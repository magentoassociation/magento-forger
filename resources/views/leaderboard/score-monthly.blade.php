@extends('layouts.app')

@php
    use Carbon\Carbon;

    $monthFull = Carbon::createFromFormat('!Y-m', $ym)->format('F Y');
@endphp

@section('content')
    <div class="lb">
        <p class="lb-intro">
            Ranked by activity in {{ $monthFull }} — bigger changes count for more, with no recency
            decay, so every day of the month counts the same.
            Points come from {{ $scoring['scoredList'] }}.
            <button type="button" class="lb-tallied" data-bs-toggle="modal" data-bs-target="#scoringModal">How are scores tallied?</button>
        </p>

        @include('leaderboard._tabs')

        <div class="lb-months">
            @php $prevYear = null; @endphp
            @foreach ($months as $month)
                @php
                    $chip = Carbon::createFromFormat('!Y-m', $month['ym']);
                    $year = $chip->format('Y');
                    // Selected chip, or the first chip of an earlier year, carries
                    // its year so the year change is readable; the rest show the
                    // month only.
                    $withYear = $month['active'] || ($prevYear !== null && $year !== $prevYear);
                    $prevYear = $year;
                @endphp
                <a href="{{ route('leaderboard.monthly', ['board' => $board, 'ym' => $month['ym']]) }}"
                   class="lb-month {{ $month['active'] ? 'active' : '' }}">{{ $withYear ? $chip->format('M Y') : $chip->format('M') }}</a>
            @endforeach
        </div>

        @if ($entries->isEmpty())
            <div class="alert alert-info">
                No scored activity for {{ $monthFull }}.
            </div>
        @else
            @php
                $detailUrl = fn (string $login): string => route('leaderboard.monthly.detail', ['board' => $board, 'ym' => $ym, 'login' => $login]);
            @endphp
            @include('leaderboard._board')
        @endif
    </div>

    @include('leaderboard._scoring-modal')
@endsection

@push('head')
    @include('leaderboard._lb-styles')
@endpush
