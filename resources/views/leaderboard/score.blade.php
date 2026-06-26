@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row mb-3">
            <div class="col-12">
                <h2>{{ $boards[$board] }} Score</h2>
                <p class="text-muted">Weighted, impact-aware ranking over the last 12 months, with recency decay.</p>
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
                            <th class="text-end" style="width: 130px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $i => $entry)
                            <tr>
                                <td class="text-muted">{{ $entry->rank ?? $i + 1 }}</td>
                                <td>
                                    <a href="https://github.com/{{ $entry->login }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none fw-medium">
                                        {{ $entry->login }}
                                    </a>
                                </td>
                                <td class="text-end">
                                    <span class="badge text-bg-success rounded-pill">{{ number_format($entry->score, 1) }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('scores.detail', ['board' => $board, 'login' => $entry->login]) }}" class="btn btn-sm btn-outline-primary">
                                        Items
                                    </a>
                                    @if (! empty($entry->breakdown))
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#breakdown-{{ $i }}" aria-expanded="false">
                                            Breakdown
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if (! empty($entry->breakdown))
                                <tr class="collapse" id="breakdown-{{ $i }}">
                                    <td></td>
                                    <td colspan="3">
                                        <ul class="list-unstyled mb-0 small">
                                            @foreach ($entry->breakdown as $action => $detail)
                                                <li>
                                                    <code>{{ $action }}</code>
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
@endsection
