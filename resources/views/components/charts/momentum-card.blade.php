@props([
    'id',     // canvas id
    'title',  // 'Pull requests' | 'Issues'
    'stats',  // month (yyyy-MM) => ['opened' => int, 'closed' => int]; non-null
])

@php
    use Carbon\Carbon;

    $months = array_keys($stats);
    $opened = array_sum(array_column($stats, 'opened'));
    $closed = array_sum(array_column($stats, 'closed'));
    $range = $months === []
        ? null
        : Carbon::createFromFormat('!Y-m', reset($months))->format('M Y').' – '.Carbon::createFromFormat('!Y-m', end($months))->format('M Y');
    $ariaLabel = "{$title} opened and closed per quarter"
        .($range ? ', '.str_replace(' – ', ' to ', $range) : '')
        .': '.number_format($opened).' opened, '.number_format($closed).' closed';
@endphp

<div class="chart-card">
    <div class="chart-card-head">
        <h3 class="chart-card-title">{{ $title }}</h3>
        @if ($range)
            <span class="chart-card-range">{{ $range }}</span>
        @endif
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
        <canvas id="{{ $id }}" aria-label="{{ $ariaLabel }}" role="img"></canvas>
    </div>
</div>
