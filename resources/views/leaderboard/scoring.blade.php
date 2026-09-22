@extends('layouts.app')

@section('content')
    @php
        $fmt = fn (float $f): string => rtrim(rtrim(number_format($f, 2), '0'), '.').'×';
        $n = fn ($v): string => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        // Merge bonus drives the worked example; the base point key differs per board.
        $mergeAction = ['contributor' => 'pr_merged', 'maintainer' => 'approved_then_merged'];
        // Multipliers are identical on both boards; read them from either.
        $mult = $scorings['contributor'];
        $half = $mult['recency']['half_life_days'] ?? 182;
        $window = $mult['recency']['window_days'] ?? 365;
        $bonus = rtrim(rtrim(number_format($mult['impact']['confirmed_bonus'] ?? 0, 1), '0'), '.');
        $bars = [['h' => 56, 'c' => '#f26322'], ['h' => 28, 'c' => '#f7a97f'], ['h' => 14, 'c' => '#fad4bd'], ['h' => 3, 'c' => '#dfe1e4']];
        $barLabels = ['Today', $half.' days', (2 * $half).' days', $window.'+ days'];
    @endphp

    <div class="hsw">
        <p class="hsw-intro">
            Every contribution on the leaderboard earns points. Here's exactly how each board is
            scored — the base points per action, the multipliers that scale them, and a worked
            example. The rolling 12-month boards decay older work; the monthly boards do not.
        </p>

        <div class="hsw-formula">
            <span class="scm-tok scm-tok--base">Base points</span>
            <span class="scm-op">×</span>
            <span class="scm-tok scm-tok--out">Priority</span>
            <span class="scm-op">×</span>
            <span class="scm-tok scm-tok--out">Recency</span>
            <span class="scm-op">=</span>
            <span class="scm-tok scm-tok--score">Score</span>
            <span class="hsw-formula-cap">Same formula on both boards</span>
        </div>

        <div class="hsw-boards">
            @foreach (['contributor', 'maintainer'] as $board)
                @php
                    $scoring = $scorings[$board];
                    $exBase = $scoring['weights'][$mergeAction[$board]] ?? 0;
                    $exPriority = collect($scoring['impactExamples'])->firstWhere('label', 'Priority: P1')['factor'] ?? 3;
                    $exRecency = $scoring['recencyExamples'][1]['factor'] ?? 0.5;
                    $exResult = $exBase * $exPriority * $exRecency;
                @endphp
                <div class="hsw-col">
                    <h2 class="hsw-h2">{{ $boards[$board] }} Board</h2>
                    <div class="hsw-thead"><span>Action</span><span>Base points</span></div>
                    <div class="scm-rows">
                        @foreach ($scoring['weights'] as $action => $points)
                            <div class="scm-row">
                                <span class="scm-action">
                                    {{ $scoring['labels'][$action] ?? \Illuminate\Support\Str::headline($action) }}
                                    @if (in_array($action, $scoring['impactActions'], true))
                                        <span class="scm-tag">× priority</span>
                                    @endif
                                </span>
                                <span class="scm-val">{{ $points }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="scm-example scm-example--sm">
                        <p class="scm-ex-eyebrow">Worked example</p>
                        <p class="scm-ex-prose">
                            @if ($board === 'maintainer')
                                When a <span class="scm-ex-mono">Priority: P1</span> PR you approved later merges, the
                                <strong>merge bonus</strong> alone earns <strong>{{ $n($exBase) }}</strong> base ×
                                <strong>{{ $n($exPriority) }}×</strong> priority × <strong>{{ $n($exRecency) }}×</strong>
                                recency (≈6 months old) = <strong>{{ $n($exResult) }} pts</strong>, on top of the points
                                for the approval itself. The same work today, before any decay, would be worth twice as much.
                            @else
                                When a <span class="scm-ex-mono">Priority: P1</span> PR you opened merges, the author
                                <strong>merge bonus</strong> alone earns <strong>{{ $n($exBase) }}</strong> base ×
                                <strong>{{ $n($exPriority) }}×</strong> priority × <strong>{{ $n($exRecency) }}×</strong>
                                recency (≈6 months old) = <strong>{{ $n($exResult) }} pts</strong>, on top of the points
                                for opening it. The same work today, before any decay, would be worth twice as much.
                            @endif
                        </p>
                        <div class="scm-ex-eq">
                            <span class="scm-ex-num">{{ $n($exBase) }}</span><span class="scm-ex-op">×</span>
                            <span class="scm-ex-num">{{ $n($exPriority) }}</span><span class="scm-ex-op">×</span>
                            <span class="scm-ex-num">{{ $n($exRecency) }}</span><span class="scm-ex-op">=</span>
                            <span class="scm-ex-result">{{ $n($exResult) }}</span><span class="scm-ex-unit">points</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <section class="hsw-mult">
            <div class="hsw-mult-head">
                <h2 class="hsw-h2">Multipliers</h2>
                <span class="hsw-cap">Identical on both boards</span>
            </div>
            <p class="hsw-lead">
                Base points are multiplied together with the factors below. A multiplier of
                <strong>1×</strong> leaves the score unchanged; higher values boost it.
            </p>

            <div class="hsw-mult-grid">
                <div>
                    <h3 class="scm-sechead">Priority — higher-priority work counts for more</h3>
                    <p class="scm-para">
                        Anything tagged <span class="scm-tag scm-tag--inline">× priority</span> is scaled by the
                        issue/PR's priority label (applied by maintainers). Work with no priority label stays at 1×.
                        Confirmed issues add <strong>+{{ $bonus }}</strong> on top for the person who opened them,
                        up to a {{ $mult['impact']['max'] }}× cap.
                    </p>
                    <div class="scm-chips">
                        @foreach ($mult['impactExamples'] as $ex)
                            <span class="scm-chip">
                                <span class="{{ $loop->last ? 'scm-chip-name' : 'scm-chip-label' }}">{{ preg_replace('/^Priority:\s*/', '', $ex['label']) }}</span>
                                <span class="scm-chip-val">{{ $fmt($ex['factor']) }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h3 class="scm-sechead">Recency — recent work counts for more</h3>
                    <p class="scm-para">
                        Every action fades over time. It's worth half as much after each
                        <strong>{{ $half }}-day</strong> half-life, and anything older than {{ $window }} days
                        no longer counts. Monthly boards are not decayed.
                    </p>
                    <div class="scm-decay">
                        @foreach ($mult['recencyExamples'] as $i => $ex)
                            <div class="scm-decay-col">
                                <div class="scm-bar" style="height: {{ $bars[$i]['h'] }}px; background: {{ $bars[$i]['c'] }};"></div>
                                <span class="scm-decay-val {{ $ex['factor'] == 0 ? 'scm-decay-val--zero' : '' }}">{{ $fmt($ex['factor']) }}</span>
                                <span class="scm-decay-label">{{ $barLabels[$i] ?? $ex['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
