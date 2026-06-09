<script>
    const labels = {!! json_encode(array_keys($monthlyStats), JSON_THROW_ON_ERROR) !!};

    const prOpenedData = {!! json_encode(array_column($monthlyStats, 'pr_opened'), JSON_THROW_ON_ERROR) !!};
    const prClosedData = {!! json_encode(array_column($monthlyStats, 'pr_closed'), JSON_THROW_ON_ERROR) !!};

    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('prChart');
        if (!el) {
            return;
        }

        new Chart(el.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'PRs Opened',
                        data: prOpenedData,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    },
                    {
                        label: 'PRs Closed',
                        data: prClosedData,
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                        }
                    }
                }
            }
        });
    });
</script>