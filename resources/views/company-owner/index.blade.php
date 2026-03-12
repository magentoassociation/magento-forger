@extends('layouts.app')

@section('title', 'My Companies')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">My Companies</h2>

            @if($companies->isEmpty())
                <div class="alert alert-info">
                    <h5 class="alert-heading">No Companies Yet</h5>
                    <p class="mb-0">You haven't been assigned as an owner of any companies yet.</p>
                </div>
            @else
                <div class="row">
                    @foreach($companies as $company)
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">{{ $company->name }}</h5>

                                    <div class="mb-2">
                                        <span class="badge
                                            @if($company->status === 'approved') bg-success
                                            @elseif($company->status === 'pending') bg-warning
                                            @else bg-danger
                                            @endif">
                                            {{ ucfirst($company->status) }}
                                        </span>
                                    </div>

                                    <div class="small text-muted mb-3">
                                        <div><strong>Website:</strong> <a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer">{{ $company->website }}</a></div>
                                        <div><strong>Email:</strong> {{ $company->email }}</div>
                                        <div><strong>Phone:</strong> {{ $company->phone }}</div>
                                        <div><strong>Location:</strong> {{ $company->city }}, {{ $company->state }} {{ $company->zip }}@if($company->country), {{ $company->country }}@endif</div>
                                        @if($company->linkedin_url)
                                            <div><strong>LinkedIn:</strong> <a href="{{ $company->linkedin_url }}" target="_blank" rel="noopener noreferrer">View Profile</a></div>
                                        @endif
                                    </div>

                                    @if($company->owners->count() > 1)
                                        <div class="small mb-3">
                                            <strong>Other Owners:</strong><br>
                                            @foreach($company->owners as $owner)
                                                @if($owner->id !== auth()->id())
                                                    <span class="badge bg-secondary me-1">{{ $owner->github_username }}</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    <a href="{{ route('company-owner.edit', $company->id) }}" class="btn btn-primary btn-sm">
                                        Edit Company Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
