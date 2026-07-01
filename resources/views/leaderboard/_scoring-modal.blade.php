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
                        Each action earns a base number of points. Every point is then multiplied by a
                        <strong>recency factor</strong> that decays over time, so recent work counts for more.
                    </p>
                @else
                    <p class="text-muted small">
                        Each action earns a base number of points, scaled by the size of the change.
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
                                        <span class="badge text-bg-light border ms-1" title="Scaled by the size of the change">× impact</span>
                                    @endif
                                    @if ($action === 'pr_claimed')
                                        <span class="badge text-bg-light border ms-1" title="Scaled by how long the PR sat unclaimed">× staleness</span>
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
                <ul class="small mb-0">
                    <li class="mb-2">
                        <strong>Bigger changes count for more.</strong> Anything tagged
                        <span class="badge text-bg-light border">× impact</span> is boosted by how much code it
                        touched — from {{ $scoring['impact']['min'] }}× for a small tweak up to
                        {{ $scoring['impact']['max'] }}× for a large change.
                    </li>
                    @if ($decay)
                        <li class="mb-2">
                            <strong>Recent work counts for more.</strong> A contribution's value fades over time:
                            it's worth half as much after {{ $scoring['recency']['half_life_days'] }} days, and anything
                            older than {{ $scoring['recency']['window_days'] }} days no longer counts.
                        </li>
                    @endif
                    @if ($board === 'maintainer')
                        <li class="mb-2">
                            <strong>Picking up neglected work counts for more.</strong> Claiming a PR that's been
                            waiting for review is boosted by how long it sat untouched.
                        </li>
                    @endif
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
