@extends('layouts.app')

@php
    $initials = function (string $name): string {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = collect($words)->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)));

        return $letters->implode('') ?: mb_strtoupper(mb_substr($name, 0, 2));
    };

    $ready = $paths[0] ?? null;
    $viewerInTop = $viewerEntry && $topFive->contains('login', $viewerEntry->login);
@endphp

@section('content')
    {{-- Hero — full-bleed dark band, no divider from the header bar --}}
    <section class="hp-hero">
        <div class="container hp-hero-inner">
            <div class="hp-hero-left">
                <p class="hp-eyebrow">Open source · maintained in public</p>
                <h1 class="hp-h1">Ship a fix. Climb the board.</h1>
                <p class="hp-lead">
                    Magento powers thousands of stores worldwide, and it's maintained in the open by
                    developers like you. Pick an issue, open a PR, and ship a fix that real merchants
                    will use. Every contribution scores and moves you up the contributor leaderboard.
                </p>
                <div class="hp-cta">
                    <a href="{{ $ready['url'] ?? route('leaderboard.show', ['board' => 'contributor']) }}"
                       target="magentoForgerGitHub" rel="noopener" class="hp-cta-primary">Find an issue to work on →</a>
                    @guest
                        <a href="{{ route('github_login') }}" class="hp-cta-secondary">
                            <i class="fab fa-github" style="font-size: 15px;"></i> Login with GitHub
                        </a>
                    @endguest
                </div>
            </div>

            <div class="hp-hero-right">
                <ul class="hp-board" aria-label="Top contributors this month">
                    <li class="hp-board-head">
                        <span class="hp-board-label">Leaderboard · last 12 months</span>
                        <a href="{{ route('leaderboard.show', ['board' => 'contributor']) }}" class="hp-board-full">Full board →</a>
                    </li>

                    @foreach ($topFive as $i => $entry)
                        @php
                            $profile = $profiles->get($entry->login);
                            $name = $profile?->name ?: $entry->login;
                            $isYou = $viewerEntry && $viewerEntry->login === $entry->login;
                        @endphp
                        <li class="hp-board-row{{ $isYou ? ' hp-board-row--you' : '' }}">
                            <a href="{{ route('leaderboard.detail', ['board' => 'contributor', 'login' => $entry->login]) }}" class="hp-row">
                                <span class="hp-rank{{ $isYou ? ' hp-rank--you' : '' }}">{{ str_pad((string) ($entry->rank ?? $i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="hp-avatar">
                                    <span class="hp-avatar-initials">{{ $initials($name) }}</span>
                                    <img src="https://avatars.githubusercontent.com/{{ $entry->login }}?s=52"
                                         alt="" width="26" height="26" loading="lazy" onerror="this.remove()">
                                </span>
                                <span class="hp-name">{{ $name }}</span>
                                <span class="hp-score">{{ number_format($entry->score, 1) }}</span>
                            </a>
                        </li>
                    @endforeach

                    @unless ($viewerInTop)
                        @auth
                            @if ($viewerEntry)
                                {{-- Signed in, ranked outside the top five --}}
                                @php
                                    $vname = $profiles->get($viewerEntry->login)?->name ?: (auth()->user()->name ?: $viewerEntry->login);
                                @endphp
                                <li class="hp-board-you">
                                    <a href="{{ route('leaderboard.detail', ['board' => 'contributor', 'login' => $viewerEntry->login]) }}" class="hp-row">
                                        <span class="hp-rank hp-rank--you">{{ str_pad((string) $viewerEntry->rank, 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="hp-avatar">
                                            <span class="hp-avatar-initials">{{ $initials($vname) }}</span>
                                            <img src="https://avatars.githubusercontent.com/{{ $viewerEntry->login }}?s=52"
                                                 alt="" width="26" height="26" loading="lazy" onerror="this.remove()">
                                        </span>
                                        <span class="hp-name">{{ $vname }}</span>
                                        <span class="hp-score">{{ number_format($viewerEntry->score, 1) }}</span>
                                    </a>
                                    <p class="hp-you-caption">Your rank over the last 12 months.</p>
                                </li>
                            @else
                                {{-- Signed in, no scoring activity in the window --}}
                                @php
                                    $vlogin = auth()->user()->github_username;
                                    $vname = $profiles->get($vlogin)?->name ?: (auth()->user()->name ?: $vlogin);
                                @endphp
                                <li class="hp-board-you">
                                    <span class="hp-row">
                                        <span class="hp-rank hp-rank--empty">—</span>
                                        <span class="hp-avatar">
                                            <span class="hp-avatar-initials">{{ $initials($vname) }}</span>
                                            <img src="https://avatars.githubusercontent.com/{{ $vlogin }}?s=52"
                                                 alt="" width="26" height="26" loading="lazy" onerror="this.remove()">
                                        </span>
                                        <span class="hp-name">{{ $vname }}</span>
                                        <span class="hp-score hp-score--empty">0.0</span>
                                    </span>
                                    <p class="hp-you-caption">Your score starts with your first contribution.</p>
                                </li>
                            @endif
                        @else
                            {{-- Signed out — the empty state is the pitch --}}
                            <li class="hp-board-you">
                                <span class="hp-row">
                                    <span class="hp-rank hp-rank--empty">—</span>
                                    <span class="hp-avatar hp-avatar--dashed"></span>
                                    <span class="hp-name hp-name--empty">Your row is empty</span>
                                    <span class="hp-score hp-score--empty">0.0</span>
                                </span>
                                <p class="hp-you-caption">Your score starts with your first contribution.</p>
                            </li>
                        @endauth
                    @endunless
                </ul>
            </div>
        </div>
    </section>

    {{-- Start contributing --}}
    @if ($ready)
        <section class="container hp-section hp-start">
            <h2 class="hp-h2">Start contributing</h2>
            <p class="hp-sub">Pick up a confirmed, prioritized issue and open your first PR.</p>
            <a href="{{ $ready['url'] }}" target="magentoForgerGitHub" rel="noopener" class="hp-ready">
                <span class="hp-ready-body">
                    <span class="hp-ready-title">Ready to code</span>
                    <span class="hp-ready-desc">Confirmed, prioritized issues waiting for a developer.</span>
                </span>
                @isset($ready['count'])
                    <span class="hp-chip">{{ number_format($ready['count']) }} open</span>
                @endisset
                <span class="hp-ready-arrow" aria-hidden="true">→</span>
            </a>
        </section>
    @endif

    {{-- Pick your area + First time contributing --}}
    <section class="container hp-section hp-area">
        @if (! empty($areas))
            <h2 class="hp-h2">Pick your area</h2>
            <p class="hp-sub">Jump straight to open issues in the part of Magento you know best.</p>
            <div class="hp-grid">
                @foreach ($areas as $area)
                    <a class="hp-cell" href="{{ $area['url'] }}" target="magentoForgerGitHub" rel="noopener"
                       aria-label="{{ $area['name'] }}, {{ number_format($area['count']) }} open">
                        <span class="hp-cell-name">{{ $area['name'] }}</span>
                        <span class="hp-cell-count">{{ number_format($area['count']) }} open</span>
                    </a>
                @endforeach
                @if (count($areas) % 2 === 1)
                    <span class="hp-cell hp-cell--empty" aria-hidden="true"></span>
                @endif
            </div>
        @endif

        <div class="hp-first">
            <div class="hp-first-head">
                <h3 class="hp-h3">First time contributing?</h3>
                <p class="hp-first-sub">Three steps to your first merged PR.</p>
            </div>
            <ol class="hp-steps">
                <li class="hp-step">
                    <span class="hp-step-num">01</span>
                    Read the <a href="{{ $links['contributing'] }}" target="_blank" rel="noopener">Contribution Guidelines</a>
                </li>
                <li class="hp-step">
                    <span class="hp-step-num">02</span>
                    Set up your <a href="{{ $links['dev_setup'] }}" target="_blank" rel="noopener">development environment</a>
                </li>
                <li class="hp-step">
                    <span class="hp-step-num">03</span>
                    <a href="{{ $ready['url'] ?? '#' }}" target="magentoForgerGitHub" rel="noopener">Claim an issue</a> and open your first PR
                </li>
            </ol>
        </div>
    </section>
@endsection
