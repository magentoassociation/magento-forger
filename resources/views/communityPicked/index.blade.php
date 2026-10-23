@use('App\Queries\Dashboard\CommunityPickCandidatesQuery')
@use('Illuminate\Support\Str')
@extends('layouts.app')

@section('content')
    @php
        $repoUrl = 'https://github.com/'.config('github.repo');
        $filtered = $selectedArea || $selectedComponent || $selectedAuthor || $selectedTitle;
        // A shared link may name a label no current candidate carries; keep it selectable.
        // Labels are compared ignoring case: the link may carry a spelling from before a rename.
        $withSelected = fn (?string $selected, array $options): array => $selected && ! in_array(mb_strtolower($selected), array_map('mb_strtolower', $options), true) ? [$selected, ...$options] : $options;
        $filters = [
            'area' => ['label' => 'Area', 'all' => 'All areas', 'selected' => $selectedArea, 'options' => $withSelected($selectedArea, $areaOptions)],
            'component' => ['label' => 'Component', 'all' => 'All components', 'selected' => $selectedComponent, 'options' => $withSelected($selectedComponent, $componentOptions)],
        ];
        $sorts = [CommunityPickCandidatesQuery::SORT_VOTES => 'Most votes', CommunityPickCandidatesQuery::SORT_OLDEST => 'Oldest first', CommunityPickCandidatesQuery::SORT_NEWEST => 'Newest first'];
        $shown = count($candidates);
    @endphp

    <div class="lb cp">
        @if ($dataMissing)
            <x-data-missing>
                The pull-requests index is empty or missing. Run
                <code>ddev artisan sync:github:prs</code> to populate it.
            </x-data-missing>
        @endif

        <p class="lb-intro">Vote for the open pull requests you want merged into Magento 2: click a PR's number to open it on GitHub, then click 👍 on the PR description. After each voting period, the top-voted PRs are prioritized for merging. Vote counts refresh from GitHub every 15 minutes.</p>

        @if ($monthlyResults->isNotEmpty())
            {{-- Monthly results (#32a). Every option carries what the full view (#32c) swaps in. --}}
            @php
                $monthOptions = $monthlyResults->map(fn ($result): array => [
                    'value' => $result->month->format('Y-m'),
                    'heading' => $result->month->format('F Y').' results',
                    'posted' => 'Posted '.$result->posted_on->format('M j, Y'),
                    'postedIso' => $result->posted_on->toDateString(),
                    'postedFull' => $result->posted_on->format('F j, Y'),
                    'src' => $result->imageUrl(),
                    'alt' => $result->month->format('F Y').' Community-Picked results: open PRs on GitHub sorted by reactions',
                ]);
                $latest = $monthOptions->first();
            @endphp
            <section class="cp-band" aria-labelledby="cp-results-heading">
                <div class="cp-band-head">
                    <h2 class="cp-h2" id="cp-results-heading">{{ $latest['heading'] }}</h2>
                    <time class="cp-posted" datetime="{{ $latest['postedIso'] }}" title="{{ $latest['postedFull'] }}">{{ $latest['posted'] }}</time>
                    <select class="cp-select cp-past" aria-label="Past results">
                        <option value="">Past results</option>
                        @foreach ($monthOptions as $option)
                            <option value="{{ $option['value'] }}">{{ Str::before($option['heading'], ' results') }}</option>
                        @endforeach
                    </select>
                </div>
                <a class="cp-preview" href="{{ $latest['src'] }}" data-bs-toggle="modal" data-bs-target="#cp-results-modal" data-month="{{ $latest['value'] }}">
                    <img src="{{ $latest['src'] }}" alt="{{ $latest['alt'] }}">
                    <span class="cp-preview-label">View full results</span>
                </a>
            </section>

            <div class="modal fade cpm" id="cp-results-modal" tabindex="-1" aria-labelledby="cp-results-modal-heading" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered cpm-dialog">
                    <div class="modal-content cpm-panel">
                        <div class="cpm-header">
                            <div class="cpm-headtext">
                                <h2 class="cp-h2" id="cp-results-modal-heading">{{ $latest['heading'] }}</h2>
                                <time class="cp-posted" datetime="{{ $latest['postedIso'] }}" title="{{ $latest['postedFull'] }}">{{ $latest['posted'] }}</time>
                                <a class="cpm-github" href="{{ $repoUrl }}/pulls?q={{ urlencode('is:pr state:open label:"Project: Community Picked"') }}" target="_blank" rel="noopener">Community-Picked PRs on GitHub</a>
                            </div>
                            <select class="cp-select cpm-month" aria-label="Month">
                                @foreach ($monthOptions as $option)
                                    <option value="{{ $option['value'] }}" data-heading="{{ $option['heading'] }}" data-posted="{{ $option['posted'] }}"
                                            data-posted-iso="{{ $option['postedIso'] }}" data-posted-full="{{ $option['postedFull'] }}"
                                            data-src="{{ $option['src'] }}" data-alt="{{ $option['alt'] }}">{{ Str::before($option['heading'], ' results') }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="cpm-close" data-bs-dismiss="modal" aria-label="Close">✕</button>
                        </div>
                        <div class="modal-body cpm-body">
                            <img src="{{ $latest['src'] }}" alt="{{ $latest['alt'] }}" loading="lazy">
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <h2 class="cp-h2 cp-voting">Open for voting</h2>
        @if ($votingEndsOn)
            <p class="cp-voting-ends">Voting ends on <time datetime="{{ $votingEndsOn->toDateString() }}">{{ $votingEndsOn->format('F j, Y') }}</time>.</p>
        @endif

        @unless ($dataMissing)
            {{-- No Filter button: selects apply on change, text fields on Enter or a typing pause (script below). --}}
            <form method="GET" action="{{ route('prs.communityPicked') }}" class="cp-strip" role="search">
                <div class="lb-search cp-search">
                    <input type="search" name="title" value="{{ $selectedTitle }}" @class(['lb-search-input', 'is-set' => $selectedTitle])
                           placeholder="PR title or #" aria-label="PR title or number"
                           maxlength="200" autocomplete="off">
                </div>
                @foreach ($filters as $name => $filter)
                    <select name="{{ $name }}" @class(['cp-select', 'is-set' => $filter['selected']]) aria-label="{{ $filter['label'] }}">
                        <option value="">{{ $filter['all'] }}</option>
                        @foreach ($filter['options'] as $option)
                            <option value="{{ $option }}" @selected(mb_strtolower($option) === mb_strtolower((string) $filter['selected']))>{{ Str::after($option, ': ') }}</option>
                        @endforeach
                    </select>
                @endforeach
                <input type="search" name="author" value="{{ $selectedAuthor }}" list="cp-author-options"
                       @class(['cp-input', 'is-set' => $selectedAuthor]) placeholder="Author handle" aria-label="Author handle"
                       autocomplete="off" spellcheck="false" maxlength="50">
                <datalist id="cp-author-options">
                    @foreach ($authorOptions as $option)
                        <option value="{{ $option }}"></option>
                    @endforeach
                </datalist>
                {{-- Shown while a filter is set; the script keeps it in step as filters change. --}}
                <button type="button" class="cp-clear" @if (! $filtered) hidden @endif>Clear filters</button>
                <select name="sort" class="cp-select cp-select--dark" aria-label="Sort">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($value === $sort)>{{ $label }}</option>
                    @endforeach
                </select>
                {{-- Default button: Enter in either text field submits without JavaScript. --}}
                <button type="submit" hidden>Filter</button>
            </form>
        @endunless

        <div class="cp-results">
            @unless ($dataMissing)
                <p class="cp-count">{{ number_format($total) }} open {{ $total === 1 ? 'PR' : 'PRs' }}</p>
            @endunless

            @if ($shown === 0 && ! $dataMissing)
                <div class="lb-empty">
                    <p class="lb-empty-1">{{ $filtered ? 'No candidates match these filters.' : 'No community pick candidates right now.' }}</p>
                </div>
            @elseif ($shown > 0)
                <div class="cp-colhead" role="presentation">
                    <span>Votes</span>
                    <span>Pull request</span>
                    <span class="cp-col-meta">Author · Age</span>
                </div>

                <div class="cp-rows">
                    @foreach ($candidates as $pr)
                        @php
                            $areas = array_filter($pr['labels'], fn (string $label): bool => str_starts_with($label, CommunityPickCandidatesQuery::AREA_PREFIX));
                            $components = array_filter($pr['labels'], fn (string $label): bool => str_starts_with($label, CommunityPickCandidatesQuery::COMPONENT_PREFIX));
                            $createdAt = \Carbon\Carbon::parse($pr['created_at']);
                        @endphp
                        <div class="cpr" tabindex="-1">
                            <span class="cpr-votes">{{ number_format($pr['thumbs_up_count']) }}<span class="visually-hidden"> votes</span></span>
                            <div class="cpr-pr">
                                <div class="cpr-line1">
                                    <a class="cpr-num" href="{{ $pr['url'] }}" target="_blank" rel="noopener">#{{ $pr['number'] }}</a>
                                    <span class="cpr-title">{{ $pr['title'] }}</span>
                                </div>
                                @if ($pr['labels'] || $pr['linked_issues'])
                                    <div class="cpr-line2">
                                        @foreach ($areas as $label)
                                            <span class="cpr-area">{{ Str::after($label, ': ') }}</span>
                                        @endforeach
                                        @foreach ($components as $label)
                                            <span class="cpr-comp">{{ Str::after($label, ': ') }}</span>
                                        @endforeach
                                        @if ($pr['linked_issues'])
                                            <span class="cpr-issues">
                                                <span class="cpr-issues-label">Issue</span>
                                                @foreach ($pr['linked_issues'] as $issue)
                                                    <a class="cpr-issue" href="{{ $repoUrl }}/issues/{{ $issue }}" target="_blank" rel="noopener">#{{ $issue }}</a>
                                                @endforeach
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="cpr-meta">
                                @if ($pr['author'])
                                    <a class="cpr-author" href="{{ route('leaderboard.detail', ['board' => 'contributor', 'login' => $pr['author']]) }}"
                                       title="Contributions — {{ '@'.$pr['author'] }}">{{ '@'.$pr['author'] }}</a>
                                @endif
                                <time class="cpr-age" datetime="{{ $createdAt->toIso8601String() }}" title="{{ $createdAt->toFormattedDateString() }}">{{ $createdAt->diffForHumans() }}</time>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="lb-pager">
                    @if ($moreRows)
                        <a class="lb-more" href="{{ request()->fullUrlWithQuery(['rows' => $moreRows]) }}">Show {{ CommunityPickCandidatesQuery::PER_PAGE }} more</a>
                    @endif
                    <span class="lb-count-stmt">{{ $shown >= $total ? 'Showing all '.number_format($total) : 'Showing 1–'.number_format($shown).' of '.number_format($total) }}</span>
                </div>
            @endif
        </div>

        <span class="cp-live visually-hidden" aria-live="polite"></span>
    </div>
@endsection

@push('head')
    @include('leaderboard._lb-styles')
    @include('communityPicked._styles')
@endpush

@push('scripts')
    @include('communityPicked._script')
@endpush
