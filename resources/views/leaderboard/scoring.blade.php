@extends('layouts.app')

@section('content')
    <div class="sc-page">
        <p class="sc-intro">
            Every contribution on the leaderboard earns points. Here's exactly how each board is
            scored — the base points per action, the multipliers that scale them, and a worked
            example. The rolling 12-month boards decay older work; the monthly boards do not.
        </p>

        @foreach ($scorings as $board => $scoring)
            <section class="sc-section">
                <h2 class="sc-board">{{ $boards[$board] }} board</h2>
                @include('leaderboard._scoring-content', ['scoring' => $scoring, 'boards' => $boards, 'board' => $board])
            </section>
        @endforeach
    </div>
@endsection
