@extends('layouts.app')

@section('content')
    <div class="lb">
        <p class="lb-intro">
            Ranked by the last 12 months of activity — recent work and bigger changes count for more.
            Points come from {{ $scoring['scoredList'] }}. Note that scores are subject to change.
            <button type="button" class="lb-tallied" data-bs-toggle="modal" data-bs-target="#scoringModal">How are scores tallied?</button>
        </p>

        @include('leaderboard._tabs')

        @if ($entries->isEmpty())
            <div class="alert alert-info">
                No scores yet. Run <code>artisan leaderboard:compute</code> to populate.
            </div>
        @else
            @php
                $detailUrl = fn (string $login): string => route('leaderboard.detail', ['board' => $board, 'login' => $login]);
            @endphp
            @include('leaderboard._board')
        @endif
    </div>

    @include('leaderboard._scoring-modal')
@endsection

@push('head')
    @include('leaderboard._lb-styles')
@endpush
