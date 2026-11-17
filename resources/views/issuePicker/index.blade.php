@use('App\Queries\Dashboard\IssuePickerQuery')
@use('Illuminate\Support\Str')
@extends('layouts.app')

@section('content')
    @php
        // A shared link may name an area no open issue carries; keep it selectable.
        $areaChoices = $selectedArea && ! in_array(mb_strtolower($selectedArea), array_map('mb_strtolower', $areaOptions), true)
            ? [$selectedArea, ...$areaOptions]
            : $areaOptions;
        $noComponents = $selectedArea && $componentOptions === [];
        $sorts = [IssuePickerQuery::SORT_OLDEST => 'Oldest first', IssuePickerQuery::SORT_NEWEST => 'Newest first', IssuePickerQuery::SORT_COMMENTS => 'Most comments'];
        $chips = ['showAssigned' => ['label' => 'Show assigned', 'on' => $showAssigned], 'showPr' => ['label' => 'Show with a PR', 'on' => $showPr]];
        $countText = 'Showing '.number_format($total).' of '.number_format($openTotal).' open issues';
        $shown = count($issues);
    @endphp

    <div class="lb ip">
        @if ($dataMissing)
            <x-data-missing>
                The issues index is empty or missing. Run
                <code>ddev artisan sync:github:issues</code> to populate it.
            </x-data-missing>
        @endif

        <p class="lb-intro">Narrow the open issues by area and component. Pick one, then open it on GitHub to claim it.</p>

        @unless ($dataMissing)
            {{-- No Apply button: the script applies each change. Labels are visually hidden; the placeholder options name the dropdowns. --}}
            <form method="GET" action="{{ route('issues.pick') }}" class="ip-filters" role="search" aria-label="Filter issues">
                <label class="visually-hidden" for="ip-area">Area</label>
                <select id="ip-area" name="area" @class(['ip-select', 'is-empty' => ! $selectedArea])>
                    <option value="">Any area</option>
                    @foreach ($areaChoices as $label)
                        <option value="{{ $label }}" @selected(mb_strtolower($label) === mb_strtolower((string) $selectedArea))>{{ Str::after($label, ': ') }}</option>
                    @endforeach
                </select>

                <label class="visually-hidden" for="ip-component">Component</label>
                <select id="ip-component" name="component" @class(['ip-select', 'is-empty' => ! $selectedComponent]) @disabled($noComponents)>
                    @if ($noComponents)
                        <option value="">No components for this area</option>
                    @else
                        <option value="">Any component</option>
                        @foreach ($componentOptions as $label)
                            <option value="{{ $label }}" @selected(mb_strtolower($label) === mb_strtolower((string) $selectedComponent))>{{ Str::after($label, ': ') }}</option>
                        @endforeach
                    @endif
                </select>

                <label class="visually-hidden" for="ip-sort">Sort</label>
                <select id="ip-sort" name="sort" class="ip-select">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($value === $sort)>{{ $label }}</option>
                    @endforeach
                </select>

                @foreach ($chips as $name => $chip)
                    {{-- The hidden input carries the chip into the URL; it is disabled (left out) while the chip is off. --}}
                    <input type="hidden" name="{{ $name }}" value="1" @disabled(! $chip['on'])>
                    <button type="button" class="ip-chip" data-param="{{ $name }}" aria-pressed="{{ $chip['on'] ? 'true' : 'false' }}">
                        <svg class="ip-check" width="11" height="9" viewBox="0 0 11 9" aria-hidden="true" focusable="false"><path d="M1 4.5 4 7.5 10 1.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        {{ $chip['label'] }}
                    </button>
                @endforeach

                {{-- Without JavaScript, Enter on a select submits the form. --}}
                <button type="submit" hidden>Apply</button>
            </form>
        @endunless

        <div class="ip-results">
            @unless ($dataMissing)
                <div class="ip-countline">
                    <p class="ip-count">{{ $countText }}</p>
                    <a class="ip-reset" href="{{ route('issues.pick') }}">Reset filters</a>
                </div>

                @if ($shown === 0)
                    <div class="lb-empty">
                        <p class="lb-empty-1">No open issues match these filters.</p>
                        <p class="lb-empty-2">Try turning on a Show filter, or choose a different area. <a class="ip-reset" href="{{ route('issues.pick') }}">Reset filters</a></p>
                    </div>
                @else
                    <table class="ip-table">
                        <thead>
                            <tr>
                                <th scope="col">Issue</th>
                                <th scope="col" class="ip-col-area">Area</th>
                                <th scope="col" class="ip-col-component">Component</th>
                                <th scope="col" class="ip-col-opened">Opened</th>
                                <th scope="col" class="ip-col-comments">Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($issues as $issue)
                                @php($createdAt = \Carbon\Carbon::parse($issue['created_at']))
                                <tr class="ipr">
                                    <td>
                                        {{-- The link stretches over the whole row, so the row is the action. --}}
                                        <a class="ipr-link" href="{{ $issue['url'] }}" target="_blank" rel="noopener">
                                            <span class="ipr-num">#{{ $issue['number'] }}</span>
                                            {{-- Title and the narrow-width component line share a column, so wrapped lines stay clear of the number. --}}
                                            <span class="ipr-text">
                                                <span class="ipr-title">{{ $issue['title'] }}</span>
                                                @if ($issue['components'])
                                                    <span class="ipr-sub">{{ implode(', ', $issue['components']) }}</span>
                                                @endif
                                            </span>
                                        </a>
                                    </td>
                                    <td class="ipr-label">{{ implode(', ', $issue['areas']) }}</td>
                                    <td class="ipr-label ip-col-component">{{ implode(', ', $issue['components']) }}</td>
                                    <td class="ipr-mono ip-col-opened"><time datetime="{{ $createdAt->toDateString() }}">{{ $createdAt->format('j M Y') }}</time></td>
                                    <td class="ipr-mono ip-col-comments">{{ number_format($issue['comments_count']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($moreRows)
                        <div class="lb-pager">
                            <a class="lb-more" href="{{ request()->fullUrlWithQuery(['rows' => $moreRows]) }}">Show {{ IssuePickerQuery::PER_PAGE }} more</a>
                        </div>
                    @endif
                @endif
            @endunless
        </div>

        <span class="ip-live visually-hidden" aria-live="polite"></span>
    </div>
@endsection

@push('head')
    @include('leaderboard._lb-styles')
    @include('issuePicker._styles')
@endpush

@push('scripts')
    @include('issuePicker._script')
@endpush
