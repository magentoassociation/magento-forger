<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Magento Forger' }}</title>
    <meta name="description" content="Magento 2 PR & Issue Statistics Viewer">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2">
    @vite(['resources/sass/app.scss', 'resources/js/app.js']) {{-- Tailwind CSS --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @include('components._chrome-styles')
    @stack('head')
</head>
<body>
<div class="chrome-hairline"></div>
<nav class="navbar navbar-expand-lg site-nav" data-bs-theme="dark">
    <div class="container">
        <a class="navbar-brand site-brand" href="/">
            <img class="brand-mark" src="{{ asset('assets/logo_magento_soul_white.svg') }}" alt="" width="30" height="30">
            <span class="brand-word"><span class="w1">Magento Open Source </span><span class="w2">Forger</span></span>
        </a>

        {{-- Login / account stays visible outside the collapse on mobile --}}
        <div class="site-endgroup order-lg-last ms-lg-3">
            @auth
                @php
                    $acctUser = Auth::user();
                    $acctLogin = $acctUser->github_username;
                    $acctName = $acctUser->name ?: $acctLogin;
                    $acctWords = preg_split('/\s+/', trim((string) $acctName)) ?: [];
                    $acctInitials = collect($acctWords)->filter()->take(2)
                        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('')
                        ?: mb_strtoupper(mb_substr((string) $acctName, 0, 2));
                @endphp
                <div class="dropdown">
                    <button type="button" class="acct-chip" data-bs-toggle="dropdown" data-bs-display="static"
                            aria-expanded="false" aria-haspopup="menu" aria-label="Account menu, {{ $acctName }}">
                        <span class="acct-avatar">
                            <span class="acct-avatar-initials">{{ $acctInitials }}</span>
                            @if ($acctLogin)
                                <img src="https://avatars.githubusercontent.com/{{ $acctLogin }}?s=52"
                                     alt="" width="26" height="26" onerror="this.remove()">
                            @endif
                        </span>
                        <span class="acct-name">{{ $acctName }}</span>
                        <span class="acct-caret" aria-hidden="true">▾</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end acct-menu">
                        <div class="acct-head">
                            <span class="acct-head-name">{{ $acctName }}</span>
                            @if ($acctLogin)<span class="acct-head-handle">{{ '@'.$acctLogin }}</span>@endif
                        </div>
                        @if ($acctLogin)
                            <a class="acct-item" href="{{ route('leaderboard.detail', ['board' => 'contributor', 'login' => $acctLogin]) }}">My Contributions</a>
                        @endif
                        @if ($acctUser->is_admin)
                            <a class="acct-item" href="/admin">Admin</a>
                        @endif
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="acct-item acct-logout">Logout</button>
                        </form>
                    </div>
                </div>
            @endauth
            @guest
                <a href="{{ route('github_login') }}" class="site-login"><i class="fab fa-github"></i> Login with GitHub</a>
            @endguest
        </div>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            {!! $mainMenu !!}
        </div>
    </div>
</nav>

@unless (request()->routeIs('home', 'leaderboard.detail', 'leaderboard.monthly.detail'))
    @include('components.header')
@endunless

@php ($isFullBleed = request()->routeIs('home', 'leaderboard.detail', 'leaderboard.monthly.detail'))
<main role="main" class="{{ $isFullBleed ? '' : 'container mx-auto pb-4 mb-4' }}">
    @yield('content')
</main>

@include('components.footer')

@stack('scripts')
</body>
</html>
