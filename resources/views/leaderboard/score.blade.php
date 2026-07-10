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
                No scores yet. Run <code>artisan leaderboard:compute</code> to populate.
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
                                    @if (($entry->active ?? true) === false)
                                        <span class="badge text-bg-secondary ms-1" title="No longer on the maintainer team">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @php($breakdownTitle = collect($entry->breakdown)->map(fn ($detail, $action) => e(\App\DataTransferObjects\Leaderboard\Action::labelFor($action)).' &mdash; '.number_format($detail['count'] ?? 0).'&times; &rarr; '.number_format($detail['points'] ?? 0, 1).' pts')->implode('<br>'))
                                    <span class="badge text-bg-success rounded-pill"
                                        @if (! empty($entry->breakdown)) data-bs-toggle="tooltip" data-bs-html="true" data-bs-custom-class="breakdown-tooltip" data-bs-title="{!! $breakdownTitle !!}" style="cursor: help;" @endif>
                                        {{ number_format($entry->score, 1) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-2 justify-content-end flex-nowrap">
                                        @if ($entry->score > 0)
                                            <a href="{{ route('scores.detail', ['board' => $board, 'login' => $entry->login]) }}" class="btn btn-sm btn-outline-primary text-nowrap">
                                                Details
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('leaderboard._scoring-modal')
@endsection

@push('scripts')
    <style>
        .breakdown-tooltip .tooltip-inner {
            max-width: none;
            white-space: nowrap;
        }
    </style>
    <script>
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
    </script>
@endpush
