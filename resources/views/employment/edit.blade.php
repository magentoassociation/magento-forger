@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-6 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Edit Employment</h2>
                    <a href="{{ route('employment') }}" class="btn btn-outline-secondary">
                        Back to Employment
                    </a>
                </div>

                @if(session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if($errors->has('conflict'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ $errors->first('conflict') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('employment.update', $affiliation->id) }}">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
                                <select name="company_id" id="company_id" class="form-select @error('company_id') is-invalid @enderror" required>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ $affiliation->company_id == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('company_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input
                                    type="date"
                                    name="start_date"
                                    id="start_date"
                                    value="{{ $affiliation->start_date }}"
                                    class="form-control @error('start_date') is-invalid @enderror"
                                    required
                                >
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input
                                    type="date"
                                    name="end_date"
                                    id="end_date"
                                    value="{{ $affiliation->end_date }}"
                                    class="form-control @error('end_date') is-invalid @enderror"
                                >
                                <div class="form-text">Leave empty if you're currently employed at this company</div>
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('employment') }}" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" class="btn btn-success">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
