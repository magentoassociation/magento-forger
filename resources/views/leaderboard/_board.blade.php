{{--
    The #21 ranked board: control strip, 4-column rows, activity column and
    pagination. The whole population is rendered; rows past $initialRows carry
    `is-beyond` and are hidden by CSS. Progressive enhancement (search, jump to
    my rank, in-place "show more") reveals them without another request; with no
    JavaScript the "Show 25 more" link reloads at a deeper ?rows=.

    Expected from the including view:
      $entries, $board, $boards, $profiles,
      $total, $noun, $windowCaption,
      $viewerLogin, $viewerRank, $viewerNotRanked, $initialRows,
      $detailUrl  — fn(string $login): string
--}}
@php
    use App\DataTransferObjects\Leaderboard\Action;

    $initials = function (string $name): string {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = collect($words)->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)));

        return $letters->implode('') ?: mb_strtoupper(mb_substr($name, 0, 2));
    };

    // Abbreviated, counted activity for the 130px column. Contributor/monthly
    // count PRs and issues; maintainer counts reviews and merges.
    $activity = function (array $breakdown) use ($board): array {
        if ($board === 'maintainer') {
            $rev = (int) ($breakdown['review_approved']['count'] ?? 0)
                + (int) ($breakdown['review_rejected']['count'] ?? 0)
                + (int) ($breakdown['review_commented']['count'] ?? 0);
            $mrg = (int) ($breakdown['approved_then_merged']['count'] ?? 0);
            $parts = [];
            if ($rev > 0) { $parts[] = number_format($rev).' REV'; }
            if ($mrg > 0) { $parts[] = number_format($mrg).' MRG'; }

            return $parts;
        }

        $pr = (int) ($breakdown['pr_opened']['count'] ?? 0);
        $iss = (int) ($breakdown['issue_opened']['count'] ?? 0);
        $parts = [];
        if ($pr > 0) { $parts[] = number_format($pr).' PR'; }
        if ($iss > 0) { $parts[] = number_format($iss).' ISS'; }

        return $parts;
    };

    $shown = min($initialRows, $total);
    $isMonthly = request()->routeIs('leaderboard.monthly');
@endphp

