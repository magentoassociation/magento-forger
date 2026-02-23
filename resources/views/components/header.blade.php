@php
    use App\Helpers\RouteLabelHelper;
    $formattedLabel = RouteLabelHelper::formatLabel(Route::currentRouteName());

    // For year-specific leaderboard routes, show "Leaderboard - Year"
    if (isset($year) && Route::currentRouteName() === 'leaderboard-year') {
        $formattedLabel = 'Leaderboard - ' . $year;
    }
@endphp

<header class="bg-white shadow py-3 mb-4">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-light text-gray-800 truncate">
                {{ $formattedLabel }}
            @isset($infoText)
                <a href="#headerInfoCollapse"
                   role="button"
                   class="ml-3 p-1 text-gray-500 hover:text-gray-800 focus:outline-none"
                   data-bs-toggle="collapse"
                   aria-expanded="false"
                   aria-controls="headerInfoCollapse"
                   aria-label="Toggle information panel">

                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" width="24" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </a>
            @endisset
            </h1>
        </div>
    </div>
</header>

@isset($infoText)
    <div class="collapse" id="headerInfoCollapse">
        <div class="container mx-auto px-4">
            <div class="p-4 bg-sky-100 border-l-4 border-sky-500 text-sky-800 mb-4" role="alert">
                <div class="container my-4">
                    <div class="row">
                        <div class="col-md-10 offset-md-1">
                            <div class="card shadow-sm border-0">
                                <div class="card-body">
                                    <h5 class="card-title">{{ $infoText->title }}</h5>
                                    @foreach($infoText->paragraphs as $paragraph)
                                        <p class="card-text">{{ $paragraph }}</p>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endisset
