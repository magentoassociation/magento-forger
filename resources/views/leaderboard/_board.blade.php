{{--
    The #21 ranked board: control strip, 4-column rows, activity column and
    pagination. The whole population is rendered and the inline script that
    follows it owns pagination: it reads ?rows, hides rows past that depth, and
    fills the count. Search, jump to my rank and "Show 25 more" reveal rows without
    another request; with no JavaScript every row shows and the pager is absent.

    Expected from the including view:
      $entries, $board, $boards, $profiles,
      $total, $noun, $windowCaption,
      $viewerLogin, $viewerRank, $viewerNotRanked,
      $detailUrl  — fn(string $login): string
      $emptyText  — line shown when the board has no rows
--}}
@php
    use App\DataTransferObjects\Leaderboard\Action;

    $initials = function (string $name): string {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = collect($words)->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)));

        return $letters->implode('') ?: mb_strtoupper(mb_substr($name, 0, 2));
    };

    // Activity cell for the 130px column: one written-out total of every scored
    // action — "328 actions" ("1 action" singular) — with no tooltip. Its
    // accessible name spells the per-action counts ("328 actions: PRs opened 281,
    // issues opened 47"), zeros omitted, in the spec's order. Returns
    // [total, accessible-name]; the score tooltip is the only hover breakdown.
    $activity = function (array $breakdown): array {
        $total = 0;
        $parts = [];
        foreach (Action::activityOrder() as $action) {
            $count = (int) ($breakdown[$action->value]['count'] ?? 0);
            if ($count > 0) {
                $total += $count;
                $parts[] = $action->countLabel().' '.number_format($count);
            }
        }
        $word = $total === 1 ? 'action' : 'actions';
        $aria = $parts ? number_format($total).' '.$word.': '.implode(', ', $parts) : '';

        return [$total, $word, $aria];
    };

    $isMonthly = request()->routeIs('leaderboard.monthly');
@endphp

<div class="lb-board"
     data-total="{{ $total }}"
     data-noun="{{ $noun }}"
     data-window="{{ $windowCaption }}"
     data-monthly="{{ $isMonthly ? '1' : '0' }}"
     @if ($viewerRank) data-viewer-rank="{{ $viewerRank }}" @endif>
    <script>document.currentScript.parentNode.classList.add('js');</script>

    {{-- Control strip --}}
    <div class="lb-strip">
        <span class="lb-pop">{{ number_format($total) }} {{ $noun }} · {{ $windowCaption }}</span>

        @if ($total > 0)
            <div class="lb-search">
                <span class="lb-search-ico" aria-hidden="true">⌕</span>
                <input type="search" class="lb-search-input" autocomplete="off"
                       placeholder="Search name or handle" aria-label="Search name or handle">
                <button type="button" class="lb-search-clear" aria-label="Clear search" hidden>✕</button>
            </div>
        @endif

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
                [$actCount, $actWord, $actAria] = $activity($breakdown);
            @endphp
            <div class="lbr"
                 id="rank-{{ $rank }}" tabindex="-1"
                 data-search="{{ mb_strtolower($name.' @'.$entry->login) }}">
                <span class="lbr-rank {{ $rank <= 3 ? 'is-top' : '' }}">{{ $rank }}</span>

                <a class="lbr-avatar" href="https://github.com/{{ $entry->login }}" target="_blank" rel="noopener"
                   title="GitHub profile — {{ $name }}" aria-label="GitHub profile — {{ $name }}">
                    <span class="lbr-avatar-initials">{{ $initials($name) }}</span>
                    <img src="https://avatars.githubusercontent.com/{{ $entry->login }}?s=56"
                         alt="" width="28" height="28" loading="lazy" onerror="this.remove()">
                </a>

                <span class="lbr-id">
                    {{-- Name links to the detail page unconditionally; a zero-score
                         row still has a page (it shows the empty state). --}}
                    <a class="lbr-name" href="{{ $detailUrl($entry->login) }}">{{ $name }}</a>
                    <span class="lbr-handle">{{ '@'.$entry->login }}</span>
                    @if (($entry->active ?? true) === false)
                        <span class="lbr-inactive" title="No longer on the maintainer team">Inactive</span>
                    @endif
                </span>

                {{-- aria-label is ignored on a generic span, so the spoken breakdown is real hidden text. --}}
                <span class="lbr-activity">
                    @if ($actCount > 0)
                        <span aria-hidden="true">{{ number_format($actCount) }} {{ $actWord }}</span>
                        <span class="visually-hidden">{{ $actAria }}</span>
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

        {{-- Empty board (server) / search no-results (JS) block --}}
        <div class="lb-empty" @if ($total > 0) hidden @endif>
            <p class="lb-empty-1">{{ $total > 0 ? '' : $emptyText }}</p>
            <p class="lb-empty-2"
               data-monthly="{{ $isMonthly ? '1' : '0' }}"
               data-window="{{ $windowCaption }}"
               data-other-href="{{ $isMonthly ? route('leaderboard.show', ['board' => 'contributor']) : route('leaderboard.show', ['board' => $board === 'maintainer' ? 'contributor' : 'maintainer']) }}"
               data-other-name="{{ $isMonthly ? 'Contributor Leaderboard' : ($board === 'maintainer' ? 'Contributor Leaderboard' : 'Maintainer Leaderboard') }}"></p>
        </div>
    </div>

    {{-- Pagination — JS only; without it every row is already visible. --}}
    @if ($total > 0)
        <div class="lb-pager">
            <button type="button" class="lb-more" hidden>Show 25 more</button>
            <span class="lb-count-stmt"></span>
        </div>
    @endif

    <span class="lb-live visually-hidden" aria-live="polite"></span>
</div>

{{-- Inline, straight after the rows, so rows past the depth hide before first paint. --}}
@include('leaderboard._board-script')
