@php
    // The Monthly tab tracks the current board when it has a monthly variant
    // (contributor/maintainer); otherwise it defaults to the contributor board.
    $monthlyBoard = in_array($board ?? null, ['contributor', 'maintainer'], true) ? $board : 'contributor';
    $onMonthly = request()->routeIs('leaderboard.monthly') || request()->routeIs('leaderboard.monthly.index');
@endphp
<ul class="nav nav-tabs mb-4">
    @foreach ($boards as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ (! $onMonthly && ! request()->routeIs('leaderboard.highlights') && ($board ?? null) === $key) ? 'active' : '' }}"
               href="{{ route('leaderboard.show', ['board' => $key]) }}">
                {{ $label }}
            </a>
        </li>
    @endforeach
    <li class="nav-item">
        <a class="nav-link {{ $onMonthly ? 'active' : '' }}" href="{{ route('leaderboard.monthly.index', ['board' => $monthlyBoard]) }}">
            Monthly
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('leaderboard.highlights') ? 'active' : '' }}" href="{{ route('leaderboard.highlights') }}">
            Highlights
        </a>
    </li>
</ul>
