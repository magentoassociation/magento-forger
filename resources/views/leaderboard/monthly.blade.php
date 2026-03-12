@extends('layouts.app')

@section('content')
    @php
        $monthNames = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthNames[$m] = \Carbon\Carbon::createFromDate(null, $m, 1)->translatedFormat('F');
        }
    @endphp
    <div class="container">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ route('leaderboard') }}" class="btn btn-sm btn-outline-secondary mb-3">
                    <i class="fas fa-arrow-left"></i> Back to All Years
                </a>
                <h2>Company Leaderboard - {{ $year }}</h2>
                <p class="text-muted">Top companies by contribution points per month</p>
            </div>
        </div>

        <div class="accordion mb-4" id="leaderboardFAQ">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqContent" aria-expanded="false" aria-controls="faqContent">
                        <i class="fas fa-question-circle me-2"></i>
                        How do companies claim their contributions?
                    </button>
                </h2>
                <div id="faqContent" class="accordion-collapse collapse" data-bs-parent="#leaderboardFAQ">
                    <div class="accordion-body">
                        <h6 class="fw-bold">How to link your GitHub account to your company</h6>
                        <p>
                            Log in with your GitHub account and visit the <a href="{{ route('employment') }}">Employment page</a> to link your contributions to your company. Your company will automatically appear on the leaderboard once you've linked your account.
                        </p>

                        <h6 class="fw-bold mt-3">How points are calculated</h6>
                        <p>Points are awarded for contributions to the Magento 2 project:</p>
                        <ul>
                            <li>Pull requests (PRs)</li>
                            <li>Issues created</li>
                            <li>Code reviews</li>
                            <li>Other community contributions</li>
                        </ul>

                        <h6 class="fw-bold mt-3">What does "unclaimed by company" mean?</h6>
                        <p>
                            These are contributions from developers who haven't linked their GitHub account to a company on their Employment page. These points could be yours! Make sure your team members link their accounts.
                        </p>

                        <h6 class="fw-bold mt-3">Getting your company officially recognized</h6>
                        <p>
                            If your company isn't listed, you can login and propose it for approval. Once added, team members can link their contributions to your company.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
        @foreach($data as $monthNumber => $companies)
            @php
                $unclaimedCompany = null;
                $maxVisible = 3;
                $visibleCompanies = array_filter($companies, fn($c) => $c['name'] !== 'unclaimed by company');
                $companyIndex = 0;
            @endphp
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-0 fw-bold">{{ $monthNames[$monthNumber] }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                        @foreach($companies as $company)
                            @if ($company['name'] === 'unclaimed by company')
                                @php
                                    $unclaimedCompany = $company;
                                @endphp
                            @endif
                            @if($company['name'] !== 'unclaimed by company')
                                <li class="list-group-item d-flex justify-content-between align-items-center {{ $company['name'] === 'Adobe' ? 'bg-danger-subtle' : '' }} {{ $companyIndex >= $maxVisible ? 'collapse-item d-none' : '' }}" data-month="{{ $monthNumber }}">
                                    <span class="fw-medium">{{ $company['name'] }}</span>
                                    <span class="badge text-bg-success rounded-pill">{{ number_format($company['points']) }}</span>
                                </li>
                                @php $companyIndex++; @endphp
                            @endif
                        @endforeach
                            @if(count($visibleCompanies) > $maxVisible)
                                <li class="list-group-item text-center">
                                    <button class="btn btn-sm btn-outline-primary expand-btn" data-month="{{ $monthNumber }}" onclick="toggleMonthGroup({{ $monthNumber }})">
                                        <i class="fas fa-chevron-down"></i> Show {{ count($visibleCompanies) - $maxVisible }} more
                                    </button>
                                </li>
                            @endif
                            @if ($unclaimedCompany)
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-warning-subtle text-danger">
                                <span class="fw-medium">{{ $unclaimedCompany['name'] }}</span>
                                <span class="badge text-bg-warning rounded-pill">{{ number_format($unclaimedCompany['points']) }}</span>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        @endforeach
        </div>
    </div>
@endsection

@push('scripts')
<script>
function toggleMonthGroup(monthNumber) {
    const button = document.querySelector(`.expand-btn[data-month="${monthNumber}"]`);
    const hiddenItems = document.querySelectorAll(`.collapse-item[data-month="${monthNumber}"]`);
    const isExpanded = !hiddenItems[0].classList.contains('d-none');

    hiddenItems.forEach(item => {
        item.classList.toggle('d-none');
    });

    if (isExpanded) {
        const count = hiddenItems.length;
        button.innerHTML = `<i class="fas fa-chevron-down"></i> Show ${count} more`;
    } else {
        button.innerHTML = `<i class="fas fa-chevron-up"></i> Show less`;
    }
}
</script>
@endpush
