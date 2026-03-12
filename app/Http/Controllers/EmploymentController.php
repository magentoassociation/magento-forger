<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class EmploymentController extends Controller
{
    public function create()
    {
        return view('employment.form', [
            'companies' => \App\Models\Company::where('status', 'approved')->orderBy('name')->get(),
            'affiliations' => auth()->user()->affiliations()->with('company')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $user = auth()->user();
        if ($user) {
            $conflict = $user->affiliations()
                ->where('company_id', $request->company_id)
                ->where(function ($query) use ($request) {
                    $query
                        ->where(function ($q) use ($request) {
                            $q->whereNull('end_date')
                                ->orWhere('end_date', '>=', $request->start_date);
                        })
                        ->where('start_date', '<=', $request->end_date ?? now());
                })
                ->exists();

            if ($conflict) {
                return back()
                    ->withErrors(['conflict' => 'You already have a conflicting employment period at this company.'])
                    ->withInput();
            }

            $user->affiliations()->create($request->only([
                'company_id',
                'start_date',
                'end_date',
            ]));
        } else {
            return back()->withErrors(['error' => 'You must be logged in to add employment.']);
        }

        return back()->with('status', 'Employment saved.');
    }

    public function edit($id)
    {
        $affiliation = auth()->user()->affiliations()->findOrFail($id);
        $companies = \App\Models\Company::where('status', 'approved')->orderBy('name')->get();

        return view('employment.edit', compact('affiliation', 'companies'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $user = auth()->user();
        $affiliation = $user->affiliations()->findOrFail($id);

        $conflict = $user->affiliations()
            ->where('company_id', $request->company_id)
            ->where('id', '!=', $id)
            ->where(function ($query) use ($request) {
                $query
                    ->where(function ($q) use ($request) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', $request->start_date);
                    })
                    ->where('start_date', '<=', $request->end_date ?? now());
            })
            ->exists();

        if ($conflict) {
            return back()
                ->withErrors(['conflict' => 'This employment period overlaps another one you’ve already submitted.'])
                ->withInput();
        }

        $affiliation->update($request->only([
            'company_id',
            'start_date',
            'end_date',
        ]));

        return redirect('/employment')->with('status', 'Employment updated.');
    }

    public function destroy($id)
    {
        $affiliation = auth()->user()->affiliations()->findOrFail($id);
        $affiliation->delete();

        return redirect('/employment')->with('status', 'Employment deleted.');
    }
}
