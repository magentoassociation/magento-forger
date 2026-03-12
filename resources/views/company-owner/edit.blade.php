@extends('layouts.app')

@section('title', 'Edit Company - ' . $company->name)

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Edit Company</h2>
                <a href="{{ route('company-owner.index') }}" class="btn btn-outline-secondary">
                    Back to My Companies
                </a>
            </div>

            @if(session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h5 class="alert-heading">Please correct the following errors:</h5>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('company-owner.update', $company->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">
                                    Company Name <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    class="form-control @error('name') is-invalid @enderror"
                                    id="name"
                                    name="name"
                                    value="{{ old('name', $company->name) }}"
                                    required
                                    maxlength="255"
                                >
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">
                                    Email <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    id="email"
                                    name="email"
                                    value="{{ old('email', $company->email) }}"
                                    required
                                    maxlength="255"
                                >
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="website" class="form-label">
                                    Website <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="url"
                                    class="form-control @error('website') is-invalid @enderror"
                                    id="website"
                                    name="website"
                                    value="{{ old('website', $company->website) }}"
                                    required
                                    maxlength="255"
                                    placeholder="https://example.com"
                                >
                                @error('website')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">
                                    Phone <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    class="form-control @error('phone') is-invalid @enderror"
                                    id="phone"
                                    name="phone"
                                    value="{{ old('phone', $company->phone) }}"
                                    required
                                    maxlength="50"
                                >
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="linkedin_url" class="form-label">
                                LinkedIn Company URL
                            </label>
                            <input
                                type="url"
                                class="form-control @error('linkedin_url') is-invalid @enderror"
                                id="linkedin_url"
                                name="linkedin_url"
                                value="{{ old('linkedin_url', $company->linkedin_url) }}"
                                maxlength="500"
                                placeholder="https://linkedin.com/company/your-company"
                            >
                            <small class="form-text text-muted">
                                Adding a LinkedIn URL helps speed up verification
                            </small>
                            @error('linkedin_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">
                                Address <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control @error('address') is-invalid @enderror"
                                id="address"
                                name="address"
                                value="{{ old('address', $company->address) }}"
                                required
                                maxlength="255"
                                placeholder="123 Main Street"
                            >
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label for="city" class="form-label">
                                    City <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    class="form-control @error('city') is-invalid @enderror"
                                    id="city"
                                    name="city"
                                    value="{{ old('city', $company->city) }}"
                                    required
                                    maxlength="100"
                                >
                                @error('city')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="state" class="form-label">
                                    State/Province
                                </label>
                                <input
                                    type="text"
                                    class="form-control @error('state') is-invalid @enderror"
                                    id="state"
                                    name="state"
                                    value="{{ old('state', $company->state) }}"
                                    maxlength="100"
                                >
                                @error('state')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="zip" class="form-label">
                                    ZIP/Postal <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    class="form-control @error('zip') is-invalid @enderror"
                                    id="zip"
                                    name="zip"
                                    value="{{ old('zip', $company->zip) }}"
                                    required
                                    maxlength="20"
                                >
                                @error('zip')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="country_code" class="form-label">
                                Country
                            </label>
                            <select
                                class="form-select @error('country_code') is-invalid @enderror"
                                id="country_code"
                                name="country_code"
                            >
                                <option value="">Select a country</option>
                                @php
                                    $sortedCountries = cache()->rememberForever('sorted_countries_by_name', function () {
                                        return collect(countries())->sortBy('name');
                                    });
                                @endphp
                                @foreach($sortedCountries as $country)
                                    <option
                                        value="{{ $country['iso_3166_1_alpha3'] }}"
                                        @if(old('country_code', $company->country_code) === $country['iso_3166_1_alpha3']) selected @endif
                                    >
                                        {{ $country['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('country_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info">
                            <strong>Note:</strong> You can only edit company details. Status flags (Magento Member, Recommended, Approval Status) are managed by administrators.
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('company-owner.index') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
