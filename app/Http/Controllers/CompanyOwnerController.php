<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyOwnerController extends Controller
{
    public function index()
    {
        $companies = auth()->user()->companies()->with('owners')->get();

        return view('company-owner.index', compact('companies'));
    }

    public function edit($id)
    {
        $company = Company::findOrFail($id);

        // Check if user owns this company
        $this->checkIfUserOwnsThisCompany($id);

        return view('company-owner.edit', compact('company'));
    }

    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        // Check if user owns this company
        $this->checkIfUserOwnsThisCompany($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('companies', 'name')->ignore($id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('companies', 'email')->ignore($id)],
            'phone' => ['required', 'string', 'max:50', Rule::unique('companies', 'phone')->ignore($id)],
            'website' => ['required', 'url', 'max:255', Rule::unique('companies', 'website')->ignore($id)],
            'linkedin_url' => 'nullable|url|max:500',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip' => 'required|string|max:20',
            'country_code' => 'nullable|string|max:3',
        ]);

        // Update company (fillable fields only, status/flags remain protected)
        $company->update($validated);

        return redirect()->route('company-owner.edit', $id)
            ->with('status', 'Company updated successfully!');
    }

    /**
     * @param $id
     * @return void
     */
    private function checkIfUserOwnsThisCompany($id): void
    {
        if (!auth()->user()->companies()->where('companies.id', $id)->exists()) {
            abort(403, 'You do not have permission to edit this company.');
        }
    }
}
