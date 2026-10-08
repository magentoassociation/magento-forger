@include('components.charts._bar-chart')

@push('scripts')
    <script>
        // Null when that aggregation failed; its card is not rendered either.
        const prStats = {!! json_encode($prStats, JSON_THROW_ON_ERROR) !!};
        const issueStats = {!! json_encode($issueStats, JSON_THROW_ON_ERROR) !!};

        // Monthly pairs over 140+ months are under 2px each in a half-width card, so the
        // charts sum calendar quarters. A partial first or last quarter is charted as-is.
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

        // Smallest 1, 2, 2.5 or 5 × 10ⁿ step that fits the max in three steps.
        const niceStep = (max) => {
            const raw = Math.max(max, 1) / 3;
            const exp = Math.pow(10, Math.floor(Math.log10(raw)));

            return [1, 2, 2.5, 5, 10].map(m => m * exp).find(step => step >= raw);
        };

        const fmt = (n) => n.toLocaleString('en-US');

        const momentumChart = (id, stats, noun) => {
            if (!stats) {
                return;
            }

            const quarters = toQuarters(stats);
            const step = niceStep(Math.max(0, ...quarters.flatMap(q => [q.opened, q.closed])));
            // One label per even year, at its Q1 bar.
            const yearTicks = new Set(quarters.flatMap((q, i) => (q.quarter === 1 && q.year % 2 === 0 ? [i] : [])));
            const mono = { family: "'Martian Mono', ui-monospace, monospace", size: 9.5 };

            forgerBarChart(id, quarters.map(q => `${q.year}-Q${q.quarter}`), [
                { label: 'Opened', data: quarters.map(q => q.opened), color: '#ee6524' },
                { label: 'Closed', data: quarters.map(q => q.closed), color: '#15171b' },
            ], (config) => {
                const hover = ['rgba(238, 101, 36, .6)', 'rgba(21, 23, 27, .6)'];
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
                                font: mono,
                                callback: (value) => (value === 0 ? '' : fmt(value)),
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

                                    return `${fmt(q.opened)} ${noun} opened, ${fmt(q.closed)} closed`;
                                },
                            },
                        },
                    },
                });
            });
        };

        document.addEventListener('DOMContentLoaded', () => {
            momentumChart('prChart', prStats, 'PRs');
            momentumChart('issueChart', issueStats, 'issues');
        });
    </script>
@endpush
