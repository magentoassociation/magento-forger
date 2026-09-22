@props([
    'rows',       // years array: [ ['year'=>int, 'total'=>int, 'months'=>[ ['month_number'=>'01', 'total'=>int, 'start'=>, 'end'=>], ... ]], ... ]
    'info',       // InfoText (title + paragraphs)
    'noun',       // 'issues' | 'PRs' — used verbatim in totals and hover text
    'linkPath',   // 'issues' | 'pulls'
    'qType',      // 'issue' | 'pr'
])

@php
    use Carbon\Carbon;

    $currentYear = (int) date('Y');
    $currentMonth = (int) date('n');

    // Largest monthly count across the whole page — the square-root scale's denominator.
    $maxMonthly = 0;
    foreach ($rows as $year) {
        foreach ($year['months'] as $m) {
            $maxMonthly = max($maxMonthly, (int) $m['total']);
        }
    }
    $maxMonthly = max($maxMonthly, 1);

    // Absolute volume buckets (README "Colour buckets").
    $bucketFill = function (int $n): string {
        return match (true) {
            $n >= 80 => '#f26322',
            $n >= 40 => '#f59058',
            $n >= 20 => '#f8bd96',
            $n >= 10 => '#fbddcb',
            $n >= 1 => '#fdf1ea',
            default => '#e6e8ea',
        };
    };

    // Bar height: sqrt(n/max)*118, floored at 3px for any non-zero month.
    $barHeight = function (int $n) use ($maxMonthly): int {
        return $n === 0 ? 2 : max(3, (int) round(sqrt($n / $maxMonthly) * 118));
    };

    // "issues"/"PRs" but "issue"/"PR" when the count is exactly 1.
    $nounFor = fn (int $n) => $n === 1 ? substr($noun, 0, -1) : $noun;

    $ghUrl = fn ($start, $end) => 'https://github.com/magento/magento2/'.$linkPath
        .'?q=is%3A'.$qType.'%20state%3Aopen%20updated%3A'.$start.'..'.$end;

    // Index each year's months by integer month number for slot lookup.
    $byMonth = [];
    foreach ($rows as $year) {
        $map = [];
        foreach ($year['months'] as $m) {
            $map[(int) $m['month_number']] = $m;
        }
        $byMonth[(int) $year['year']] = $map;
    }

    $pickerMonths = $byMonth[$currentYear] ?? [];
@endphp

<x-info-text :info="$info" />

<div class="bm-scroll">
<ul class="bm-timeline" aria-label="Open {{ $noun }} per month, all years">
    @foreach ($rows as $year)
        @php $y = (int) $year['year']; @endphp
        <li class="bm-year">
            <ul class="bm-bars">
                @for ($m = 1; $m <= 12; $m++)
                    @php
                        $isFuture = $y === $currentYear && $m > $currentMonth;
                        $data = $byMonth[$y][$m] ?? null;
                        $count = (int) ($data['total'] ?? 0);
                        $label = Carbon::create(2020, $m, 1)->format('M').' '.$y.' — '.number_format($count).' '.$nounFor($count);
                    @endphp
                    <li class="bm-slot">
                        @if ($isFuture)
                            {{-- future month: transparent slot, no bar --}}
                        @elseif ($count === 0)
                            <span class="bm-bar bm-bar--zero" aria-label="{{ $label }}"></span>
                        @else
                            <a class="bm-bar" href="{{ $ghUrl($data['start'], $data['end']) }}"
                               target="magentoForgerGitHub"
                               style="height: {{ $barHeight($count) }}px; background: {{ $bucketFill($count) }};"
                               title="{{ $label }}" aria-label="{{ $label }}"></a>
                        @endif
                    </li>
                @endfor
            </ul>
        </li>
    @endforeach
</ul>

<div class="bm-years" role="tablist" aria-label="Choose a year for the month picker">
    @foreach ($rows as $year)
        @php $y = (int) $year['year']; @endphp
        <button type="button" class="bm-year-label{{ $y === $currentYear ? ' active' : '' }}"
                role="tab" data-year="{{ $y }}" aria-selected="{{ $y === $currentYear ? 'true' : 'false' }}"
                aria-controls="bm-grid-{{ $y }}">
            <span class="bm-year-num">{{ $year['year'] }}</span>
            <span class="bm-year-total">{{ number_format($year['total']).' '.$nounFor((int) $year['total']) }}</span>
        </button>
    @endforeach
</div>
</div>{{-- /.bm-scroll --}}

<p class="bm-caption">
    Each bar is one month; height is the number of open {{ $noun }}, on a square-root scale so
    small months stay visible. Hover for the exact count, click to open that month.
</p>

<div class="bm-picker">
    <div class="bm-picker-head">
        <h2 class="bm-picker-title">Pick a month</h2>
        <span class="bm-picker-year" data-bm-picker-year>{{ $currentYear }}</span>
    </div>
    @foreach ($rows as $year)
        @php $y = (int) $year['year']; @endphp
        <div class="bm-grid" id="bm-grid-{{ $y }}" data-year="{{ $y }}" @unless ($y === $currentYear) hidden @endunless>
            @for ($m = 1; $m <= 12; $m++)
                @php
                    $isFuture = $y === $currentYear && $m > $currentMonth;
                    $data = $byMonth[$y][$m] ?? null;
                    $count = (int) ($data['total'] ?? 0);
                    $mon = strtoupper(Carbon::create(2020, $m, 1)->format('M'));
                @endphp
                @if (! $isFuture && $count > 0)
                    <a class="bm-tile" href="{{ $ghUrl($data['start'], $data['end']) }}" target="magentoForgerGitHub">
                        <span class="bm-tile-mon">{{ $mon }}</span>
                        <span class="bm-tile-count">{{ number_format($count) }}</span>
                    </a>
                @else
                    <span class="bm-tile bm-tile--empty">
                        <span class="bm-tile-mon">{{ $mon }}</span>
                        <span class="bm-tile-count">{{ $isFuture ? '—' : '0' }}</span>
                    </span>
                @endif
            @endfor
        </div>
    @endforeach
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (e) {
                const tab = e.target.closest('.bm-year-label');
                if (! tab) return;
                const picker = tab.closest('.bm-scroll')?.parentElement;
                const year = tab.dataset.year;
                if (! picker) return;

                picker.querySelectorAll('.bm-year-label').forEach(function (t) {
                    const on = t === tab;
                    t.classList.toggle('active', on);
                    t.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                picker.querySelectorAll('.bm-grid').forEach(function (g) {
                    g.hidden = g.dataset.year !== year;
                });
                const label = picker.querySelector('[data-bm-picker-year]');
                if (label) label.textContent = year;
            });
        </script>
    @endpush
@endonce
