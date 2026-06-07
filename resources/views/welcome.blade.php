@extends('layouts.app')

@section('content')
    @if ($dataMissing)
        <x-data-missing>
            The OpenSearch indices are empty or missing. Run
            <code>ddev artisan sync:github:prs</code> and
            <code>ddev artisan sync:github:issues</code> to populate them.
        </x-data-missing>
    @endif

    <section class="mb-12">
        <div class="container">
            <div class="row">
                <div class="col-12 col-lg-6 mb-4">
                    <div class="bg-white p-4 shadow rounded-xl">
                        <h4 class="text-lg font-medium mb-3 text-blue-700">PR Age Over Time</h4>
                        <canvas id="prAgeOverTime" class="w-full" style="height: 300px;"></canvas>
                    </div>
                </div>
                <div class="col-12 col-lg-6 mb-4">
                    <div class="bg-white p-4 shadow rounded-xl">
                        <h4 class="text-lg font-medium mb-3 text-purple-700">Issue Age Over Time</h4>
                        <canvas id="issueAgeOverTime" class="w-full" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="mb-12">
        <h3 class="text-xl font-medium mb-3 text-blue-700">Pull Requests Over Time</h3>
        <div class="bg-white p-4 shadow rounded-xl">
            <canvas id="prChart" class="w-full" style="height: 400px;"></canvas>
        </div>
    </section>

    <section>
        <h3 class="text-xl font-medium mb-3 text-purple-700">Issues Over Time</h3>
        <div class="bg-white p-4 shadow rounded-xl">
            <canvas id="issueChart" class="w-full" style="height: 400px;"></canvas>
        </div>
    </section>
@endsection

@push('scripts')
    @include('components.charts.github-stats', ['monthlyStats' => $monthlyStats])
@endpush
