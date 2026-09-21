@extends('layouts.app')

@php
    use App\DataTransferObjects\Leaderboard\Action;

    // PR / issue counts for the contribution count link, drawn from the same
    // breakdown that feeds the score tooltip.
    $countLabel = function (array $breakdown): string {
        $prs = (int) ($breakdown['pr_opened']['count'] ?? 0);
        $issues = (int) ($breakdown['issue_opened']['count'] ?? 0);
        $parts = [];
        if ($prs > 0) {
            $parts[] = number_format($prs).' PR'.($prs === 1 ? '' : 's');
        }
        if ($issues > 0) {
            $parts[] = number_format($issues).' '.\Illuminate\Support\Str::plural('issue', $issues);
        }

        return $parts ? implode(' · ', $parts) : 'See contributions';
    };

    // Two-letter initials for the avatar placeholder shown until the real
    // GitHub image loads (or if it fails).
    $initials = function (string $name): string {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = collect($words)->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)));

        return $letters->implode('') ?: mb_strtoupper(mb_substr($name, 0, 2));
    };
@endphp

@section('content')
    <div class="lb">
        <p class="lb-intro">
            Ranked by the last 12 months of activity — recent work and bigger changes count for more.
            Points come from {{ $scoring['scoredList'] }}. Note that scores are subject to change.
        </p>

        <button type="button" class="lb-tallied" data-bs-toggle="modal" data-bs-target="#scoringModal">
            How are scores tallied?
        </button>

        @include('leaderboard._tabs')

        @if ($entries->isEmpty())
            <div class="alert alert-info">
                No scores yet. Run <code>artisan leaderboard:compute</code> to populate.
            </div>
        @else
            <div class="lb-card">
                <div class="lb-row lb-head">
                    <span>#</span>
                    <span>{{ $boards[$board] }}</span>
                    <span class="lb-score-col">Score</span>
                </div>

                @foreach ($entries as $i => $entry)
                    @php
                        $profile = $profiles->get($entry->login);
                        $name = $profile?->name ?: $entry->login;
                        $breakdown = $entry->breakdown ?? [];
                        $hasBreakdown = ! empty($breakdown);
                    @endphp
                    <div class="lb-row">
                        <span class="lb-rank">{{ $entry->rank ?? $i + 1 }}</span>

                        <span class="lb-contributor">
                            <a href="https://github.com/{{ $entry->login }}" target="_blank" rel="noopener"
                               class="lb-avatar" title="GitHub profile — {{ $name }}"
                               aria-label="GitHub profile — {{ $name }}">
                                <span class="lb-avatar-initials">{{ $initials($name) }}</span>
                                <img src="https://avatars.githubusercontent.com/{{ $entry->login }}?s=68"
                                     alt="" width="34" height="34" loading="lazy"
                                     onerror="this.remove()">
                            </a>
                            <span class="lb-namewrap">
                                <span class="lb-nameline">
                                    <span class="lb-name">{{ $name }}</span>
                                    <span class="lb-handle">{{ '@'.$entry->login }}</span>
                                    @if (($entry->active ?? true) === false)
                                        <span class="badge text-bg-secondary" title="No longer on the maintainer team">Inactive</span>
                                    @endif
                                </span>
                                @if ($entry->score > 0)
                                    <a href="{{ route('leaderboard.detail', ['board' => $board, 'login' => $entry->login]) }}"
                                       class="lb-count">{{ $countLabel($breakdown) }}</a>
                                @endif
                            </span>
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
            </div>
        @endif
    </div>

    @include('leaderboard._scoring-modal')
@endsection

@push('head')
    @include('leaderboard._lb-styles')
@endpush
