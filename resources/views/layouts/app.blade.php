<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Magento Forger' }}</title>
    <meta name="description" content="Magento 2 PR & Issue Statistics Viewer">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/sass/app.scss', 'resources/js/app.js']) {{-- Tailwind CSS --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Put in here because laravel stinks and nothing works as documented and frontend people are morons in general that just overcomplicate things for no reason -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('head')
</head>
<body class="bg-gray-100 text-gray-900 font-sans min-h-screen flex flex-col">
@include('components.universe-bar')
<nav class="navbar navbar-expand-lg navbar-primary bg-primary" data-bs-theme="dark">
    <div class="container">
        <a class="navbar-brand fs-5" href="/">
            <img src="{{ asset('assets/logo_magento_soul_white.svg') }}" alt="Logo" width="32" height="32" style="margin-top: -3px;">
            <span class="fw-light">Magento Open Source</span> Forger
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            {!! $mainMenu !!}

            <div class="navbar-nav ms-auto">
                <hr class="d-lg-none text-white my-2">

                @auth
                    <div class="nav-item text-center text-lg-start mb-2 mb-lg-0 me-lg-3">
                        <span class="navbar-text text-white" title="{{ Auth::user()->name }} ({{ Auth::user()->github_username }})">
                            <span class="d-none d-lg-inline text-truncate d-inline-block" style="max-width: 250px;">
                                {{ Auth::user()->name }} ({{ Auth::user()->github_username }})
                            </span>
                            <span class="d-lg-none">
                                {{ Auth::user()->name }}<br>
                                <small class="text-white-50">({{ Auth::user()->github_username }})</small>
                            </span>
                        </span>
                    </div>

                    <div class="d-flex justify-content-center justify-content-lg-start gap-2 flex-wrap">
                        @if(Auth::user()->is_admin)
                            <a href="/admin" class="btn btn-sm btn-outline-light">
                                <i class="fas fa-cog"></i> Admin
                            </a>
                        @endif

                        @if(Auth::user()->companies()->exists())
                            <a href="{{ route('company-owner.index') }}" class="btn btn-sm btn-outline-light">
                                <i class="fas fa-building"></i> My Companies
                            </a>
                        @endif

                        <form action="{{ route('logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-light">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </button>
                        </form>
                    </div>
                @endauth

                @guest
                    <div class="nav-item text-center text-lg-start">
                        <a href="{{ route('github_login') }}" class="btn btn-sm btn-outline-light">
                            <i class="fab fa-github"></i> Login with GitHub
                        </a>
                    </div>
                @endguest
            </div>
        </div>
    </div>
</nav>
@include('components.header')

<main role="main" class="flex-grow container mx-auto pt-4 px-3 px-md-4 pb-4 transition-all duration-300 ease-in-out mb-4">
    @yield('content')
</main>

@include('components.footer')

@stack('scripts')
</body>
</html>
