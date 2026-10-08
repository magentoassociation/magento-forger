@props([
    'stats',  // month (yyyy-MM) => ['opened' => int, 'closed' => int]; null or empty hides the block
    'title',  // 'Issues' | 'Pull requests'
    'noun',   // 'issues' | 'PRs'
])

{{-- By Month "Opened and closed, all time" (spec README-issues-prs-by-month.md §4): quarterly bars over the full history. --}}
@if (! empty($stats))
    @php

        $first = \Carbon\Carbon::createFromFormat('!Y-m', array_key_first($stats));
        $last = \Carbon\Carbon::createFromFormat('!Y-m', array_key_last($stats));
        $opened = array_sum(array_column($stats, 'opened'));
        $closed = array_sum(array_column($stats, 'closed'));
    @endphp

    <section class="bm-alltime">
        <h2 class="bm-alltime-title">Opened and closed, all time</h2>
        <p class="bm-alltime-sub">All {{ $noun }} opened and closed since the first month, by quarter.</p>
        <div class="chart-card chart-card--alltime">
            <div class="chart-card-head">
                <h3 class="chart-card-title">{{ $title }}</h3>
                <span class="chart-card-range">{{ $first->format('M Y') }} – {{ $last->format('M Y') }}</span>
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
            <div class="chart-card-canvas chart-card-canvas--alltime">
                <canvas id="allTimeChart" role="img"
                        aria-label="{{ $title }} opened and closed per quarter, {{ $first->format('M Y') }} to {{ $last->format('M Y') }}: {{ number_format($opened) }} opened, {{ number_format($closed) }} closed"
                        data-noun="{{ $noun }}"
                        data-stats="{{ json_encode($stats, JSON_THROW_ON_ERROR) }}"></canvas>
            </div>
        </div>
    </section>

    @include('components.charts._bar-chart')

    @push('scripts')
        <script>
            (function () {
                // Monthly pairs over 140+ months are under 2px each, so the chart sums calendar
                // quarters. A partial first or last quarter is charted as-is.
                const toQuarters = (stats) => {
                    const quarters = new Map();
                    for (const [month, counts] of Object.entries(stats)) {
                        const [year, m] = month.split('-').map(Number);
                        const quarter = Math.ceil(m / 3);
                        const key = `${year}-${quarter}`;
                        const sum = quarters.get(key) ?? { year, quarter, opened: 0, closed: 0 };
                        sum.opened += counts.opened;
                        sum.closed += counts.closed;
                        quarters.set(key, sum);
                    }

                    return [...quarters.values()];
                };

                document.addEventListener('DOMContentLoaded', () => {
                    const canvas = document.getElementById('allTimeChart');
                    if (!canvas) {
                        return;
                    }

                    const quarters = toQuarters(JSON.parse(canvas.dataset.stats));
                    const noun = canvas.dataset.noun;
                    const step = forgerNiceStep(Math.max(0, ...quarters.flatMap(q => [q.opened, q.closed])));
                    // One label per even year, at its Q1 bar.
                    const yearTicks = new Set(quarters.flatMap((q, i) => (q.quarter === 1 && q.year % 2 === 0 ? [i] : [])));

                    forgerBarChart('allTimeChart', quarters.map(q => `${q.year}-Q${q.quarter}`), [
                        { label: 'Opened', data: quarters.map(q => q.opened), color: '#ee6524' },
                        { label: 'Closed', data: quarters.map(q => q.closed), color: '#9aa3ae' },
                    ], (config) => {
                        const hover = ['rgba(238, 101, 36, .6)', 'rgba(154, 163, 174, .6)'];
                        config.data.datasets.forEach((dataset, i) => Object.assign(dataset, {
                            borderRadius: 1,
                            categoryPercentage: 0.92,
                            barPercentage: 0.9,
                            hoverBackgroundColor: hover[i],
                        }));

                        Object.assign(config.options, {
                            // Headroom so the top y label, drawn above its gridline, isn't clipped.
                            layout: { padding: { top: 14 } },
                            interaction: { mode: 'index', intersect: false },
                            scales: {
                                x: {
                                    border: { color: '#c9ced4' },
                                    grid: {
                                        drawOnChartArea: false,
                                        tickLength: 5,
                                        tickColor: (ctx) => (yearTicks.has(ctx.index) ? '#c9ced4' : 'transparent'),
                                    },
                                    ticks: {
                                        autoSkip: false,
                                        maxRotation: 0,
                                        minRotation: 0,
                                        align: 'start',
                                        padding: 2,
                                        color: '#15171b',
                                        font: { family: "'Libre Franklin', system-ui, sans-serif", size: 12, weight: '700' },
                                        callback: (value, i) => (yearTicks.has(i) ? String(quarters[i].year) : ''),
                                    },
                                },
                                y: {
                                    min: 0,
                                    max: step * 3,
                                    border: { display: false },
                                    grid: { color: '#eef0f2', drawTicks: false },
                                    ticks: {
                                        stepSize: step,
                                        mirror: true,
                                        padding: 0,
                                        labelOffset: -7,
                                        showLabelBackdrop: true,
                                        backdropColor: '#ffffff',
                                        backdropPadding: 1,
                                        color: '#6b7178',
                                        font: { family: "'Martian Mono', ui-monospace, monospace", size: 9.5 },
                                        callback: (value) => (value === 0 ? '' : forgerFmt(value)),
                                    },
                                },
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    displayColors: false,
                                    filter: (item) => item.datasetIndex === 0,
                                    callbacks: {
                                        title: (items) => `Q${quarters[items[0].dataIndex].quarter} ${quarters[items[0].dataIndex].year}`,
                                        label: (item) => {
                                            const q = quarters[item.dataIndex];

                                            return `${forgerFmt(q.opened)} ${noun} opened, ${forgerFmt(q.closed)} closed`;
                                        },
                                    },
                                },
                            },
                        });
                    });
                });
            })();
        </script>
    @endpush
@endif
