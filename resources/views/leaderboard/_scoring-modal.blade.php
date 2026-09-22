@php
    $decay = $scoring['decay'] ?? true;
    $fmt = fn (float $f): string => rtrim(rtrim(number_format($f, 2), '0'), '.').'×';
    $half = $scoring['recency']['half_life_days'] ?? 182;
    $window = $scoring['recency']['window_days'] ?? 365;
    $bonus = rtrim(rtrim(number_format($scoring['impact']['confirmed_bonus'] ?? 0, 1), '0'), '.');
    $n = fn ($v): string => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    // Worked example, driven by config: merge bonus base × the P1 priority factor × scenario recency.
    $mergeAction = $board === 'maintainer' ? 'approved_then_merged' : 'pr_merged';
    $exBase = $scoring['weights'][$mergeAction] ?? 0;
    $exPriority = collect($scoring['impactExamples'])->firstWhere('label', 'Priority: P1')['factor'] ?? 3;
    // Maintainer example is "merged today" (1×); contributor example is "≈6 months old" (half-life factor).
    $exRecency = $board === 'maintainer' ? 1.0 : ($scoring['recencyExamples'][1]['factor'] ?? 0.5);
    // Fixed decay-bar geometry per handoff (height px + colour), matched by index to recencyExamples.
    $bars = [['h' => 56, 'c' => '#f26322'], ['h' => 28, 'c' => '#f7a97f'], ['h' => 14, 'c' => '#fad4bd'], ['h' => 3, 'c' => '#dfe1e4']];
    $barLabels = ['Today', $half.' days', (2 * $half).' days', $window.'+ days'];
