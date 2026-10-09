@use('App\Queries\Dashboard\CommunityPickCandidatesQuery')
@extends('layouts.app')

@section('content')
    @if ($dataMissing)
        <x-data-missing>
            The pull-requests index is empty or missing. Run
            <code>ddev artisan sync:github:prs</code> to populate it.
        </x-data-missing>
    @endif

    <x-info-text :info="$infoText" />

    @php
        $repoUrl = 'https://github.com/'.config('github.repo');
        $filtered = $selectedArea || $selectedComponent || $selectedAuthor;
        // A shared link may name a label no current candidate carries; keep it selectable.
        $withSelected = fn (?string $selected, array $options): array => $selected && ! in_array($selected, $options, true) ? [$selected, ...$options] : $options;
        $filters = [
            'area' => ['label' => 'Area', 'selected' => $selectedArea, 'options' => $withSelected($selectedArea, $areaOptions)],
            'component' => ['label' => 'Component', 'selected' => $selectedComponent, 'options' => $withSelected($selectedComponent, $componentOptions)],
        ];
    @endphp

    @unless ($dataMissing)
        <form method="GET" action="{{ route('prs.communityPicked') }}" class="row g-2 align-items-end mb-3">
            @foreach ($filters as $name => $filter)
                <div class="col-sm-auto">
                    <label for="filter-{{ $name }}" class="form-label mb-1">{{ $filter['label'] }}</label>
                    <select id="filter-{{ $name }}" name="{{ $name }}" class="form-select" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach ($filter['options'] as $option)
                            <option value="{{ $option }}" @selected($option === $filter['selected'])>{{ \Illuminate\Support\Str::after($option, ': ') }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-sm-auto">
                <label for="filter-author" class="form-label mb-1">Author</label>
                <input type="search" id="filter-author" name="author" value="{{ $selectedAuthor }}" list="filter-author-options"
                       class="form-control" placeholder="GitHub username" autocomplete="off" spellcheck="false"
                       maxlength="50" onchange="this.form.submit()">
                <datalist id="filter-author-options">
                    @foreach ($authorOptions as $option)
                        <option value="{{ $option }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div class="col-sm-auto">
                <label for="filter-sort" class="form-label mb-1">Sort</label>
                <select id="filter-sort" name="sort" class="form-select" onchange="this.form.submit()">
                    @foreach ([CommunityPickCandidatesQuery::SORT_VOTES => 'Most votes', CommunityPickCandidatesQuery::SORT_OLDEST => 'Oldest first', CommunityPickCandidatesQuery::SORT_NEWEST => 'Newest first'] as $value => $label)
                        <option value="{{ $value }}" @selected($value === $sort)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
                @if ($filtered)
                    {{-- Clears the filters, keeps the sort. --}}
                    <a href="{{ route('prs.communityPicked', $sort === CommunityPickCandidatesQuery::SORT_VOTES ? [] : ['sort' => $sort]) }}" class="btn btn-link">Clear</a>
                @endif
            </div>
        </form>
    @endunless

    @if ($candidates->isEmpty() && ! $dataMissing)
        <div class="alert alert-info text-center">
            <h4>{{ $filtered ? 'No candidates match these filters.' : 'No community pick candidates right now.' }}</h4>
        </div>
    @elseif ($candidates->isNotEmpty())
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th scope="col" class="text-center">👍</th>
                        <th scope="col">Pull request</th>
                        <th scope="col">Linked issues</th>
                        <th scope="col">Area / Component</th>
                        <th scope="col">Author</th>
                        <th scope="col">Age</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($candidates as $pr)
                        <tr>
                            <td class="text-center">
                                <a href="{{ $pr['url'] }}" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-primary text-nowrap"
                                   aria-label="Vote for #{{ $pr['number'] }} on GitHub ({{ $pr['thumbs_up_count'] }} 👍)">
                                    👍 {{ number_format($pr['thumbs_up_count']) }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ $pr['url'] }}" target="_blank" rel="noopener">#{{ $pr['number'] }}</a>
                                {{ $pr['title'] }}
                            </td>
                            <td>
                                @forelse ($pr['linked_issues'] as $issue)
                                    <a href="{{ $repoUrl }}/issues/{{ $issue }}" target="_blank" rel="noopener">#{{ $issue }}</a>@if (! $loop->last), @endif
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td>
                                @forelse ($pr['labels'] as $label)
                                    <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal">{{ $label }}</span>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td>
                                @if ($pr['author'])
                                    <a href="{{ route('leaderboard.detail', ['board' => 'contributor', 'login' => $pr['author']]) }}">{{ $pr['author'] }}</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            @php $createdAt = \Carbon\Carbon::parse($pr['created_at']); @endphp
                            <td class="text-nowrap">
                                <time datetime="{{ $createdAt->toIso8601String() }}" title="{{ $createdAt->toFormattedDateString() }}">
                                    {{ $createdAt->diffForHumans() }}
                                </time>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $candidates->links('pagination::bootstrap-5') }}
    @endif
@endsection
