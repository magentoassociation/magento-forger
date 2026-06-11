<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Company;

use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class CompanyProposalProcessor
{
    /**
     * Deduplicate, sanitize, and persist a proposed company.
     *
     * @param  array<string, mixed>  $data  Validated proposal fields (name, website, email, etc.)
     * @return array{httpStatus: int, body: array<string, mixed>}
     */
    public function process(array $data): array
    {
        $companyName = strip_tags(trim($data['name']));

        if (strlen($companyName) < 2) {
            return [
                'httpStatus' => 422,
                'body' => ['success' => false, 'message' => 'Company name is too short.'],
            ];
        }

        $existing = Company::whereRaw('LOWER(name) = ?', [strtolower($companyName)])->first();
        if ($existing) {
            if ($existing->status === 'pending') {
                return [
                    'httpStatus' => 200,
                    'body' => [
                        'success' => true,
                        'warning' => true,
                        'company' => [
                            'id' => $existing->id,
                            'name' => e($existing->name),
                            'status' => $existing->status,
                        ],
                        'message' => 'This company has already been submitted for review by another user. You can add it to your employment history now.',
                    ],
                ];
            }

            if ($existing->status === 'approved') {
                return [
                    'httpStatus' => 422,
                    'body' => [
                        'success' => false,
                        'message' => 'This company is already approved in our system. Please refresh the page to see it in the company list.',
                    ],
                ];
            }

            if ($existing->status === 'rejected') {
                return [
                    'httpStatus' => 422,
                    'body' => ['success' => false, 'message' => 'This company already exists.'],
                ];
            }
        }

        $uniqueId = uniqid('pending_', true);

        $commonEmailPlaceholders = ['info@example.com', 'contact@example.com', 'pending@example.com'];
        $commonWebsitePlaceholders = ['https://example.com', 'http://example.com', 'https://www.example.com'];

        $email = $uniqueId.'@pending.example.com';
        $providedEmail = trim($data['email'] ?? '');
        if (! empty($providedEmail)) {
            $sanitizedEmail = filter_var($providedEmail, FILTER_SANITIZE_EMAIL);
            if (! empty($sanitizedEmail)
                && ! in_array(strtolower($sanitizedEmail), $commonEmailPlaceholders)
                && ! Company::where('email', $sanitizedEmail)->exists()
            ) {
                $email = $sanitizedEmail;
            }
        }

        $website = 'https://pending.example.com/'.$uniqueId;
        $providedWebsite = trim($data['website'] ?? '');
        if (! empty($providedWebsite)) {
            $sanitizedWebsite = filter_var($providedWebsite, FILTER_SANITIZE_URL);
            if (! empty($sanitizedWebsite)
                && ! in_array(strtolower($sanitizedWebsite), $commonWebsitePlaceholders)
                && ! Company::where('website', $sanitizedWebsite)->exists()
            ) {
                $website = $sanitizedWebsite;
            }
        }

        $phone = '000-000-'.substr($uniqueId, -4);
        $providedPhone = trim($data['phone'] ?? '');
        if (! empty($providedPhone)) {
            $sanitizedPhone = strip_tags($providedPhone);
            if (! Company::where('phone', $sanitizedPhone)->exists()) {
                $phone = $sanitizedPhone;
            }
        }

        $providedLinkedin = trim($data['linkedin_url'] ?? '');
        $linkedinUrl = ! empty($providedLinkedin) ? filter_var($providedLinkedin, FILTER_SANITIZE_URL) : null;

        $providedAddress = trim($data['address'] ?? '');
        $address = ! empty($providedAddress) ? strip_tags($providedAddress) : 'Pending Review';

        $providedCity = trim($data['city'] ?? '');
        $city = ! empty($providedCity) ? strip_tags($providedCity) : 'Pending';

        $providedState = trim($data['state'] ?? '');
        $state = ! empty($providedState) ? strip_tags($providedState) : null;

        $providedZip = trim($data['zip'] ?? '');
        $zip = ! empty($providedZip) ? strip_tags($providedZip) : '00000';

        $providedCountryCode = trim($data['country_code'] ?? '');
        $countryCode = ! empty($providedCountryCode) ? strip_tags($providedCountryCode) : null;

        try {
            $company = Company::create([
                'name' => $companyName,
                'email' => $email,
                'website' => $website,
                'phone' => $phone,
                'linkedin_url' => $linkedinUrl,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'country_code' => $countryCode,
            ]);

            return [
                'httpStatus' => 200,
                'body' => [
                    'success' => true,
                    'company' => [
                        'id' => $company->id,
                        'name' => e($company->name),
                        'status' => $company->status,
                    ],
                ],
            ];
        } catch (QueryException $e) {
            Log::error('Company proposal failed', [
                'error' => $e->getMessage(),
                'company_name' => $companyName,
            ]);

            if (str_contains($e->getMessage(), 'Duplicate entry') || $e->getCode() == 23000) {
                return [
                    'httpStatus' => 422,
                    'body' => ['success' => false, 'message' => 'A company with this information already exists.'],
                ];
            }

            return [
                'httpStatus' => 500,
                'body' => ['success' => false, 'message' => 'Failed to create company proposal. Please try again.'],
            ];
        }
    }
}
