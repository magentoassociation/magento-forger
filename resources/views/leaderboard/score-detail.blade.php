@extends('layouts.app')

@php
    use Carbon\Carbon;

    $name = $profile?->name ?: $login;
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $initials = collect($words)->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('')
        ?: mb_strtoupper(mb_substr($name, 0, 2));

    // Full month name ("April 2026") for the intro sentence.
    $monthFull = $activeMonth ? Carbon::createFromFormat('Y-m', $activeMonth)->format('F Y') : null;
@endphp

@section('content')
    @include('leaderboard._detail-header', [
        'scoreLabel' => $monthLabel ?? '12 months',
        'zero' => $groups->isEmpty(),
    ])

    <div class="container mx-auto lb lb-detail">
        @if ($groups->isEmpty())
            <p class="lb-d-intro">{{ $board === 'maintainer'
                ? 'No maintainer activity scored in the last 12 months. Reviews and merges you complete from here will show up on this page, grouped by what earned the points.'
                : 'No scored contributions in the last 12 months. PRs and issues you open from here will show up on this page, grouped by what earned the points.' }} <button type="button" class="lb-tallied" data-bs-toggle="modal" data-bs-target="#scoringModal">How are scores tallied?</button></p>
        @else
            <p class="lb-d-intro">Every scored contribution{{ $monthFull ? ' in '.$monthFull : ' in the last 12 months' }}, grouped by what earned the points. Each group's points sum to the grand total. <button type="button" class="lb-tallied" data-bs-toggle="modal" data-bs-target="#scoringModal">How are scores tallied?</button></p>
        @endif

        {{-- View toggle + month filter — suppressed on the zero-state (nothing to group/list/filter). --}}
        @unless ($groups->isEmpty())
        <div class="lb-d-controls">
            <span class="lb-d-toggle">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'grouped', 'group' => null]) }}" class="{{ $view === 'grouped' ? 'active' : '' }}">Grouped</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'list', 'group' => null]) }}" class="{{ $view === 'list' ? 'active' : '' }}">List</a>
            </span>
        </div>

        @if ($months->isNotEmpty())
            <div class="lb-months">
                <a href="{{ request()->fullUrlWithQuery(['month' => null, 'group' => null]) }}" class="lb-month {{ $activeMonth ? '' : 'active' }}">All</a>
                @foreach ($months as $ym)
                    <a href="{{ request()->fullUrlWithQuery(['month' => $ym, 'group' => null]) }}"
                       class="lb-month {{ $activeMonth === $ym ? 'active' : '' }}">{{ Carbon::createFromFormat('Y-m', $ym)->format('M Y') }}</a>
                @endforeach
            </div>
        @endif
        @endunless

        @if ($groups->isEmpty())
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
                    <a href="{{ $cta['url'] }}" target="_blank" rel="noopener" class="lb-d-empty-cta">{{ $cta['label'] }}</a>
                    <span class="lb-d-empty-hint">The groups above are the ones that earn {{ $board }} points. Each fills in as you go.</span>
                </div>
            </div>

        @elseif ($view === 'list')
            {{-- #9a: stat strip + one sortable list --}}
            <div class="lb-a-stats">
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

            <div class="lb-a-head">
                <h2 class="lb-d-group-name lb-d-group-name--single">Scored contributions</h2>
                <a href="{{ request()->fullUrlWithQuery(['sort' => 'type']) }}"
                   class="lb-a-sort {{ $sort === 'type' ? 'active' : '' }}">Type</a>
                <a href="{{ request()->fullUrlWithQuery(['sort' => 'date']) }}"
                   class="lb-a-sort {{ $sort === 'date' ? 'active' : '' }}">Date</a>
                <a href="{{ request()->fullUrlWithQuery(['sort' => 'points']) }}"
                   class="lb-a-sort lb-a-sort--right {{ $sort === 'points' ? 'active' : '' }}">Points</a>
            </div>
            @foreach ($flat as $row)
                @if ($row->url)
                    <a href="{{ $row->url }}" target="_blank" rel="noopener" class="lb-a-row">
                        <span class="lb-d-row-title">{{ $row->title }}</span>
                        <span class="lb-a-chip">{{ $row->tag }}</span>
                        <span class="lb-d-row-date">{{ $row->date?->format('j M Y') }}</span>
                        @include('leaderboard._detail-points', ['row' => $row])
                    </a>
                @else
                    <div class="lb-a-row">
                        <span class="lb-d-row-title">{{ $row->title }}</span>
                        <span class="lb-a-chip">{{ $row->tag }}</span>
                        <span class="lb-d-row-date">{{ $row->date?->format('j M Y') }}</span>
                        @include('leaderboard._detail-points', ['row' => $row])
                    </div>
                @endif
            @endforeach

        @elseif ($activeGroup)
            {{-- #9b: single filtered group --}}
            <a href="{{ request()->fullUrlWithQuery(['group' => null]) }}" class="lb-d-back">← All contributions</a>
            <div class="lb-d-group">
                <div class="lb-d-grouphead">
                    <h2 class="lb-d-group-name lb-d-group-name--single">{{ $activeGroup->name }}</h2>
                    <span class="lb-d-group-count">{{ number_format($activeGroup->count) }} items</span>
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
                        <span class="lb-d-group-count">{{ number_format($group->count) }} items</span>
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
            <p class="lb-d-otherboard {{ $groups->isEmpty() ? 'lb-d-otherboard--zero' : '' }}">Your {{ strtolower($otherBoardName) }} score is tracked separately on the <a href="{{ route('leaderboard.detail', ['board' => $otherBoard, 'login' => $login]) }}">{{ $otherBoardName }} Board</a>.</p>
        @endif
    </div>

    @include('leaderboard._scoring-modal')
@endsection

@push('head')
    @include('leaderboard._lb-styles')
@endpush
