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

    // Oldest year first, current year last — the timeline reads left (old) to right (now).
    $years = array_values($rows);
    usort($years, fn ($a, $b) => (int) $a['year'] <=> (int) $b['year']);

    // Largest monthly count across every loaded year — the square-root scale's denominator.
    // Scrolling never rescales a bar, so the denominator is fixed for the whole page.
    $maxMonthly = 0;
    foreach ($years as $year) {
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

    // Height ratio sqrt(n/max) in 0..1; CSS multiplies by the per-breakpoint factor (118 / 82).
    $barRatio = fn (int $n): string => number_format(sqrt($n / $maxMonthly), 4, '.', '');

    // "issues"/"PRs" but "issue"/"PR" when the count is exactly 1.
    $nounFor = fn (int $n) => $n === 1 ? substr($noun, 0, -1) : $noun;

    // Bars/tiles link to GitHub's own filtered issue/PR search by design — no internal per-month
    // list page is maintained (confirmed decision, not drift).
    $ghUrl = fn ($start, $end) => 'https://github.com/magento/magento2/'.$linkPath
        .'?q=is%3A'.$qType.'%20state%3Aopen%20updated%3A'.$start.'..'.$end;

    // Index each year's months by integer month number for slot lookup.
    $byMonth = [];
    foreach ($years as $year) {
        $map = [];
        foreach ($year['months'] as $m) {
            $map[(int) $m['month_number']] = $m;
        }
        $byMonth[(int) $year['year']] = $map;
    }

    $pickerMonths = $byMonth[$currentYear] ?? [];
@endphp

<x-info-text :info="$info" />

<div class="bm" data-bm>
    <div class="bm-range" data-bm-range>
        <span class="bm-range-span" data-bm-span></span>
        <div class="bm-range-btns" data-bm-btns hidden>
            <button type="button" class="bm-nav" data-bm-earlier>&lsaquo; Earlier</button>
            <button type="button" class="bm-nav" data-bm-later>Later &rsaquo;</button>
        </div>
    </div>

    <div class="bm-scroll" data-bm-scroll>
        <ul class="bm-timeline" aria-label="Open {{ $noun }} per month, all years">
            @foreach ($years as $year)
                @php $y = (int) $year['year']; @endphp
                <li class="bm-year" data-year="{{ $y }}">
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
                                    <span class="bm-bar bm-bar--zero" aria-label="{{ $label }}" title="{{ $label }}"></span>
                                @else
                                    <a class="bm-bar" href="{{ $ghUrl($data['start'], $data['end']) }}"
                                       target="magentoForgerGitHub"
                                       style="--bh: {{ $barRatio($count) }}; background: {{ $bucketFill($count) }};"
                                       title="{{ $label }}" aria-label="{{ $label }}"></a>
                                @endif
                            </li>
                        @endfor
                    </ul>
                    <div class="bm-year-label">
                        <span class="bm-year-num">{{ $year['year'] }}</span>
                        <span class="bm-year-total">{{ number_format((int) $year['total']).' '.$nounFor((int) $year['total']) }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    <p class="bm-caption bm-caption--pointer">
        Each bar is one month; height is the number of open {{ $noun }}, on a square-root scale so
        small months stay visible. Hover for the exact count, click to open that month. Earlier years
        scroll in from the left, back to the oldest year with anything still open.
    </p>
    <p class="bm-caption bm-caption--touch">
        Tap for the exact count and that month's list. Swipe right for earlier years.
    </p>
</div>

<div class="bm-picker">
    <div class="bm-picker-head">
        <h2 class="bm-picker-title">Pick a month</h2>
        <span class="bm-picker-year">{{ $currentYear }}</span>
    </div>
    <div class="bm-grid">
        @for ($m = 1; $m <= 12; $m++)
            @php
                $isFuture = $m > $currentMonth;
                $data = $pickerMonths[$m] ?? null;
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
</div>

@once
    @push('scripts')
        <script>
            (function () {
                var MIN = 118; // minimum year-block width; 12 bars then stay >= 8px

                function layout(root) {
                    var scroll = root.querySelector('[data-bm-scroll]');
                    var years = Array.prototype.slice.call(root.querySelectorAll('.bm-year'));
                    if (!scroll || !years.length) return;

                    var narrow = window.matchMedia('(max-width: 575.98px)').matches;
                    var gap = narrow ? 10 : 14;
                    var W = scroll.clientWidth;
                    var loaded = years.length;

                    var visible = Math.floor((W + gap) / (MIN + gap));
                    visible = Math.max(1, Math.min(loaded, visible));
                    var blockW = (W - gap * (visible - 1)) / visible;

                    years.forEach(function (y) { y.style.width = blockW + 'px'; });

                    var scrollable = visible < loaded;
                    var btns = root.querySelector('[data-bm-btns]');
                    if (btns) btns.hidden = !scrollable;

                    root._bm = { scroll: scroll, years: years, gap: gap, blockW: blockW, visible: visible, scrollable: scrollable };

                    if (scrollable) {
                        scroll.scrollLeft = scroll.scrollWidth; // open at the current year, right edge
                    } else {
                        scroll.scrollLeft = 0;
                    }
                    updateRange(root);
                }

                function updateRange(root) {
                    var s = root._bm;
                    if (!s) return;
                    var span = root.querySelector('[data-bm-span]');
                    var earlier = root.querySelector('[data-bm-earlier]');
                    var later = root.querySelector('[data-bm-later]');
                    var step = s.blockW + s.gap;

                    var first = Math.round(s.scroll.scrollLeft / step);
                    first = Math.max(0, Math.min(s.years.length - s.visible, first));
                    var last = Math.min(s.years.length - 1, first + s.visible - 1);

                    if (span) {
                        var text = first === last
                            ? s.years[first].dataset.year
                            : s.years[first].dataset.year + ' – ' + s.years[last].dataset.year;
                        if (first > 0) text += ' · back to ' + s.years[0].dataset.year;
                        span.textContent = text;
                    }

                    var max = s.scroll.scrollWidth - s.scroll.clientWidth;
                    if (earlier) earlier.disabled = s.scroll.scrollLeft <= 1;
                    if (later) later.disabled = s.scroll.scrollLeft >= max - 1;
                }

                function wire(root) {
                    var scroll = root.querySelector('[data-bm-scroll]');
                    var earlier = root.querySelector('[data-bm-earlier]');
                    var later = root.querySelector('[data-bm-later]');

                    function nudge(dir) {
                        if (!root._bm) return;
                        scroll.scrollBy({ left: dir * (root._bm.blockW + root._bm.gap), behavior: 'smooth' });
                    }
                    if (earlier) earlier.addEventListener('click', function () { nudge(-1); });
                    if (later) later.addEventListener('click', function () { nudge(1); });

                    var ticking = false;
                    scroll.addEventListener('scroll', function () {
                        if (ticking) return;
                        ticking = true;
                        window.requestAnimationFrame(function () { updateRange(root); ticking = false; });
                    });

                    var rt;
                    window.addEventListener('resize', function () {
                        clearTimeout(rt);
                        rt = setTimeout(function () { layout(root); }, 120);
                    });

                    layout(root);
                }

                document.querySelectorAll('[data-bm]').forEach(wire);
            })();
        </script>
    @endpush
@endonce
