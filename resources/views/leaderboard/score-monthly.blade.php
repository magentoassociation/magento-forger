@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row mb-3">
            <div class="col-12">
                <p class="text-muted mb-1">
                    Ranked by activity in {{ $monthLabel }} — bigger changes count for more, with no recency decay.
                    Points come from {{ $scoring['scoredList'] }}.
                </p>
                <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#scoringModal">
                    How are scores tallied?
                </button>
            </div>
        </div>

        @include('leaderboard._tabs')

        <div class="mb-4 d-flex flex-wrap gap-2">
            @foreach ($months as $month)
                <a href="{{ route('scores.monthly', ['board' => $board, 'ym' => $month['ym']]) }}"
                   class="btn btn-sm {{ $month['active'] ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $month['label'] }}
                </a>
            @endforeach
        </div>

        @if ($entries->isEmpty())
            <div class="alert alert-info">
                No scored activity for {{ $monthLabel }}.
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
                                            <a href="{{ route('scores.monthly.detail', ['board' => $board, 'ym' => $ym, 'login' => $entry->login]) }}" class="btn btn-sm btn-outline-primary text-nowrap">
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

    @include('leaderboard._scoring-modal')
@endsection
