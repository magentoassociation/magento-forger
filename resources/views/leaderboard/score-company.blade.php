@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row mb-3">
            <div class="col-12">
                <p class="text-muted">
                    How organizations stack up over the past year, adding up the same work that earns
                    individual points — opening and merging PRs, filing issues, reviews, and triage.
                    Each contribution is credited to the company the person was with at the time; anyone
                    whose company we don't know is grouped under &ldquo;Unknown&rdquo;.
                </p>
            </div>
        </div>

        @include('leaderboard._tabs')

        @if ($entries->isEmpty())
            <div class="alert alert-info">
                No company scores yet. Run <code>ddev artisan leaderboard:compute</code> to populate.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px">#</th>
                            <th>Organization</th>
                            <th class="text-end">Contributor</th>
                            <th class="text-end">Maintainer</th>
                            <th class="text-end">Members</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $i => $row)
                            <tr>
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td class="fw-medium">{{ $row->organization }}</td>
                                <td class="text-end"><span class="badge text-bg-success rounded-pill">{{ number_format($row->contributor_score, 1) }}</span></td>
                                <td class="text-end"><span class="badge text-bg-primary rounded-pill">{{ number_format($row->maintainer_score, 1) }}</span></td>
                                <td class="text-end text-muted">{{ number_format($row->member_count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
