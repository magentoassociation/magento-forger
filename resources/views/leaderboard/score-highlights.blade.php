@extends('layouts.app')

@php
    $githubLink = fn (string $login) => '<a href="https://github.com/'.e($login).'" target="_blank" rel="noopener noreferrer" class="text-decoration-none fw-medium">'.e($login).'</a>';
@endphp

@section('content')
    <div class="container">
        <div class="row mb-3">
            <div class="col-12">
                <h2>Highlights</h2>
                <p class="text-muted">Segments that surface entry, momentum, and re-engagement — not just the all-time top.</p>
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
                            <strong>New contributor spotlight</strong>
                            <div class="small text-muted">First contribution in the last 30 days</div>
                        </div>
                        <ul class="list-group list-group-flush">
                            @forelse ($newContributors as $stat)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    {!! $githubLink($stat->login) !!}
                                    <span class="badge text-bg-success rounded-pill">{{ number_format($stat->contributor_score, 1) }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">Nobody new yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                {{-- Rising --}}
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <strong>Rising</strong>
                            <div class="small text-muted">Biggest score gain since the last run</div>
                        </div>
                        <ul class="list-group list-group-flush">
                            @forelse ($rising as $stat)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    {!! $githubLink($stat->login) !!}
                                    <span class="badge text-bg-primary rounded-pill">+{{ number_format($stat->contributor_score - $stat->contributor_score_prev, 1) }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">No movement yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                {{-- Comebacks --}}
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <strong>Comebacks</strong>
                            <div class="small text-muted">Returned after a long silence</div>
                        </div>
                        <ul class="list-group list-group-flush">
                            @forelse ($comebacks as $stat)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    {!! $githubLink($stat->login) !!}
                                    <span class="text-muted small">back after {{ number_format($stat->returned_after_days) }} days</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">No comebacks yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                {{-- Recently active --}}
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <strong>Recently active</strong>
                            <div class="small text-muted">Active in the last 14 days, by score</div>
                        </div>
                        <ul class="list-group list-group-flush">
                            @forelse ($recentlyActive as $stat)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    {!! $githubLink($stat->login) !!}
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
