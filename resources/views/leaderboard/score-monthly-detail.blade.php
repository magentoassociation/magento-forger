@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row mb-3">
            <div class="col-12">
                <h2>
                    <img src="{{ $profile?->avatar_url ?: 'https://github.com/'.$login.'.png?size=96' }}"
                         alt="" width="36" height="36" class="rounded-circle me-2 align-middle" onerror="this.style.display='none'">
                    {{ $profile?->name ?: $login }}
                    @if ($profile?->name)
                        <span class="text-muted fs-6">{{ '@'.$login }}</span>
                    @endif
                </h2>
                <p class="text-muted">
                    Every scored contribution in {{ $monthLabel }} behind this month's score &mdash;
                    <strong>{{ number_format($total, 1) }}</strong> pts total (impact-weighted, no recency decay).
                </p>
                <a href="{{ route('scores.monthly', ['board' => $board, 'ym' => $ym]) }}" class="btn btn-sm btn-outline-secondary mb-2">
                    &larr; Back to {{ $monthLabel }} board
                </a>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="alert alert-info">No scored contributions in {{ $monthLabel }} for <code>{{ $login }}</code>.</div>
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
                                <td>{{ $row->action }}</td>
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
