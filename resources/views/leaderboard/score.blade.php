@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row mb-3">
            <div class="col-12">
                <p class="text-muted mb-1">
                    Ranked by the last 12 months of activity — recent work and bigger changes count for more.
                    Points come from {{ $scoring['scoredList'] }}.
                </p>
                <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#scoringModal">
                    How are scores tallied?
                </button>
            </div>
        </div>

        @include('leaderboard._tabs')

        @if ($entries->isEmpty())
            <div class="alert alert-info">
                No scores yet. Run <code>ddev artisan leaderboard:compute</code> to populate.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px">#</th>
                            <th>{{ $boards[$board] }}</th>
                            <th class="text-end">Score</th>
                            <th class="text-end" style="width: 260px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $i => $entry)
                            <tr>
                                <td class="text-muted">{{ $entry->rank ?? $i + 1 }}</td>
                                <td>
                                    @php($profile = $profiles->get($entry->login))
                                    <img src="{{ $profile?->avatar_url ?: 'https://github.com/'.$entry->login.'.png?size=48' }}"
                                         alt="" width="24" height="24" class="rounded-circle me-2" loading="lazy"
                                         onerror="this.style.display='none'">
                                    <a href="https://github.com/{{ $entry->login }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none fw-medium">
                                        {{ $profile?->name ?: $entry->login }}
                                    </a>
                                    @if ($profile?->name)
                                        <span class="text-muted small">{{ '@'.$entry->login }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <span class="badge text-bg-success rounded-pill">{{ number_format($entry->score, 1) }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-2 justify-content-end flex-nowrap">
                                        @if ($entry->score > 0)
                                            <a href="{{ route('scores.detail', ['board' => $board, 'login' => $entry->login]) }}" class="btn btn-sm btn-outline-primary text-nowrap">
                                                Details
                                            </a>
                                        @endif
                                        @if (! empty($entry->breakdown))
                                            <button class="btn btn-sm btn-outline-secondary text-nowrap" type="button"
                                                    data-bs-toggle="collapse" data-bs-target="#breakdown-{{ $i }}" aria-expanded="false">
                                                See Points Breakdown
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @if (! empty($entry->breakdown))
                                <tr class="collapse" id="breakdown-{{ $i }}">
                                    <td></td>
                                    <td colspan="3">
                                        <ul class="list-unstyled mb-0 small">
                                            @foreach ($entry->breakdown as $action => $detail)
                                                <li>
                                                    {{ \App\DataTransferObjects\Leaderboard\Action::labelFor($action) }}
                                                    &mdash; {{ number_format($detail['count'] ?? 0) }}&times; &rarr;
                                                    {{ number_format($detail['points'] ?? 0, 1) }} pts
                                                </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="modal fade" id="scoringModal" tabindex="-1" aria-labelledby="scoringModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scoringModalLabel">How {{ strtolower($boards[$board]) }} scores are tallied</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Each action earns a base number of points. Every point is then multiplied by a
                        <strong>recency factor</strong> that decays over time, so recent work counts for more.
                    </p>

                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Action</th>
                                <th class="text-end" style="width: 110px">Base points</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($scoring['weights'] as $action => $points)
                                <tr>
                                    <td>
                                        {{ $scoring['labels'][$action] ?? \Illuminate\Support\Str::headline($action) }}
                                        @if (in_array($action, $scoring['impactActions'], true))
                                            <span class="badge text-bg-light border ms-1" title="Scaled by the size of the change">× impact</span>
                                        @endif
                                        @if ($action === 'pr_claimed')
                                            <span class="badge text-bg-light border ms-1" title="Scaled by how long the PR sat unclaimed">× staleness</span>
                                        @endif
                                    </td>
                                    <td class="text-end"><code>{{ $points }}</code></td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-muted">No point values configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <h6 class="mt-4">Multipliers</h6>
                    <ul class="small mb-0">
                        <li class="mb-2">
                            <strong>Bigger changes count for more.</strong> Anything tagged
                            <span class="badge text-bg-light border">× impact</span> is boosted by how much code it
                            touched — from {{ $scoring['impact']['min'] }}× for a small tweak up to
                            {{ $scoring['impact']['max'] }}× for a large change.
                        </li>
                        <li class="mb-2">
                            <strong>Recent work counts for more.</strong> A contribution's value fades over time:
                            it's worth half as much after {{ $scoring['recency']['half_life_days'] }} days, and anything
                            older than {{ $scoring['recency']['window_days'] }} days no longer counts.
                        </li>
                        @if ($board === 'maintainer')
                            <li class="mb-2">
                                <strong>Picking up neglected work counts for more.</strong> Claiming a PR that's been
                                waiting for review is boosted by how long it sat untouched.
                            </li>
                        @endif
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection
