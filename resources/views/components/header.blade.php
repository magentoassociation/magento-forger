@php
    use App\Helpers\RouteLabelHelper;
    $currentRouteName = Route::currentRouteName();
    $formattedLabel = RouteLabelHelper::formatLabel($currentRouteName);

    // For year-specific leaderboard routes, show "Leaderboard - Year"
    if (isset($year) && $currentRouteName === 'leaderboard-year') {
        $formattedLabel = 'Leaderboard - ' . $year;
    }

    // Weighted score routes: show a readable, board-aware heading.
    if ($currentRouteName === 'leaderboard.show') {
        $boardName = (isset($boards, $board) && isset($boards[$board])) ? $boards[$board] : null;
        $formattedLabel = $boardName ? $boardName . ' Leaderboard' : 'Leaderboard';
    } elseif ($currentRouteName === 'leaderboard.highlights') {
        $formattedLabel = 'Leaderboard Highlights';
    } elseif ($currentRouteName === 'leaderboard.scoring') {
        $formattedLabel = 'How Scores Work';
    } elseif ($currentRouteName === 'leaderboard.monthly') {
        $formattedLabel = 'Monthly Leaderboard';
    } elseif ($currentRouteName === 'leaderboard.monthly.detail') {
        $boardName = (isset($boards, $board) && isset($boards[$board])) ? $boards[$board] : null;
        $base = $boardName ? $boardName . ' Contributions' : 'Contributions';
        $formattedLabel = isset($monthLabel) ? $base . ' — ' . $monthLabel : $base;
    } elseif ($currentRouteName === 'leaderboard.detail') {
        $boardName = (isset($boards, $board) && isset($boards[$board])) ? $boards[$board] : null;
        $formattedLabel = $boardName ? $boardName . ' Contributions' : 'Contributions';
    }
@endphp

<header class="page-title-bar">
    <div class="container mx-auto">
        <h1>{{ $formattedLabel }}</h1>
    </div>
</header>
