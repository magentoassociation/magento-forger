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
    {{-- Pinned: the Momentum chart styling (mirrored y labels, label backdrops) relies on Chart.js 4 behaviour. --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"
            integrity="sha384-jb8JQMbMoBUzgWatfe6COACi2ljcDdZQ2OxczGA3bGNeWe+6DChMTBJemed7ZnvJ"
            crossorigin="anonymous"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @include('components._fonts')
    @include('components._chrome-styles')
    @stack('head')
</head>
<body>
<div class="chrome-hairline"></div>
<nav class="navbar navbar-expand-lg site-nav" data-bs-theme="dark">
    <div class="container">
        <a class="navbar-brand site-brand" href="{{ route('home') }}">
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
                    <button type="button" id="acct-chip" class="acct-chip" data-bs-toggle="dropdown" data-bs-display="static"
                            aria-expanded="false" aria-haspopup="menu" aria-label="Account menu, {{ $acctName }}">
                        <span class="acct-avatar">
                            <span class="acct-avatar-initials">{{ $acctInitials }}</span>
                            @if ($acctLogin)
                                <img src="https://avatars.githubusercontent.com/{{ $acctLogin }}?s=52"
                                     alt="" width="26" height="26" onerror="this.remove()">
                            @endif
                        </span>
                        <span class="acct-name">{{ $acctName }}</span>
                        @include('components._caret')
                    </button>
                    <div class="dropdown-menu dropdown-menu-end acct-menu" role="menu" aria-labelledby="acct-chip">
                        {{-- Name is already in the chip's accessible name; hide the visual repeat. --}}
                        <div class="acct-head" aria-hidden="true">
                            <span class="acct-head-name">{{ $acctName }}</span>
                            @if ($acctLogin)<span class="acct-head-handle">{{ '@'.$acctLogin }}</span>@endif
                        </div>
                        @if ($acctLogin)
                            <a class="dropdown-item acct-item" role="menuitem" tabindex="-1" href="{{ route('leaderboard.detail', ['board' => 'contributor', 'login' => $acctLogin]) }}">My contributions</a>
                        @endif
                        @if ($acctUser->is_admin)
                            <a class="dropdown-item acct-item" role="menuitem" tabindex="-1" href="{{ route('filament.admin.pages.dashboard') }}">Admin</a>
                        @endif
                        <form action="{{ route('logout') }}" method="POST" role="none">
                            @csrf
                            <button type="submit" class="dropdown-item acct-item acct-logout" role="menuitem" tabindex="-1">Logout</button>
                        </form>
                    </div>
                </div>
                @push('scripts')
                    <script>
                        // Account menu (README-header "Behaviour"): Enter/Space opens and focuses the
                        // first item. Up/down, Esc, outside click and Tab-out closing are Bootstrap's
                        // own, which is why the items carry .dropdown-item.
                        (function () {
                            var chip = document.getElementById('acct-chip');
                            var openedByKey = false;
                            chip.addEventListener('keydown', function (e) { openedByKey = e.key === 'Enter' || e.key === ' '; });
                            chip.addEventListener('shown.bs.dropdown', function () {
                                var first = chip.nextElementSibling.querySelector('[role="menuitem"]');
                                if (openedByKey && first) { first.focus(); }
                                openedByKey = false;
                            });
                        })();
                    </script>
                @endpush
            @endauth
            @guest
                <a href="{{ route('github_login') }}" class="site-login"><i class="fab fa-github" aria-hidden="true"></i> Login with GitHub</a>
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
<main role="main" class="{{ $isFullBleed ? '' : 'container mx-auto pt-4 pb-4 mb-4' }}">
    @yield('content')
</main>

@include('components.footer')

<script>
    // Nav dropdowns (README-header "Nav dropdowns"): open on hover where the device
    // hovers, close 150ms after the pointer leaves item and menu. Click, ↓ and Esc
    // are Bootstrap's own; Enter/Space also open and focus the first link.
    (function () {
        var items = Array.prototype.slice.call(document.querySelectorAll('.site-nav .nav-item.dropdown'));
        var hovers = window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 992px)');
        var menu = function (item) { return bootstrap.Dropdown.getOrCreateInstance(item.querySelector('[data-bs-toggle="dropdown"]')); };

        items.forEach(function (item) {
            var toggle = item.querySelector('[data-bs-toggle="dropdown"]');
            var closing;

            item.addEventListener('mouseenter', function () {
                if (!hovers.matches) { return; }
                window.clearTimeout(closing);
                items.forEach(function (other) { if (other !== item) { menu(other).hide(); } });
                // show() focuses the toggle, which would draw the keyboard focus ring on hover.
                var hadFocus = document.activeElement === toggle;
                menu(item).show();
                if (!hadFocus) { toggle.blur(); }
            });
            item.addEventListener('mouseleave', function () {
                if (!hovers.matches) { return; }
                closing = window.setTimeout(function () { menu(item).hide(); }, 150);
            });
            // A parent with its own page (Leaderboard) follows its link on click where
            // hover opens the menu; touch devices still need the click to toggle it.
            toggle.addEventListener('click', function () {
                if (hovers.matches && toggle.getAttribute('href') !== '#') { window.location.href = toggle.href; }
            });
            toggle.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') { return; }
                e.preventDefault();
                menu(item).show();
                var first = item.querySelector('.dropdown-item');
                if (first) { first.focus(); }
            });
        });
    })();
</script>

@stack('scripts')
</body>
</html>