@endphp
<div class="modal fade scm" id="scoringModal" tabindex="-1" aria-labelledby="scoringModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered scm-dialog" role="dialog" aria-modal="true" aria-labelledby="scoringModalLabel">
        <div class="modal-content scm-panel">
            <div class="scm-header">
                <div class="scm-headtext">
                    <h2 class="scm-title" id="scoringModalLabel">How {{ strtolower($boards[$board]) }} scores are tallied</h2>
                    @if ($decay)
                        <p class="scm-intro">
                            Each action earns a base number of points, scaled by the issue/PR's
                            <strong>priority label</strong>. Every point is then multiplied by a
                            <strong>recency factor</strong> that decays over time, so recent work counts for more.
                        </p>
                    @else
                        <p class="scm-intro">
                            Each action earns a base number of points, scaled by the issue/PR's
                            <strong>priority label</strong>. Monthly totals have
                            <strong>no recency decay</strong> — every action within the month counts at full value.
                        </p>
                    @endif
                </div>
                <button type="button" class="scm-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            </div>

            <div class="scm-formula">
                <span class="scm-tok scm-tok--base">Base points</span>
                <span class="scm-op">×</span>
                <span class="scm-tok scm-tok--out">Priority</span>
                @if ($decay)
                    <span class="scm-op">×</span>
                    <span class="scm-tok scm-tok--out">Recency</span>
                @endif
                <span class="scm-op">=</span>
                <span class="scm-tok scm-tok--score">Score</span>
            </div>

            <div class="modal-body scm-body">
                <div class="scm-grid">
                    <div class="scm-col">
                        <h3 class="scm-sechead">1 · Base points</h3>
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
                    </div>

                    <div class="scm-col">
                        <h3 class="scm-sechead">2 · Priority — higher-priority work counts for more</h3>
                        <p class="scm-para">
                            Anything tagged <span class="scm-tag scm-tag--inline">× priority</span> is scaled by the
                            issue/PR's priority label (applied by maintainers). Work with no priority label stays at 1×.
                            Confirmed issues add <strong>+{{ $bonus }}</strong> on top for the person who opened them,
                            up to a {{ $scoring['impact']['max'] }}× cap.
                        </p>
                        <div class="scm-chips">
                            @foreach ($scoring['impactExamples'] as $i => $ex)
                                @php ($isLast = $loop->last)
                                <span class="scm-chip">
                                    <span class="{{ $isLast ? 'scm-chip-name' : 'scm-chip-label' }}">{{ preg_replace('/^Priority:\s*/', '', $ex['label']) }}</span>
                                    <span class="scm-chip-val">{{ $fmt($ex['factor']) }}</span>
                                </span>
                            @endforeach
                        </div>

                        @if ($decay)
                            <h3 class="scm-sechead scm-sechead--rec">3 · Recency — recent work counts for more</h3>
                            <p class="scm-para">
                                Every action fades over time. It's worth half as much after each
                                <strong>{{ $half }}-day</strong> half-life, and anything older than {{ $window }} days
                                no longer counts.
                            </p>
                            <div class="scm-decay">
                                @foreach ($scoring['recencyExamples'] as $i => $ex)
                                    <div class="scm-decay-col">
                                        <div class="scm-bar" style="height: {{ $bars[$i]['h'] }}px; background: {{ $bars[$i]['c'] }};"></div>
                                        <span class="scm-decay-val {{ $ex['factor'] == 0 ? 'scm-decay-val--zero' : '' }}">{{ $fmt($ex['factor']) }}</span>
                                        <span class="scm-decay-label">{{ $barLabels[$i] ?? $ex['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="scm-example">
                <p class="scm-ex-eyebrow">Example</p>
                @if ($board === 'maintainer')
                    @if ($decay)
                        <p class="scm-ex-prose">
                            A <span class="scm-ex-mono">Priority: P1</span> PR you approved later merges. The merge
                            bonus alone earns <strong>{{ $n($exBase) }}</strong> base points, scaled
                            <strong>{{ $n($exPriority) }}×</strong> for P1 — and if it merged today, the recency factor
                            is <strong>{{ $n($exRecency) }}×</strong>.
                        </p>
                        <div class="scm-ex-eq">
                            <span class="scm-ex-num">{{ $n($exBase) }}</span><span class="scm-ex-op">×</span>
                            <span class="scm-ex-num">{{ $n($exPriority) }}</span><span class="scm-ex-op">×</span>
                            <span class="scm-ex-num">{{ $n($exRecency) }}</span><span class="scm-ex-op">=</span>
                            <span class="scm-ex-result">{{ $n($exBase * $exPriority * $exRecency) }}</span><span class="scm-ex-unit">points</span>
                        </div>
                    @else
                        <p class="scm-ex-prose">
                            A <span class="scm-ex-mono">Priority: P1</span> PR you approved later merges. The merge
                            bonus alone earns <strong>{{ $n($exBase) }}</strong> base points, scaled
                            <strong>{{ $n($exPriority) }}×</strong> for P1. Monthly totals apply no recency decay.
                        </p>
                        <div class="scm-ex-eq">
                            <span class="scm-ex-num">{{ $n($exBase) }}</span><span class="scm-ex-op">×</span>
                            <span class="scm-ex-num">{{ $n($exPriority) }}</span><span class="scm-ex-op">=</span>
                            <span class="scm-ex-result">{{ $n($exBase * $exPriority) }}</span><span class="scm-ex-unit">points</span>
                        </div>
                    @endif
                @else
                    @if ($decay)
                        <p class="scm-ex-prose">
                            When a <span class="scm-ex-mono">Priority: P1</span> PR you opened merges, the author
                            <strong>merge bonus</strong> alone earns <strong>{{ $n($exBase) }}</strong> base ×
                            <strong>{{ $n($exPriority) }}×</strong> priority × <strong>{{ $n($exRecency) }}×</strong>
                            recency (≈6 months old) = <strong>{{ $n($exBase * $exPriority * $exRecency) }} pts</strong>,
                            on top of the points for opening it. The same work today, before any decay, would be worth twice as much.
                        </p>
                        <div class="scm-ex-eq">
                            <span class="scm-ex-num">{{ $n($exBase) }}</span><span class="scm-ex-op">×</span>
                            <span class="scm-ex-num">{{ $n($exPriority) }}</span><span class="scm-ex-op">×</span>
                            <span class="scm-ex-num">{{ $n($exRecency) }}</span><span class="scm-ex-op">=</span>
                            <span class="scm-ex-result">{{ $n($exBase * $exPriority * $exRecency) }}</span><span class="scm-ex-unit">points</span>
                        </div>
                    @else
                        <p class="scm-ex-prose">
                            When a <span class="scm-ex-mono">Priority: P1</span> PR you opened merges, the author
                            <strong>merge bonus</strong> alone earns <strong>{{ $n($exBase) }}</strong> base ×
                            <strong>{{ $n($exPriority) }}×</strong> priority = <strong>{{ $n($exBase * $exPriority) }} pts</strong>,
                            on top of the points for opening it. Monthly totals apply no recency decay.
                        </p>
                        <div class="scm-ex-eq">
                            <span class="scm-ex-num">{{ $n($exBase) }}</span><span class="scm-ex-op">×</span>
                            <span class="scm-ex-num">{{ $n($exPriority) }}</span><span class="scm-ex-op">=</span>
                            <span class="scm-ex-result">{{ $n($exBase * $exPriority) }}</span><span class="scm-ex-unit">points</span>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
