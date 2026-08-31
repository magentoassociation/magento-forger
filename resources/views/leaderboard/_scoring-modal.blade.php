@php($decay = $scoring['decay'] ?? true)

<div class="modal fade" id="scoringModal" tabindex="-1" aria-labelledby="scoringModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scoringModalLabel">How {{ strtolower($boards[$board]) }} scores are tallied</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if ($decay)
                    <p class="text-muted small">
                        Each action earns a base number of points, scaled by the issue/PR's
                        <strong>priority label</strong>. Every point is then multiplied by a
                        <strong>recency factor</strong> that decays over time, so recent work counts for more.
                    </p>
                @else
                    <p class="text-muted small">
                        Each action earns a base number of points, scaled by the issue/PR's priority label.
                        Monthly totals have <strong>no recency decay</strong> — every action within the
                        month counts at full value.
                    </p>
                @endif

                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Action</th>
                            <th class="text-end" style="width: 110px">Base points</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($scoring['weights'] as $action => $points)
                            <tr>
                                <td>
                                    {{ $scoring['labels'][$action] ?? \Illuminate\Support\Str::headline($action) }}
                                    @if (in_array($action, $scoring['impactActions'], true))
                                        <span class="badge text-bg-light border ms-1" title="Scaled by the issue/PR priority label">× priority</span>
                                    @endif
                                </td>
                                <td class="text-end"><code>{{ $points }}</code></td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">No point values configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <h6 class="mt-4">Multipliers</h6>
                <p class="text-muted small mb-3">
                    Base points are multiplied together with the factors below. A multiplier of
                    <strong>1×</strong> leaves the score unchanged; higher values boost it.
                </p>

                <div class="mb-4">
                    <p class="mb-1"><strong>Higher-priority work counts for more.</strong></p>
                    <p class="text-muted small mb-2">
                        Anything tagged <span class="badge text-bg-light border">× priority</span> is scaled by the
                        issue/PR's priority label (applied by maintainers). Work with no priority label stays at
                        1×. Confirmed issues add <strong>+{{ rtrim(rtrim(number_format($scoring['impact']['confirmed_bonus'] ?? 0, 1), '0'), '.') }}</strong>
                        on top for the person who opened them, up to a {{ $scoring['impact']['max'] }}× cap.
                    </p>
                    <table class="table table-sm align-middle small mb-0">
                        <tbody>
                            @foreach ($scoring['impactExamples'] as $example)
                                <tr>
                                    <td>{{ $example['label'] }}</td>
                                    <td class="text-end" style="width: 90px"><code>{{ rtrim(rtrim(number_format($example['factor'], 1), '0'), '.') }}×</code></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($decay)
                    <div class="mb-4">
                        <p class="mb-1"><strong>Recent work counts for more.</strong></p>
                        <p class="text-muted small mb-2">
                            Every action fades over time. It's worth half as much after each
                            <strong>{{ $scoring['recency']['half_life_days'] }}-day</strong> half-life, and anything
                            older than {{ $scoring['recency']['window_days'] }} days no longer counts.
                        </p>
                        <table class="table table-sm align-middle small mb-0">
                            <tbody>
                                @foreach ($scoring['recencyExamples'] as $example)
                                    <tr>
                                        <td>{{ ucfirst($example['label']) }}</td>
                                        <td class="text-end" style="width: 90px"><code>{{ rtrim(rtrim(number_format($example['factor'], 2), '0'), '.') }}×</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <h6 class="mt-4">Example</h6>
                <p class="text-muted small mb-0">
                    @if ($board === 'maintainer')
                        Approving a <code>Priority: P1</code> PR that later merges earns
                        <code>6</code> base × <code>3×</code> priority
                        @if ($decay) × <code>0.5×</code> recency (≈6 months old) = <strong>9 pts</strong>@else = <strong>18 pts</strong>@endif.
                    @else
                        Getting a <code>Priority: P1</code> PR merged earns
                        <code>10</code> base × <code>3×</code> priority
                        @if ($decay) × <code>0.5×</code> recency (≈6 months old) = <strong>15 pts</strong>@else = <strong>30 pts</strong>@endif.
                    @endif
                    The same work today, before any decay, would be worth twice as much.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
