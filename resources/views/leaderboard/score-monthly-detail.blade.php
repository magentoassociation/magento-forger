@extends('layouts.app')

@php
    use Carbon\Carbon;

    $name = $profile?->name ?: $login;
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $initials = collect($words)->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('')
        ?: mb_strtoupper(mb_substr($name, 0, 2));
    $monthFull = Carbon::createFromFormat('!Y-m', $ym)->format('F Y');
@endphp

@section('content')
    @include('leaderboard._detail-header', [
        'scoreLabel' => $monthLabel,
    ])

    <div class="container mx-auto lb lb-detail">
        <p class="lb-d-intro">Every scored contribution in {{ $monthFull }}, grouped by what earned the points — impact-weighted, no recency decay. Each group's points sum to the grand total.</p>
        <p class="lb-d-tallied-row"><button type="button" class="lb-tallied" data-bs-toggle="modal" data-bs-target="#scoringModal">How are scores tallied?</button></p>

        @if ($groups->isEmpty())
            <div class="alert alert-info">No scored contributions in {{ $monthFull }} for <code>{{ $login }}</code>.</div>
        @else
            @foreach ($groups as $group)
                <div class="lb-d-group">
                    <div class="lb-d-grouphead">
                        <h2 class="lb-d-group-name">{{ $group->name }}</h2>
                        <span class="lb-d-group-count">{{ number_format($group->count) }} items</span>
                        <span class="lb-d-group-total">{{ number_format($group->total, 1) }}</span>
                    </div>
                    @foreach ($group->rows as $row)
                        @include('leaderboard._detail-row', ['row' => $row])
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>

    @include('leaderboard._scoring-modal')
@endsection

@push('head')
    @include('leaderboard._lb-styles')
@endpush
