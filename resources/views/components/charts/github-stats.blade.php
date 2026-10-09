{{-- Homepage Momentum charts: last 12 months, one pair per month. Data comes from each card's canvas. --}}
@include('components.charts._bar-chart')

@push('scripts')
    <script>
        (function () {
            const Y_GUTTER = 34; // keeps y labels off the bars; the HTML month row is inset to match
            const PAIR_GAP = 2;
            const narrow = window.matchMedia('(max-width: 991.98px)');

            // Size bars in pixels: 2px inside a pair, 8px between months (5px below lg).
            // A bar sits centred in its half-month slot, so the visible month gap is the
            // category gutter plus one slot-minus-bar gap.
            const applyGaps = (chart) => {
                const width = chart.chartArea ? chart.chartArea.width : 0;
                if (width <= 0) {
                    return;
                }
                const month = width / chart.data.labels.length;
                const inner = Math.max(month - (narrow.matches ? 5 : 8) + PAIR_GAP, PAIR_GAP * 2 + 2);
                const slot = inner / 2;
                chart.data.datasets.forEach(dataset => {
                    dataset.categoryPercentage = Math.min(inner / month, 1);
                    dataset.barPercentage = (slot - PAIR_GAP) / slot;
                });
            };

            const momentumChart = (canvas) => {
                const months = JSON.parse(canvas.dataset.months);
                const noun = canvas.dataset.noun;
                const step = forgerNiceStep(Math.max(0, ...months.flatMap(m => [m.opened, m.closed])));

                const chart = forgerBarChart(canvas.id, months.map(m => m.label), [
                    { label: 'Opened', data: months.map(m => m.opened), color: '#ee6524' },
                    { label: 'Closed', data: months.map(m => m.closed), color: '#9aa3ae' },
                ], (config) => {
                    const hover = ['rgba(238, 101, 36, .6)', 'rgba(154, 163, 174, .6)'];
                    config.data.datasets.forEach((dataset, i) => Object.assign(dataset, {
                        borderRadius: 1,
                        categoryPercentage: 0.8,
                        barPercentage: 0.92,
                        hoverBackgroundColor: hover[i],
                    }));

                    Object.assign(config.options, {
                        // Headroom so the top y label, drawn above its gridline, isn't clipped.
                        layout: { padding: { top: 10 } },
                        interaction: { mode: 'index', intersect: false },
                        onResize: (resized) => {
                            applyGaps(resized);
                            resized.update('none');
                        },
                        scales: {
                            // Month labels are HTML under the canvas; the axis is just the baseline.
                            x: {
                                border: { color: '#c9ced4' },
                                grid: { display: false },
                                ticks: { display: false },
                            },
                            y: {
                                min: 0,
                                max: step * 3,
                                afterFit: (scale) => {
                                    scale.width = Y_GUTTER;
                                },
                                border: { display: false },
                                grid: { color: '#eef0f2', drawTicks: false },
                                ticks: {
                                    stepSize: step,
                                    crossAlign: 'far',
                                    padding: 0,
                                    labelOffset: -6,
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
                                    title: (items) => months[items[0].dataIndex].label,
                                    label: (item) => {
                                        const m = months[item.dataIndex];

                                        return `${forgerFmt(m.opened)} ${noun} opened, ${forgerFmt(m.closed)} closed`;
                                    },
                                },
                            },
                        },
                    });
                });

                if (chart) {
                    applyGaps(chart);
                    chart.update('none');
                }
            };

            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.chart-card--momentum canvas').forEach(momentumChart);
            });
        })();
    </script>
@endpush
