@extends('layouts.app')

@section('content')
    @if ($dataMissing)
        <x-data-missing>
            The pull-requests index is empty or missing. Run
            <code>ddev artisan sync:github:prs</code> to populate it.
        </x-data-missing>
    @endif

    @php
        $currentYear = date('Y');
        $currentMonth = date('m');
    @endphp
    <div class="container">
    @if(empty($prs) && ! $dataMissing)
        <div class="alert alert-info text-center">
            <h4>There is no data available, please ensure the import has run.</h4>
        </div>
    @else
        @foreach($prs as $year)
            <div class="row">
                <div class="col-12">
                    <h3>{{ $year['year']  }} <span class="text-secondary">({{ $year['total'] }} PRs)</span> </h3>
                </div>
                @foreach($year['months'] as $month)
                    @if (!($year['year'] == $currentYear && $month['month_number'] > $currentMonth))
                    <div class="col-6 col-sm-4 col-md-3 col-lg-2 col-xl-1 mb-3">
                        <div class="card h-100 text-center calendar-card {{ $month['total'] > 0 ? 'has-link' : '' }} {{ $month['total'] === 0 ? 'opacity-50 pe-none' : '' }}">
                            <div class="card-body p-2">
                                <h6 class="card-title {{$month['total'] > 0 ? 'text-primary' : 'text-muted'}}">{{ $month['month_number'] }}</h6>
                                <p class="card-text small mb-0 {{$month['total'] > 0 ? 'text-primary' : 'text-muted'}}" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $month['total'] }}&nbsp;PRs
                                </p>
                                @if($month['total'] > 0)
                                    <a href="https://github.com/magento/magento2/pulls?q=is%3Apr%20state%3Aopen%20updated%3A{{$month['start']}}..{{$month['end']}}" target="magentoForgerGitHub" class="stretched-link">
                                        <span class="visually-hidden">View PRs</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>
        @endforeach
    @endif
    </div>
@endsection