<div class="lb-board"
     data-total="{{ $total }}"
     data-noun="{{ $noun }}"
     data-window="{{ $windowCaption }}"
     data-shown="{{ $shown }}"
     @if ($viewerRank) data-viewer-rank="{{ $viewerRank }}" @endif>

    {{-- Control strip --}}
    <div class="lb-strip">
        <span class="lb-pop">{{ number_format($total) }} {{ $noun }} · {{ $windowCaption }}</span>

        <div class="lb-search">
            <span class="lb-search-ico" aria-hidden="true">⌕</span>
            <input type="search" class="lb-search-input" autocomplete="off"
                   placeholder="Search name or handle" aria-label="Search name or handle">
            <button type="button" class="lb-search-clear" aria-label="Clear search" hidden>✕</button>
        </div>

        @if ($viewerRank)
            <a class="lb-jump" href="#rank-{{ $viewerRank }}" data-rank="{{ $viewerRank }}">
                <img class="lb-jump-av" src="https://avatars.githubusercontent.com/{{ $viewerLogin }}?s=40"
                     alt="" width="20" height="20" onerror="this.style.visibility='hidden'">
                <span class="lb-jump-label">Jump to my rank</span>
                <span class="lb-jump-rank">#{{ $viewerRank }}</span>
            </a>
        @elseif ($viewerNotRanked)
            <a class="lb-jump lb-jump--out" href="{{ $detailUrl($viewerLogin) }}">
                <img class="lb-jump-av" src="https://avatars.githubusercontent.com/{{ $viewerLogin }}?s=40"
                     alt="" width="20" height="20" onerror="this.style.visibility='hidden'">
                <span class="lb-jump-label">You're not on this board yet</span>
            </a>
        @endif
    </div>

    {{-- Column header --}}
    <div class="lb-colhead" role="presentation">
        <span class="lb-col-rank">#</span>
        <span class="lb-col-name">{{ $boards[$board] }}</span>
        <span class="lb-col-act">Activity</span>
        <span class="lb-col-score">Score</span>
    </div>

    {{-- Rows --}}
    <div class="lb-rows">
        @foreach ($entries as $entry)
            @php
                $profile = $profiles->get($entry->login);
                $name = $profile?->name ?: $entry->login;
                $rank = (int) ($entry->rank ?? $loop->iteration);
                $breakdown = $entry->breakdown ?? [];
                $hasBreakdown = ! empty($breakdown);
                $acts = $activity($breakdown);
                $isViewer = $viewerLogin !== null && $entry->login === $viewerLogin;
            @endphp
            <div class="lbr {{ $loop->iteration > $initialRows ? 'is-beyond' : '' }}"
                 id="rank-{{ $rank }}" tabindex="-1"
                 data-search="{{ mb_strtolower($name.' '.$entry->login) }}"
                 @if ($isViewer) aria-label="Your rank, {{ $rank }}" @endif>
                <span class="lbr-rank {{ $rank <= 3 ? 'is-top' : '' }}">{{ $rank }}</span>

                <a class="lbr-avatar" href="https://github.com/{{ $entry->login }}" target="_blank" rel="noopener"
                   title="GitHub profile — {{ $name }}" aria-label="GitHub profile — {{ $name }}">
                    <span class="lbr-avatar-initials">{{ $initials($name) }}</span>
                    <img src="https://avatars.githubusercontent.com/{{ $entry->login }}?s=56"
                         alt="" width="28" height="28" loading="lazy" onerror="this.remove()">
                </a>

                <span class="lbr-id">
                    @if ($entry->score > 0)
                        <a class="lbr-name" href="{{ $detailUrl($entry->login) }}">{{ $name }}</a>
                    @else
                        <span class="lbr-name">{{ $name }}</span>
                    @endif
                    <span class="lbr-handle">{{ '@'.$entry->login }}</span>
                    @if (($entry->active ?? true) === false)
                        <span class="badge text-bg-secondary" title="No longer on the maintainer team">Inactive</span>
                    @endif
                </span>

                <span class="lbr-activity">
                    @if ($acts)
                        {{ implode(' · ', $acts) }}
                    @elseif ($entry->score > 0)
                        <a href="{{ $detailUrl($entry->login) }}">See contributions</a>
                    @endif
                </span>

                <span class="lb-score-cell">
                    <span class="lb-score {{ $hasBreakdown ? 'lb-score-has-tip' : '' }}" @if ($hasBreakdown) tabindex="0" @endif>
                        {{ number_format($entry->score, 1) }}
                    </span>
                    @if ($hasBreakdown)
                        <span class="lb-tip" role="tooltip">
                            @foreach ($breakdown as $action => $detail)
                                <span class="lb-tip-line">
                                    <span class="lb-tip-label">{{ Action::labelFor($action) }}</span>
                                    <span class="lb-tip-count">{{ number_format($detail['count'] ?? 0) }}×</span>
                                    <span class="lb-tip-pts">{{ number_format($detail['points'] ?? 0, 1) }} pts</span>
                                </span>
                            @endforeach
                            <span class="lb-tip-arrow"></span>
                        </span>
                    @endif
                </span>
            </div>
        @endforeach

        {{-- No-results (search) / whole-board-failure block, revealed by JS --}}
        <div class="lb-empty" hidden>
            <p class="lb-empty-1"></p>
            <p class="lb-empty-2"
               data-monthly="{{ $isMonthly ? '1' : '0' }}"
               data-window="{{ $windowCaption }}"
               data-other-href="{{ $isMonthly ? route('leaderboard.show', ['board' => 'contributor']) : route('leaderboard.show', ['board' => $board === 'maintainer' ? 'contributor' : 'maintainer']) }}"
               data-other-name="{{ $isMonthly ? 'Contributor Leaderboard' : ($board === 'maintainer' ? 'Contributor Leaderboard' : 'Maintainer Leaderboard') }}"></p>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="lb-pager">
        @if ($shown < $total)
            <a class="lb-more" href="{{ request()->fullUrlWithQuery(['rows' => min($initialRows + 25, $total)]) }}">Show 25 more</a>
        @endif
        <span class="lb-count-stmt">{{ $shown >= $total ? 'Showing all '.number_format($total) : 'Showing 1–'.number_format($shown).' of '.number_format($total) }}</span>
    </div>

    <span class="lb-live visually-hidden" aria-live="polite"></span>
</div>

@push('scripts')
    @include('leaderboard._board-script')
@endpush
