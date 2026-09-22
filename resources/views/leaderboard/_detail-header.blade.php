{{-- #18a: shared full-bleed title block for every detail page with a person as the H1. --}}
<header class="page-title-bar lb-d-titlebar">
    <div class="container mx-auto">
        {{-- No back link: the nav marks Leaderboard as current and these pages are only reached from a board. --}}
        <div class="lb-d-head">
            <a href="https://github.com/{{ $login }}" target="_blank" rel="noopener"
               class="lb-d-avatar" title="GitHub profile — {{ $name }}" aria-label="GitHub profile — {{ $name }}">
                <span class="lb-d-avatar-initials">{{ $initials }}</span>
                <img src="https://avatars.githubusercontent.com/{{ $login }}?s=112"
                     alt="" width="56" height="56" loading="lazy" onerror="this.remove()">
            </a>
            <div class="lb-d-idcol">
                <h1 class="lb-d-name">{{ $name }}</h1>
                <a href="https://github.com/{{ $login }}" target="_blank" rel="noopener" class="lb-d-handle">{{ '@'.$login }}</a>
            </div>
            <div class="lb-d-scoreblock">
                <span class="lb-d-score {{ ($zero ?? false) ? 'lb-d-score--zero' : '' }}">{{ number_format($total, 1) }}</span>
                <span class="lb-d-score-label">Points · {{ $scoreLabel ?? '12 months' }}</span>
            </div>
        </div>
    </div>
</header>
