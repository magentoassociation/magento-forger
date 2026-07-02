@php
    // The Monthly tab tracks the current board when it has a monthly variant
    // (contributor/maintainer); otherwise it defaults to the contributor board.
    $monthlyBoard = in_array($board ?? null, ['contributor', 'maintainer'], true) ? $board : 'contributor';
    $onMonthly = request()->routeIs('scores.monthly') || request()->routeIs('scores.monthly.index');
@endphp
<ul class="nav nav-tabs mb-4">
    @foreach ($boards as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ (! $onMonthly && ! request()->routeIs('scores.highlights') && ($board ?? null) === $key) ? 'active' : '' }}"
               href="{{ route('scores.show', ['board' => $key]) }}">
                {{ $label }}
            </a>
        </li>
    @endforeach
    <li class="nav-item">
        <a class="nav-link {{ $onMonthly ? 'active' : '' }}" href="{{ route('scores.monthly.index', ['board' => $monthlyBoard]) }}">
            Monthly
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('scores.highlights') ? 'active' : '' }}" href="{{ route('scores.highlights') }}">
            Highlights
        </a>
    </li>
</ul>
