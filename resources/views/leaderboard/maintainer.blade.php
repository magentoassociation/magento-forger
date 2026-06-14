@extends('layouts.app')

@section('content')
    @if ($dataMissing)
        <x-data-missing>
            No data found for this leaderboard. Run <code>ddev artisan sync:github:prs</code> to populate the index.
        </x-data-missing>
    @endif

    <div class="container">
        <div class="row mb-3">
            <div class="col-12">
                <h2>Maintainer Leaderboard</h2>
                <p class="text-muted">Top reviewers of the Magento 2 project</p>
            </div>
        </div>

        {{-- Metric tabs --}}
        <ul class="nav nav-tabs mb-4">
            @foreach ($metrics as $key => $meta)
                <li class="nav-item">
                    <a class="nav-link {{ $metric === $key ? 'active' : '' }}"
                       href="{{ route('maintainer.leaderboard.show', array_merge(['metric' => $key, 'period' => $period], $period === 'custom' ? ['from' => $from->toDateString(), 'to' => $to->toDateString()] : [])) }}">
                        {{ $meta['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- Date range filter --}}
        <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
            @foreach (['last-month' => 'Last Month', 'last-quarter' => 'Last Quarter', 'last-year' => 'Last Year'] as $key => $label)
                <a href="{{ route('maintainer.leaderboard.show', ['metric' => $metric, 'period' => $key]) }}"
                   class="btn btn-sm {{ $period === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $label }}
                </a>
            @endforeach

            <form method="GET" action="{{ route('maintainer.leaderboard.show', ['metric' => $metric]) }}" class="d-flex gap-2 align-items-center ms-2">
                <input type="hidden" name="period" value="custom">
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from->toDateString() }}" style="width:auto">
                <span class="text-muted">to</span>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to->toDateString() }}" style="width:auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">Apply</button>
            </form>
        </div>

        <p class="text-muted small mb-3">
            {{ number_format(collect($contributors)->sum('count')) }} {{ $metricLabel }} from {{ $from->toFormattedDateString() }} to {{ $to->toFormattedDateString() }}
        </p>

        {{-- Leaderboard table --}}
        @if (empty($contributors))
            <div class="alert alert-info">No contributions found for this period.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px">#</th>
                            <th>Maintainer</th>
                            <th class="text-end">{{ $metricLabel }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contributors as $i => $contributor)
                            <tr>
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td>
                                    <a href="https://github.com/{{ $contributor->login }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none fw-medium">
                                        {{ $contributor->login }}
                                    </a>
                                </td>
                                <td class="text-end">
                                    <a href="{{ $githubUrl($contributor->login) }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                        <span class="badge text-bg-success rounded-pill">{{ number_format($contributor->count) }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
