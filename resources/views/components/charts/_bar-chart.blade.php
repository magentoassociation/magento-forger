{{-- Shared Chart.js bar chart in the site palette. Include before pushing a script that calls forgerBarChart(). --}}
@once
    @push('scripts')
        <script>
            /**
             * Draw a monthly bar chart into the canvas with the given id; no-op when the canvas is absent.
             *
             * @param {string} id
             * @param {string[]} labels  yyyy-MM month keys
             * @param {{label: string, data: (number|null)[], color: string}[]} datasets
             * @param {function(object): void} [customize]  Mutates the Chart.js config before drawing.
             */
            window.forgerBarChart = function (id, labels, datasets, customize) {
                const el = document.getElementById(id);
                if (!el) {
                    return;
                }

                // Orange accent and ink, hairline grid, mono ticks.
                const mono = { family: "'Martian Mono', ui-monospace, monospace", size: 10.5 };

                const config = {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: datasets.map(d => ({
                            label: d.label,
                            data: d.data,
                            backgroundColor: d.color,
                            borderRadius: 3,
                        })),
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { color: '#e6e7ea' },
                                ticks: { color: '#5d636c', font: mono }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: '#e6e7ea' },
                                border: { display: false },
                                ticks: { precision: 0, color: '#5d636c', font: mono }
                            }
                        },
                        plugins: {
                            legend: {
                                // A lone series is named by the card heading.
                                display: datasets.length > 1,
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    boxWidth: 10,
                                    boxHeight: 10,
                                    color: '#3c4148',
                                    font: { family: "'Libre Franklin', system-ui, sans-serif", size: 13 },
                                }
                            }
                        }
                    }
                };

                if (customize) {
                    customize(config);
                }

                new Chart(el.getContext('2d'), config);
            };
        </script>
    @endpush
@endonce
