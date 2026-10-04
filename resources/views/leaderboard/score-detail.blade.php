@extends('layouts.app')

@php
    use Carbon\Carbon;

    $name = $profile?->name ?: $login;
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $initials = collect($words)->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('')
        ?: mb_strtoupper(mb_substr($name, 0, 2));

    // One template for the rolling (12-month) and monthly detail pages; $ym is set on monthly.
    $windowText = $ym ? 'in '.Carbon::createFromFormat('!Y-m', $ym)->format('F Y') : 'in the last 12 months';
    $monthFull = $activeMonth && ! $ym ? Carbon::createFromFormat('!Y-m', $activeMonth)->format('F Y') : null;
    $scopeText = $monthFull ? 'in '.$monthFull : $windowText;
    $monthNote = $ym ? ' — impact-weighted, no recency decay' : '';

    // Monthly pages move between months; rolling pages filter by ?month=.
    $monthUrl = fn (?string $month): string => $ym
        ? route('leaderboard.monthly.detail', ['board' => $board, 'ym' => $month, 'login' => $login]).(request()->only(['view', 'sort']) ? '?'.http_build_query(request()->only(['view', 'sort'])) : '')
        : request()->fullUrlWithQuery(['month' => $month, 'group' => null]);

    $listPage = 25;
@endphp

