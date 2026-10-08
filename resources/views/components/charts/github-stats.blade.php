@include('components.charts._bar-chart')

@push('scripts')
    <script>
        // Null when that aggregation failed; its canvas is not rendered either.
        const prStats = {!! json_encode($prStats, JSON_THROW_ON_ERROR) !!};
        const issueStats = {!! json_encode($issueStats, JSON_THROW_ON_ERROR) !!};

        const openedClosed = (id, stats, noun) => {
            if (!stats) {
                return;
            }

            const months = Object.keys(stats);
            forgerBarChart(id, months, [
                { label: `${noun} Opened`, data: months.map(m => stats[m].opened), color: '#ee6524' },
                { label: `${noun} Closed`, data: months.map(m => stats[m].closed), color: '#15171b' },
            ]);
        };

        document.addEventListener('DOMContentLoaded', () => {
            openedClosed('prChart', prStats, 'PRs');
            openedClosed('issueChart', issueStats, 'Issues');
        });
    </script>
@endpush
