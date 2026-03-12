<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompanyProposalController extends Controller
{
    public function propose(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-\.\&\,\']+$/', // Only allow safe characters
            ],
            'website' => ['nullable', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'linkedin_url' => [
                'nullable',
                'url',
                'max:500',
                'regex:/^https:\/\/(www\.)?linkedin\.com\/company\//', // Must be LinkedIn company URL
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'max:3'],
        ], [
            'name.regex' => 'Company name contains invalid characters.',
            'linkedin_url.regex' => 'Please provide a valid LinkedIn company page URL.',
        ]);

        // Sanitize company name (defense in depth)
        $companyName = strip_tags(trim($request->name));

        // Validate minimum meaningful content
        if (strlen($companyName) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Company name is too short.',
            ], 422);
        }

        // Check for exact duplicate (case-insensitive)
        $existing = Company::whereRaw('LOWER(name) = ?', [strtolower($companyName)])->first();
        if ($existing) {
            // Case 1: Company is pending - allow user to use it
            if ($existing->status === 'pending') {
                return response()->json([
                    'success' => true,
                    'warning' => true,
                    'company' => [
                        'id' => $existing->id,
                        'name' => e($existing->name),
                        'status' => $existing->status,
                    ],
                    'message' => 'This company has already been submitted for review by another user. You can add it to your employment history now.',
                ]);
            }

            // Case 2: Company is approved - user should refresh
            if ($existing->status === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'This company is already approved in our system. Please refresh the page to see it in the company list.',
                ], 422);
            }

            // Case 3: Company is rejected - generic error (no special handling for security)
            if ($existing->status === 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => 'This company already exists.',
                ], 422);
            }
        }

        // Generate unique ID for placeholders
        $uniqueId = uniqid('pending_', true);

        // Common placeholder patterns to ignore
        $commonEmailPlaceholders = ['info@example.com', 'contact@example.com', 'pending@example.com'];
        $commonWebsitePlaceholders = ['https://example.com', 'http://example.com', 'https://www.example.com'];

        // Email: Use unique placeholder if empty, common placeholder, or already exists
        $email = $uniqueId.'@pending.example.com';
        $providedEmail = trim($request->input('email', ''));
        if (! empty($providedEmail)) {
            $sanitizedEmail = filter_var($providedEmail, FILTER_SANITIZE_EMAIL);
            // Only use provided email if it's not empty, not a common placeholder, and doesn't already exist
            if (! empty($sanitizedEmail) &&
                ! in_array(strtolower($sanitizedEmail), $commonEmailPlaceholders) &&
                ! Company::where('email', $sanitizedEmail)->exists()) {
                $email = $sanitizedEmail;
            }
        }

        // Website: Use unique placeholder if empty, common placeholder, or already exists
        $website = 'https://pending.example.com/'.$uniqueId;
        $providedWebsite = trim($request->input('website', ''));
        if (! empty($providedWebsite)) {
            $sanitizedWebsite = filter_var($providedWebsite, FILTER_SANITIZE_URL);
            // Only use provided website if it's not empty, not a common placeholder, and doesn't already exist
            if (! empty($sanitizedWebsite) &&
                ! in_array(strtolower($sanitizedWebsite), $commonWebsitePlaceholders) &&
                ! Company::where('website', $sanitizedWebsite)->exists()) {
                $website = $sanitizedWebsite;
            }
        }

        // Phone: Generate unique placeholder or check if provided phone exists
        $phone = '000-000-'.substr($uniqueId, -4); // Use last 4 chars of uniqueId
        $providedPhone = trim($request->input('phone', ''));
        if (! empty($providedPhone)) {
            $sanitizedPhone = strip_tags($providedPhone);
            // Check if phone already exists
            if (! Company::where('phone', $sanitizedPhone)->exists()) {
                $phone = $sanitizedPhone;
            }
            // Otherwise use unique placeholder
        }

        $providedLinkedin = trim($request->input('linkedin_url', ''));
        $linkedinUrl = ! empty($providedLinkedin) ? filter_var($providedLinkedin, FILTER_SANITIZE_URL) : null;

        $providedAddress = trim($request->input('address', ''));
        $address = ! empty($providedAddress) ? strip_tags($providedAddress) : 'Pending Review';

        $providedCity = trim($request->input('city', ''));
        $city = ! empty($providedCity) ? strip_tags($providedCity) : 'Pending';

        $providedState = trim($request->input('state', ''));
        $state = ! empty($providedState) ? strip_tags($providedState) : null;

        $providedZip = trim($request->input('zip', ''));
        $zip = ! empty($providedZip) ? strip_tags($providedZip) : '00000';

        $providedCountryCode = trim($request->input('country_code', ''));
        $countryCode = ! empty($providedCountryCode) ? strip_tags($providedCountryCode) : null;

        // Create pending company
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

            return response()->json([
                'success' => true,
                'company' => [
                    'id' => $company->id,
                    'name' => e($company->name), // Escape for JSON output
                    'status' => $company->status,
                ],
            ]);
        } catch (QueryException $e) {
            // Log the actual error for debugging
            Log::error('Company proposal failed', [
                'error' => $e->getMessage(),
                'company_name' => $companyName,
            ]);

            // Check if it's a unique constraint violation
            if (str_contains($e->getMessage(), 'Duplicate entry') || $e->getCode() == 23000) {
                return response()->json([
                    'success' => false,
                    'message' => 'A company with this information already exists.',
                ], 422);
            }

            // Generic error for other database issues
            return response()->json([
                'success' => false,
                'message' => 'Failed to create company proposal. Please try again.',
            ], 500);
        }
    }
}
