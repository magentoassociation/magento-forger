@extends('layouts.app')

@php
    $userLink = function (string $login) use ($profiles) {
        $profile = $profiles->get($login);
        $avatar = $profile?->avatar_url ?: 'https://github.com/'.$login.'.png?size=48';
        $label = $profile?->name
            ? e($profile->name).' <span class="text-muted small">@'.e($login).'</span>'
            : e($login);

        return '<a href="https://github.com/'.e($login).'" target="_blank" rel="noopener noreferrer" class="text-decoration-none fw-medium">'
            .'<img src="'.e($avatar).'" alt="" width="20" height="20" class="rounded-circle me-2" loading="lazy" onerror="this.style.display=\'none\'">'
            .$label.'</a>';
    };

    $humanGap = function (int $days) {
        if ($days < 60) {
            return number_format($days).' days';
        }

        return \Carbon\Carbon::now()->subDays($days)->diffForHumans(\Carbon\Carbon::now(), true, false, 2);
    };
@endphp

@section('content')
    <div class="container">
        <div class="row mb-3">
            <div class="col-12">
                <p class="text-muted">A closer look at newcomers, fast risers, returning contributors, and who's active right now — not just the all-time leaders.</p>
            </div>
        </div>

        @include('leaderboard._tabs')

        @if ($newContributors->isEmpty() && $rising->isEmpty() && $comebacks->isEmpty() && $recentlyActive->isEmpty())
            <div class="alert alert-info">
                No highlights yet. Run <code>ddev artisan leaderboard:compute</code> to populate.
            </div>
        @else
            <div class="row g-4">
                {{-- New contributor spotlight --}}
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <strong>New Contributor Spotlight</strong>
                            <div class="small text-muted">People whose first-ever contribution to the project landed in the last 30 days, ranked by contributor score.</div>
                        </div>
                        <ul class="list-group list-group-flush">
                            @forelse ($newContributors as $stat)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    {!! $userLink($stat->login) !!}
                                    <span class="d-flex align-items-center gap-2">
                                        @if ($stat->first_contribution_url)
                                            <a href="{{ $stat->first_contribution_url }}" target="_blank" rel="noopener noreferrer"
                                               class="text-muted small text-decoration-none text-end" title="{{ $stat->first_contribution_title }}">
                                                first contribution &rarr;
                                            </a>
                                        @endif
                                        <span class="badge text-bg-success rounded-pill">{{ number_format($stat->contributor_score, 1) }}</span>
                                    </span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">Nobody new yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                {{-- Comebacks --}}
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <strong>Comebacks</strong>
                            <div class="small text-muted">Contributors who have started up again after an absence.</div>
                        </div>
                        <ul class="list-group list-group-flush">
                            @forelse ($comebacks as $stat)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    {!! $userLink($stat->login) !!}
                                    @if ($stat->comeback_url)
                                        <a href="{{ $stat->comeback_url }}" target="_blank" rel="noopener noreferrer"
                                           class="text-muted small text-decoration-none text-end" title="{{ $stat->comeback_title }}">
                                            back after {{ $humanGap($stat->returned_after_days) }} &rarr;
                                        </a>
                                    @else
                                        <span class="text-muted small">back after {{ $humanGap($stat->returned_after_days) }}</span>
                                    @endif
                                </li>
                            @empty
                                <li class="list-group-item text-muted">No comebacks yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                {{-- Rising --}}
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <strong>Rising</strong>
                            <div class="small text-muted">Biggest gain in contributor score over the past {{ config('leaderboard.rising.window_days', 7) }} days (the badge shows the increase).</div>
                        </div>
                        <ul class="list-group list-group-flush">
                            @forelse ($rising as $stat)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    {!! $userLink($stat->login) !!}
                                    <span class="badge text-bg-primary rounded-pill">+{{ number_format($stat->contributor_score - $stat->rising_baseline_score, 1) }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">No movement yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                {{-- Recently active --}}
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <strong>Recently Active</strong>
                            <div class="small text-muted">Opened a PR, had a PR merged, or opened an issue in the last 30 days, ranked by contributor score.</div>
                        </div>
                        <ul class="list-group list-group-flush">
                            @forelse ($recentlyActive as $stat)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    {!! $userLink($stat->login) !!}
                                    <span class="badge text-bg-success rounded-pill">{{ number_format($stat->contributor_score, 1) }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">Nobody recent yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
