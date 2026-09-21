@extends('layouts.app')

@php
    use App\DataTransferObjects\Leaderboard\Action;
    use Carbon\Carbon;

    $monthFull = Carbon::createFromFormat('!Y-m', $ym)->format('F Y');

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
            Ranked by activity in {{ $monthFull }} — bigger changes count for more, with no recency decay.
            Points come from {{ $scoring['scoredList'] }}.
        </p>

        <button type="button" class="lb-tallied" data-bs-toggle="modal" data-bs-target="#scoringModal">
            How are scores tallied?
        </button>

        @include('leaderboard._tabs')

        <div class="lb-months">
            @foreach ($months as $month)
                <a href="{{ route('leaderboard.monthly', ['board' => $board, 'ym' => $month['ym']]) }}"
                   class="lb-month {{ $month['active'] ? 'active' : '' }}">
                    {{ $month['label'] }}
                </a>
            @endforeach
        </div>

        @if ($entries->isEmpty())
            <div class="alert alert-info">
                No scored activity for {{ $monthFull }}.
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
                                </span>
                                @if ($entry->score > 0)
                                    <a href="{{ route('leaderboard.monthly.detail', ['board' => $board, 'ym' => $ym, 'login' => $entry->login]) }}"
                                       class="lb-count">See contributions</a>
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
