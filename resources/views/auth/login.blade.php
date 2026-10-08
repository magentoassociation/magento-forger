@extends('layouts.app')

@section('content')
    <div class="my-5 py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5 text-center">
                        <div class="mb-4">
                            <i class="fab fa-github fa-4x text-dark"></i>
                        </div>
                        <h2 class="mb-3">Login Required</h2>
                        <p class="text-muted mb-4 fs-6">
                            Please sign in with your GitHub account to continue.
                        </p>

                        <a href="{{ route('github_login') }}" class="btn btn-dark btn-lg w-100 py-3">
                            <i class="fab fa-github me-2"></i> Login with GitHub
                        </a>

                        <p class="text-muted mt-4 mb-0 small">
                            By signing in, you agree to access this application using your GitHub credentials.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
