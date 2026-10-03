@extends('layouts.app')

@php
    $initials = function (string $name): string {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = collect($words)->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)));

        return $letters->implode('') ?: mb_strtoupper(mb_substr($name, 0, 2));
    };

    $identity = function ($stat) use ($profiles, $initials) {
        $profile = $profiles->get($stat->login);
        $name = $profile?->name ?: $stat->login;

        return view('leaderboard._highlight-id', [
            'login' => $stat->login,
            'name' => $name,
            'initials' => $initials($name),
        ]);
    };

    $detail = fn ($login) => route('leaderboard.detail', ['board' => 'contributor', 'login' => $login]);

    // Abbreviate "10 years 3 weeks" → "10y 3w"; keep the unit the source gives.
    $abbrevGap = function (int $days): string {
        $gap = \Carbon\Carbon::now()->subDays($days)
            ->diffForHumans(\Carbon\Carbon::now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE, false, 2);

        return strtr($gap, [
            ' years' => 'y', ' year' => 'y', ' months' => 'm', ' month' => 'm',
            ' weeks' => 'w', ' week' => 'w', ' days' => 'd', ' day' => 'd',
            ' hours' => 'h', ' hour' => 'h', ' minutes' => 'min', ' minute' => 'min',
        ]);
    };

    $newContributors = $newContributors->take(10);
    // Comebacks show 12 by default; ?comebacks=all reveals the rest (Show-all-as-URL,
    // mirroring the detail page). Keep the full count to decide the affordance.
    $showAllComebacks = request('comebacks') === 'all';
    $comebacksTotal = $comebacks->count();
    $comebacksShown = $showAllComebacks ? $comebacks : $comebacks->take(12);
    $rising = $rising->take(16);
    $recentlyActive = $recentlyActive->take(16);
    $risingWindow = (int) config('leaderboard.rising.window_days', 30);
    $spotlightWindow = (int) config('leaderboard.spotlight.window_days', 30);
    $topScore = (float) ($newContributors->max('contributor_score') ?: 1);
@endphp

@section('content')
    <div class="lb">
        <p class="lb-intro">A closer look at newcomers, fast risers, returning contributors, and who's active right now — not just the all-time leaders.</p>

        @include('leaderboard._tabs')

        @if ($newContributors->isEmpty() && $rising->isEmpty() && $comebacks->isEmpty() && $recentlyActive->isEmpty())
            <div class="alert alert-info">
                No highlights yet. Run <code>ddev artisan leaderboard:compute</code> to populate.
            </div>
        @else
            {{-- New contributor spotlight --}}
            <section class="lb-hl-spotlight">
                <div class="lb-section-head">
                    <h2 class="lb-section-title">New contributor spotlight</h2>
                    <span class="lb-section-unit">First contribution · {{ $spotlightWindow }} days · Score</span>
                </div>
                @forelse ($newContributors as $i => $stat)
                    <a href="{{ $detail($stat->login) }}" class="lb-hl-row lb-hl-row--spot">
                        <span class="lb-hl-rank">{{ $i + 1 }}</span>
                        {{ $identity($stat) }}
                        <span class="lb-hl-bar">
                            <span class="lb-hl-bar-fill" style="width: {{ round($stat->contributor_score / $topScore * 100, 2) }}%"></span>
                        </span>
                        <span class="lb-hl-value">{{ number_format($stat->contributor_score, 1) }}</span>
                    </a>
                @empty
                    <div class="lb-hl-empty">Nobody new yet.</div>
                @endforelse
            </section>

            {{-- Comebacks --}}
            <section class="lb-hl-comebacks">
                <div class="lb-section-head">
                    <h2 class="lb-section-title">Comebacks</h2>
                    <span class="lb-section-unit">Time away</span>
                </div>
                @if ($comebacksTotal === 0)
                    <p class="lb-hl-note">No comebacks yet.</p>
                @else
                    <div class="lb-hl-grid3">
                        @foreach ($comebacksShown as $stat)
                            <a href="{{ $detail($stat->login) }}" class="lb-hl-card">
                                {{ $identity($stat) }}
                                <span class="lb-hl-away">{{ $abbrevGap((int) $stat->returned_after_days) }}</span>
                            </a>
                        @endforeach
                    </div>
                    @if ($comebacksTotal > 12 && ! $showAllComebacks)
                        <a href="{{ request()->fullUrlWithQuery(['comebacks' => 'all']) }}" class="lb-more lb-hl-more">Show all {{ number_format($comebacksTotal) }}</a>
                    @endif
                    <p class="lb-hl-note">
                        {{ $comebacksSort === 'away' ? 'Longest away first.' : 'Most recently back first.' }}
                        <a href="{{ request()->fullUrlWithQuery(['comebacks_sort' => $comebacksSort === 'away' ? null : 'away']) }}" class="lb-hl-sort">{{ $comebacksSort === 'away' ? 'Sort by most recently back instead' : 'Sort by time away instead' }}</a>.
                    </p>
                @endif
            </section>

            {{-- Rising | Recently active --}}
            <div class="lb-hl-cols">
                <section>
                    <div class="lb-section-head">
                        <h2 class="lb-section-title">Rising</h2>
                        <span class="lb-section-unit">Gain · {{ $risingWindow }} days</span>
                    </div>
                    <p class="lb-section-desc">Biggest increase in contributor score over the past {{ $risingWindow }} days.</p>
                    @forelse ($rising as $i => $stat)
                        <a href="{{ $detail($stat->login) }}" class="lb-hl-row lb-hl-row--rank">
                            <span class="lb-hl-rank">{{ $i + 1 }}</span>
                            {{ $identity($stat) }}
                            <span class="lb-hl-value">+{{ number_format($stat->contributor_score - $stat->rising_baseline_score, 1) }}</span>
                        </a>
                    @empty
                        <div class="lb-hl-empty">No movement yet.</div>
                    @endforelse
                </section>

                <section>
                    <div class="lb-section-head">
                        <h2 class="lb-section-title">Recently active</h2>
                        <span class="lb-section-unit">Score</span>
                    </div>
                    <p class="lb-section-desc">Opened a PR, had one merged, or opened an issue in the last 30 days.</p>
                    @forelse ($recentlyActive as $i => $stat)
                        <a href="{{ $detail($stat->login) }}" class="lb-hl-row lb-hl-row--rank">
                            <span class="lb-hl-rank">{{ $i + 1 }}</span>
                            {{ $identity($stat) }}
                            <span class="lb-hl-value">{{ number_format($stat->contributor_score, 1) }}</span>
                        </a>
                    @empty
                        <div class="lb-hl-empty">Nobody recent yet.</div>
                    @endforelse
                </section>
            </div>
        @endif
    </div>
@endsection

@push('head')
    @include('leaderboard._lb-styles')
@endpush
