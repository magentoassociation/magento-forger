@extends('layouts.app')

@section('content')
    {{-- Section 1 — Hero --}}
    <section class="text-center py-5 mb-4">
        <h2 class="display-5 fw-bold mb-3">Help build Magento Open Source</h2>
        <p class="lead text-gray-600 mx-auto mb-4" style="max-width: 46rem;">
            Magento powers thousands of stores worldwide — and it's maintained in the open by
            developers like you. Pick an issue, open a pull request, and ship a fix that real
            merchants will use.
        </p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="#choose-how" class="btn btn-primary btn-lg">Find an issue to work on →</a>
            @auth
                <a href="{{ route('leaderboard') }}" class="btn btn-outline-primary btn-lg">View the leaderboard →</a>
            @else
                <a href="{{ route('github_login') }}" class="btn btn-outline-primary btn-lg">
                    <i class="fab fa-github"></i> Login with GitHub
                </a>
            @endauth
        </div>
    </section>

    {{-- Section 2 — Why contribute --}}
    <section class="mb-5">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <div class="bg-white p-4 shadow rounded-xl h-100">
                    <h4 class="text-lg font-medium mb-2">Make real impact</h4>
                    <p class="text-gray-600 mb-0">Your fix ships to a platform behind thousands of live storefronts.</p>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="bg-white p-4 shadow rounded-xl h-100">
                    <h4 class="text-lg font-medium mb-2">Level up</h4>
                    <p class="text-gray-600 mb-0">Work on a large, modern PHP codebase alongside experienced maintainers.</p>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <a href="{{ route('leaderboard') }}" class="d-block bg-white p-4 shadow rounded-xl h-100 text-decoration-none text-gray-900">
                    <h4 class="text-lg font-medium mb-2">Get recognized</h4>
                    <p class="text-gray-600 mb-0">Every merged PR moves you up the contributor leaderboard. →</p>
                </a>
            </div>
        </div>
    </section>

    {{-- Section 3 — Choose how you want to help --}}
    <section id="choose-how" class="mb-5">
        <h3 class="text-2xl font-semibold mb-1">Choose how you want to help</h3>
        <p class="text-gray-600 mb-4">Whatever your experience level, there's a way in.</p>
        <div class="row g-4">
            @foreach ($paths as $path)
                <div class="col-12 col-lg-4">
                    <x-issue-card
                        :href="$path['url']"
                        :title="$path['title']"
                        :icon="$path['icon']"
                        :blurb="$path['blurb']"
                        :cta="$path['cta']"
                        :count="$path['count']" />
                </div>
            @endforeach
        </div>
    </section>

    {{-- Section 4 — Contribute by area --}}
    @if (! empty($areas))
        <section class="mb-5">
            <h3 class="text-2xl font-semibold mb-1">Pick your area</h3>
            <p class="text-gray-600 mb-4">Jump straight to open issues in the part of Magento you know best.</p>
            <div class="row g-3">
                @foreach ($areas as $area)
                    <div class="col-6 col-md-4 col-lg-3">
                        <x-issue-card :href="$area['url']" :title="$area['name']" :count="$area['count']" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Section 5 — Momentum (social proof) --}}
    <section class="mb-5">
        <h3 class="text-2xl font-semibold mb-1">Momentum</h3>
        <p class="text-gray-600 mb-4">Contributors open and merge pull requests every month. Join them.</p>
        @if ($dataMissing)
            <x-data-missing>
                The OpenSearch indices are empty or missing. Run
                <code>ddev artisan sync:github:prs</code> and
                <code>ddev artisan sync:github:issues</code> to populate them.
            </x-data-missing>
        @endif
        <div class="bg-white p-4 shadow rounded-xl">
            <canvas id="prChart" class="w-full" style="height: 360px;"></canvas>
        </div>
    </section>

    {{-- Section 6 — First time contributing? --}}
    <section class="mb-5">
        <div class="bg-white p-4 p-md-5 shadow rounded-xl">
            <h3 class="text-2xl font-semibold mb-3">First time contributing?</h3>
            <p class="text-gray-600">New here? Start in three steps:</p>
            <ol class="text-gray-700 mb-3">
                <li>Read the
                    <a href="{{ $links['contributing'] }}" target="_blank" rel="noopener">Contribution Guidelines</a>
                </li>
                <li>Set up your
                    <a href="{{ $links['dev_setup'] }}" target="_blank" rel="noopener">development environment</a>
                </li>
                <li><a href="#choose-how">Claim an issue above</a> and open your first PR</li>
            </ol>
            <p class="text-gray-600 mb-0">
                Tidy-up win: help by
                <a href="{{ route('labels-PRsWithoutComponentLabel') }}">labeling PRs that are missing a component</a>.
            </p>
        </div>
    </section>

    {{-- Section 7 — Footer CTA --}}
    <section class="text-center py-5">
        <h3 class="text-2xl font-semibold mb-4">Ready to ship your first fix?</h3>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="#choose-how" class="btn btn-primary btn-lg">Find an issue →</a>
            @auth
                <a href="{{ route('leaderboard') }}" class="btn btn-outline-primary btn-lg">View the leaderboard →</a>
            @else
                <a href="{{ route('github_login') }}" class="btn btn-outline-primary btn-lg">
                    <i class="fab fa-github"></i> Login with GitHub
                </a>
            @endauth
        </div>
    </section>
@endsection

@push('scripts')
    @include('components.charts.github-stats', ['monthlyStats' => $monthlyStats])
@endpush