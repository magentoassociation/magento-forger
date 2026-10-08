@extends('layouts.app')

@section('content')
    @if ($dataMissing)
        <x-data-missing>
            The pull-requests index is empty or missing. Run
            <code>ddev artisan sync:github:prs</code> to populate it.
        </x-data-missing>
    @endif

    <div>
        @if(empty($prs) && ! $dataMissing)
            <div class="alert alert-info text-center">
                <h4>There is no data available, please ensure the import has run.</h4>
            </div>
        @else
            <x-by-month :rows="$prs" :info="$infoText" noun="PRs" link-path="pulls" q-type="pr" />
        @endif
    </div>
@endsection
