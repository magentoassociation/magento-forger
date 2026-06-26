<ul class="nav nav-tabs mb-4">
    @foreach ($boards as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ (! request()->routeIs('scores.highlights') && ($board ?? null) === $key) ? 'active' : '' }}"
               href="{{ route('scores.show', ['board' => $key]) }}">
                {{ $label }}
            </a>
        </li>
    @endforeach
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('scores.highlights') ? 'active' : '' }}" href="{{ route('scores.highlights') }}">
            Highlights
        </a>
    </li>
</ul>
