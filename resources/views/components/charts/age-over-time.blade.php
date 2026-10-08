@props([
    'stats',  // month (yyyy-MM) => average days open at close (null for a month with no data), null or empty hides the chart
    'noun',   // 'issues' | 'PRs'
])

@if (! empty($stats))
    <div class="bm-age">
        <h2 class="bm-picker-title">Average age at close</h2>
        <p class="bm-age-sub">Days from open to close, averaged over the {{ $noun }} closed each month.</p>
        <div class="chart-card">
            <div class="chart-card-canvas">
                <canvas id="ageChart" aria-label="Average age in days of {{ $noun }} closed each month" role="img"></canvas>
            </div>
        </div>
    </div>

    @include('components.charts._bar-chart')

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const ageStats = {!! json_encode($stats, JSON_THROW_ON_ERROR) !!};
                forgerBarChart('ageChart', Object.keys(ageStats), [
                    { label: 'Avg days open', data: Object.values(ageStats), color: '#ee6524' },
                ]);
            });
        </script>
    @endpush
@endif