@section('content')
    @include('leaderboard._detail-header', ['scoreLabel' => $scoreLabel])

    <div class="container mx-auto lb lb-detail">
        @if ($zero)
            <p class="lb-d-intro">{{ $board === 'maintainer'
                ? 'No maintainer activity scored '.$windowText.'. Reviews and merges you complete from here will show up on this page, grouped by what earned the points.'
                : 'No scored contributions '.$windowText.'. PRs and issues you open from here will show up on this page, grouped by what earned the points.' }}
                <button type="button" class="lb-tallied" data-bs-toggle="modal" data-bs-target="#scoringModal">How scoring works</button></p>
        @elseif ($view === 'list')
            <p class="lb-d-intro">Every scored contribution {{ $scopeText }} in one list{{ $monthNote }}. The points column sums to the grand total.
                <button type="button" class="lb-tallied" data-bs-toggle="modal" data-bs-target="#scoringModal">How scoring works</button></p>
        @else
            <p class="lb-d-intro">Every scored contribution {{ $scopeText }}, grouped by what earned the points{{ $monthNote }}. Each group's points sum to the grand total.
                <button type="button" class="lb-tallied" data-bs-toggle="modal" data-bs-target="#scoringModal">How scoring works</button></p>
        @endif

        {{-- View toggle + month chips — suppressed on the zero-state (nothing to group/list/filter). --}}
        @unless ($zero)
            <div class="lb-d-controls">
                <span class="lb-d-toggle">
                    @if ($view === 'grouped')
                        <span class="active" aria-current="page">Grouped</span>
                        <a href="{{ request()->fullUrlWithQuery(['view' => 'list', 'group' => null]) }}">List</a>
                    @else
                        <a href="{{ request()->fullUrlWithQuery(['view' => null, 'group' => null]) }}">Grouped</a>
                        <span class="active" aria-current="page">List</span>
                    @endif
                </span>
            </div>

            <div class="lb-months">
                @unless ($ym)
                    <a href="{{ $monthUrl(null) }}" class="lb-month {{ $activeMonth ? '' : 'active' }}">All</a>
                @endunless
                @foreach ($months as $month)
                    <a href="{{ $monthUrl($month) }}"
                       class="lb-month {{ $activeMonth === $month ? 'active' : '' }}">{{ Carbon::createFromFormat('!Y-m', $month)->format('M Y') }}</a>
                @endforeach
            </div>
        @endunless

        @if ($zero)
            {{-- #17b/#17c: zero-score state — the scoring rules with zeros in them, not a warning. --}}
            <div class="lb-d-empty">
                <div class="lb-d-empty-head">What scores on this board</div>
                @foreach ($scoringGroups as $groupName)
                    <div class="lb-d-empty-row">
                        <span class="lb-d-empty-name">{{ $groupName }}</span>
                        <span class="lb-d-empty-count">0 items</span>
                        <span class="lb-d-empty-pts">0.0</span>
                    </div>
                @endforeach
                <div class="lb-d-empty-foot">
                    <span class="lb-d-empty-hint">The groups above are the ones that earn {{ $board }} points. Each fills in as you go.</span>
                    <a href="{{ $cta['url'] }}" target="_blank" rel="noopener" class="lb-d-empty-cta">{{ $cta['label'] }}</a>
                </div>
            </div>

        @elseif ($groups->isEmpty())
            {{-- A month chip with nothing in it: a plain line, not the zero-score panel. --}}
            <p class="lb-d-none">Nothing scored {{ $scopeText }}.</p>

        @elseif ($view === 'list')
            {{-- #18b: group tiles + one sortable, paginated list --}}
            <div class="lb-a-stats {{ $board === 'maintainer' ? 'lb-a-stats--3' : '' }}">
                @foreach ($groups as $group)
                    <div class="lb-a-card">
                        <span class="lb-a-card-label">{{ $group->name }}</span>
                        <div class="lb-a-card-val">
                            <span class="lb-a-card-pts">{{ number_format($group->total, 1) }}</span>
                            <span class="lb-a-card-count">×{{ number_format($group->count) }}</span>
                        </div>
                        <span class="lb-a-bar">
                            <span class="lb-a-bar-fill" style="width: {{ round($group->total / $maxTotal * 100, 2) }}%"></span>
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="lb-a-list">
                <script>document.currentScript.parentNode.classList.add('js');</script>
                {{-- Sort headers are buttons in a GET form; Points is the default and carries no param. --}}
                <form method="get" class="lb-a-head">
                    @foreach (request()->except(['sort', 'group']) as $key => $value)
                        @if (is_string($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <h2 class="lb-a-title">Scored contributions</h2>
                    <button type="submit" name="sort" value="type" class="lb-a-sort lb-a-sort--type {{ $sort === 'type' ? 'active' : '' }}" aria-pressed="{{ $sort === 'type' ? 'true' : 'false' }}">Type</button>
                    <button type="submit" name="sort" value="date" class="lb-a-sort {{ $sort === 'date' ? 'active' : '' }}" aria-pressed="{{ $sort === 'date' ? 'true' : 'false' }}">Date</button>
                    <button type="submit" class="lb-a-sort lb-a-sort--right {{ $sort === 'points' ? 'active' : '' }}" aria-pressed="{{ $sort === 'points' ? 'true' : 'false' }}">Points</button>
                </form>
                @foreach ($flat as $row)
                    @if ($row->url)
                        <a href="{{ $row->url }}" target="_blank" rel="noopener" title="{{ $row->title }}" class="lb-a-row">
                    @else
                        <div class="lb-a-row" title="{{ $row->title }}" tabindex="-1">
                    @endif
                            <span class="lb-d-row-title">{{ $row->title }}</span>
                            <span class="lb-a-chip">{{ $row->tag }}</span>
                            <span class="lb-d-row-date">{{ $row->date?->format('j M Y') }}</span>
                            @include('leaderboard._detail-points', ['row' => $row])
                    @if ($row->url)
                        </a>
                    @else
                        </div>
                    @endif
                @endforeach

                <div class="lb-pager">
                    <button type="button" class="lb-more" hidden>Show 25 more</button>
                    <span class="lb-count-stmt"></span>
                </div>
                <span class="lb-live visually-hidden" aria-live="polite"></span>
                <script>
                (function (list) {
                    var PAGE = {{ $listPage }};
                    var rows = Array.prototype.slice.call(list.querySelectorAll('.lb-a-row'));
                    var more = list.querySelector('.lb-more');
                    var count = list.querySelector('.lb-count-stmt');
                    var live = list.querySelector('.lb-live');
                    var shown = 0;
                    function fmt(n) { return n.toLocaleString('en-US'); }
                    function show(depth) {
                        shown = Math.min(depth, rows.length);
                        rows.forEach(function (row, i) { row.classList.toggle('is-beyond', i >= shown); });
                        count.textContent = shown >= rows.length ? 'Showing all ' + fmt(rows.length) : 'Showing 1–' + fmt(shown) + ' of ' + fmt(rows.length);
                        more.hidden = shown >= rows.length;
                    }
                    show(PAGE);
                    more.addEventListener('click', function () {
                        var before = shown;
                        show(shown + PAGE);
                        if (rows[before]) { rows[before].focus({ preventScroll: true }); }
                        live.textContent = '25 more shown. ' + count.textContent + '.';
                    });
                })(document.currentScript.parentNode);
                </script>
            </div>

        @elseif ($activeGroup)
            {{-- #9b: single filtered group --}}
            <a href="{{ request()->fullUrlWithQuery(['group' => null]) }}" class="lb-d-back">← All contributions</a>
            <div class="lb-d-group">
                <div class="lb-d-grouphead">
                    <h2 class="lb-d-group-name lb-d-group-name--single">{{ $activeGroup->name }}</h2>
                    <span class="lb-d-group-count">{{ number_format($activeGroup->count) }} {{ $activeGroup->count === 1 ? 'item' : 'items' }}</span>
                    <span class="lb-d-group-total">{{ number_format($activeGroup->total, 1) }}</span>
                </div>
                @foreach ($activeGroup->rows as $row)
                    @include('leaderboard._detail-row', ['row' => $row])
                @endforeach
            </div>

        @else
            {{-- #9b: all groups, preview + Show all --}}
            @foreach ($groups as $group)
                <div class="lb-d-group">
                    <div class="lb-d-grouphead">
                        <h2 class="lb-d-group-name">{{ $group->name }}</h2>
                        <span class="lb-d-group-count">{{ number_format($group->count) }} {{ $group->count === 1 ? 'item' : 'items' }}</span>
                        <span class="lb-d-group-total">{{ number_format($group->total, 1) }}</span>
                    </div>
                    @foreach ($group->rows->take($preview) as $row)
                        @include('leaderboard._detail-row', ['row' => $row])
                    @endforeach
                    @if ($group->count > $preview)
                        <a href="{{ request()->fullUrlWithQuery(['group' => $group->key]) }}"
                           class="lb-d-more">Show all {{ number_format($group->count) }} →</a>
                    @endif
                </div>
            @endforeach
        @endif

        {{-- Other-board line: a footnote below the content, only when the person is on both boards. --}}
        @if ($onOtherBoard)
            <p class="lb-d-otherboard {{ $zero ? 'lb-d-otherboard--zero' : '' }} {{ ! $zero && $view === 'list' ? 'lb-d-otherboard--list' : '' }}">
                {{ $otherBoardName }} score is tracked separately on the <a href="{{ route('leaderboard.detail', ['board' => $otherBoard, 'login' => $login]) }}">{{ $otherBoardName }} Leaderboard</a>.
            </p>
        @endif
    </div>

    @include('leaderboard._scoring-modal')
@endsection

@push('head')
    @include('leaderboard._lb-styles')
@endpush
