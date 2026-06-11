<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Company\CompanyProposalProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyProposalController extends Controller
{
    public function propose(Request $request, CompanyProposalProcessor $processor): JsonResponse
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-\.\&\,\']+$/',
            ],
            'website' => ['nullable', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'linkedin_url' => [
                'nullable',
                'url',
                'max:500',
                'regex:/^https:\/\/(www\.)?linkedin\.com\/company\//',
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

        $result = $processor->process($request->only([
            'name', 'website', 'email', 'phone', 'linkedin_url',
            'address', 'city', 'state', 'zip', 'country_code',
        ]));

        return response()->json($result['body'], $result['httpStatus']);
    }
}
