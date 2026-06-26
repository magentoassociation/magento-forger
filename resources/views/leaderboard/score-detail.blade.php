@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row mb-3">
            <div class="col-12">
                <h2>{{ $login }} <small class="text-muted">— {{ $boards[$board] }} score detail</small></h2>
                <p class="text-muted">
                    Contributions in the last 12 months behind this score &mdash;
                    <strong>{{ number_format($total, 1) }}</strong> pts itemized.
                </p>
                <a href="{{ route('scores.show', ['board' => $board]) }}" class="btn btn-sm btn-outline-secondary mb-2">
                    &larr; Back to {{ $boards[$board] }} board
                </a>
            </div>
        </div>

        @if ($board === 'maintainer')
            <div class="alert alert-secondary small">
                Reviews are itemized below. Derived bonuses (claiming a stale PR, applying labels, approving a PR
                that later merged) contribute to the score but aren't listed individually.
            </div>
        @endif

        @if ($rows->isEmpty())
            <div class="alert alert-info">No itemized contributions in this window for <code>{{ $login }}</code>.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Action</th>
                            <th>Item</th>
                            <th>Date</th>
                            <th class="text-end">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><code>{{ $row->action }}</code></td>
                                <td>
                                    @if ($row->url)
                                        <a href="{{ $row->url }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                            {{ $row->title ?: $row->url }}
                                        </a>
                                    @else
                                        {{ $row->title }}
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $row->date->toFormattedDateString() }}</td>
                                <td class="text-end"><span class="badge text-bg-success rounded-pill">{{ number_format($row->points, 1) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
