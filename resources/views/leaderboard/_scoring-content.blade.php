@php($decay = $scoring['decay'] ?? true)

@if ($decay)
    <p class="sc-lead">
        Each action earns a base number of points, scaled by the issue/PR's
        <strong>priority label</strong>. Every point is then multiplied by a
        <strong>recency factor</strong> that decays over time, so recent work counts for more.
    </p>
@else
    <p class="sc-lead">
        Each action earns a base number of points, scaled by the issue/PR's priority label.
        Monthly totals have <strong>no recency decay</strong> — every action within the
        month counts at full value.
    </p>
@endif

<table class="sc-table">
    <thead>
        <tr>
            <th>Action</th>
            <th class="sc-r" style="width: 110px">Base points</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($scoring['weights'] as $action => $points)
            <tr>
                <td>
                    {{ $scoring['labels'][$action] ?? \Illuminate\Support\Str::headline($action) }}
                    @if (in_array($action, $scoring['impactActions'], true))
                        <span class="sc-badge" title="Scaled by the issue/PR priority label">× priority</span>
                    @endif
                </td>
                <td class="sc-r"><span class="sc-num">{{ $points }}</span></td>
            </tr>
        @empty
            <tr><td colspan="2" class="sc-note">No point values configured.</td></tr>
        @endforelse
    </tbody>
</table>

<h3 class="sc-h">Multipliers</h3>
<p class="sc-note">
    Base points are multiplied together with the factors below. A multiplier of
    <strong>1×</strong> leaves the score unchanged; higher values boost it.
</p>

<div class="sc-block">
    <p class="sc-sub">Higher-priority work counts for more.</p>
    <p class="sc-note">
        Anything tagged <span class="sc-badge">× priority</span> is scaled by the
        issue/PR's priority label (applied by maintainers). Work with no priority label stays at
        1×. Confirmed issues add <strong>+{{ rtrim(rtrim(number_format($scoring['impact']['confirmed_bonus'] ?? 0, 1), '0'), '.') }}</strong>
        on top for the person who opened them, up to a {{ $scoring['impact']['max'] }}× cap.
    </p>
    <table class="sc-table sc-table--compact">
        <tbody>
            @foreach ($scoring['impactExamples'] as $example)
                <tr>
                    <td>{{ $example['label'] }}</td>
                    <td class="sc-r" style="width: 90px"><span class="sc-num">{{ rtrim(rtrim(number_format($example['factor'], 1), '0'), '.') }}×</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($decay)
    <div class="sc-block">
        <p class="sc-sub">Recent work counts for more.</p>
        <p class="sc-note">
            Every action fades over time. It's worth half as much after each
            <strong>{{ $scoring['recency']['half_life_days'] }}-day</strong> half-life, and anything
            older than {{ $scoring['recency']['window_days'] }} days no longer counts.
        </p>
        <table class="sc-table sc-table--compact">
            <tbody>
                @foreach ($scoring['recencyExamples'] as $example)
                    <tr>
                        <td>{{ ucfirst($example['label']) }}</td>
                        <td class="sc-r" style="width: 90px"><span class="sc-num">{{ rtrim(rtrim(number_format($example['factor'], 2), '0'), '.') }}×</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<h3 class="sc-h">Example</h3>
<p class="sc-note sc-note--last">
    @if ($board === 'maintainer')
        When a <span class="sc-num">Priority: P1</span> PR you approved later merges, the
        <strong>merge bonus</strong> alone earns <span class="sc-num">6</span> base × <span class="sc-num">3×</span> priority
        @if ($decay) × <span class="sc-num">0.5×</span> recency (≈6 months old) = <strong>9 pts</strong>@else = <strong>18 pts</strong>@endif,
        on top of the points for the approval itself.
    @else
        When a <span class="sc-num">Priority: P1</span> PR you opened merges, the author
        <strong>merge bonus</strong> alone earns <span class="sc-num">10</span> base × <span class="sc-num">3×</span> priority
        @if ($decay) × <span class="sc-num">0.5×</span> recency (≈6 months old) = <strong>15 pts</strong>@else = <strong>30 pts</strong>@endif,
        on top of the points for opening it.
    @endif
    @if ($decay) The same work today, before any decay, would be worth twice as much. @endif
</p>
