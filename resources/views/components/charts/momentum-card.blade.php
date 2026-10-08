@props([
    'id',          // canvas id
    'title',       // 'Pull requests' | 'Issues'
    'noun',        // 'PRs' | 'issues' — tooltip text
    'stats',       // month (yyyy-MM) => ['opened' => int, 'closed' => int], full history; non-null
    'historyUrl',  // the dataset's By Month page
])

@php
    use Carbon\Carbon;

    // Twelve calendar months ending with the current one; a month with no bucket counts as zero.
    $start = Carbon::now()->startOfMonth()->subMonths(11);
    $months = [];
    for ($i = 0; $i < 12; $i++) {
        $month = $start->copy()->addMonths($i);
        $counts = $stats[$month->format('Y-m')] ?? ['opened' => 0, 'closed' => 0];
        $months[] = [
            'label' => $month->format('M Y'),
            'short' => $month->format('M'),
            'opened' => (int) $counts['opened'],
            'closed' => (int) $counts['closed'],
        ];
    }

    $opened = array_sum(array_column($months, 'opened'));
    $closed = array_sum(array_column($months, 'closed'));
    $allTimeOpened = array_sum(array_column($stats, 'opened'));
    $firstMonth = array_key_first($stats);
    $since = $firstMonth ? Carbon::createFromFormat('!Y-m', $firstMonth)->format('M Y') : null;
@endphp

<div class="chart-card chart-card--momentum">
    <div class="chart-card-body">
        <div class="chart-card-head">
            <h3 class="chart-card-title">{{ $title }}</h3>
            <span class="chart-card-range">Last 12 months</span>
        </div>
        {{-- The totals are the legend; the swatches key the bar colours. --}}
        <div class="chart-totals">
            <span class="chart-total">
                <span class="chart-swatch chart-swatch--opened" aria-hidden="true"></span>
                <span class="chart-total-num">{{ number_format($opened) }}</span>
                <span class="chart-total-word">opened</span>
            </span>
            <span class="chart-total">
                <span class="chart-swatch chart-swatch--closed" aria-hidden="true"></span>
                <span class="chart-total-num">{{ number_format($closed) }}</span>
                <span class="chart-total-word">closed</span>
            </span>
        </div>
        <div class="chart-card-canvas chart-card-canvas--momentum">
            <canvas id="{{ $id }}" role="img"
                    aria-label="{{ $title }} opened and closed per month, last 12 months: {{ number_format($opened) }} opened, {{ number_format($closed) }} closed"
                    data-noun="{{ $noun }}"
                    data-months="{{ json_encode($months, JSON_THROW_ON_ERROR) }}"></canvas>
        </div>
        {{-- Month labels in HTML (Chart.js can't letter-space); columns line up with the plot's 34px y gutter. --}}
        <div class="chart-months" aria-hidden="true">
            @foreach ($months as $month)
                <span>{{ $month['short'] }}</span>
            @endforeach
        </div>
    </div>
    <a class="chart-card-foot" href="{{ $historyUrl }}" aria-label="Full history: {{ $title }} by month">
        @if ($since)
            <span><b>{{ number_format($allTimeOpened) }}</b> opened since {{ $since }}</span>
        @endif
        <span class="chart-card-foot-link">Full history →</span>
    </a>
</div>
