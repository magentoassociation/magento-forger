@extends('layouts.app')

@section('content')
    @if ($dataMissing)
        <x-data-missing>
            The pull-requests index is empty or missing. Run
            <code>ddev artisan sync:github:prs</code> to populate it.
        </x-data-missing>
    @endif

    <x-info-text :info="$infoText" />

    @php $repoUrl = 'https://github.com/'.config('github.repo'); @endphp

    @if ($candidates->isEmpty() && ! $dataMissing)
        <div class="alert alert-info text-center">
            <h4>No community pick candidates right now.</h4>
        </div>
    @elseif ($candidates->isNotEmpty())
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th scope="col" class="text-center">👍</th>
                        <th scope="col">Pull request</th>
                        <th scope="col">Linked issues</th>
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
                                @if ($pr['author'])
                                    <a href="https://github.com/{{ $pr['author'] }}" target="_blank" rel="noopener">{{ $pr['author'] }}</a>
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
