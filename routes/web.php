<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

use App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [Controllers\WelcomeController::class, 'index'])->name('home');
Route::get('/api/universe-bar', [Controllers\UniverseBarController::class, 'render']);
Route::get('/api/charts/{method}', [Controllers\ChartController::class, 'dispatch']);

// old routes
Route::get('issuesByMonth', [Controllers\IssuesByMonthController::class, 'index'])->name('issues.issuesByMonth');
Route::get('prsByMonth', [Controllers\PrsByMonthController::class, 'index'])->name('prs.PRsByMonth');
Route::get('labels/allLabels', [Controllers\LabelController::class, 'listAllLabels'])->name('labels.listAllLabels');

// new routes
Route::middleware(['is_admin'])->group(function () {
    Route::get('leaderboard', [Controllers\ScoreLeaderboardController::class, 'index'])->name('leaderboard.index');
    Route::get('leaderboard/highlights', [Controllers\ScoreLeaderboardController::class, 'highlights'])->name('leaderboard.highlights');
    Route::get('leaderboard/monthly/{board}', [Controllers\ScoreLeaderboardController::class, 'monthlyIndex'])->name('leaderboard.monthly.index');
    Route::get('leaderboard/monthly/{board}/{ym}', [Controllers\ScoreLeaderboardController::class, 'monthly'])
        ->where('ym', '[0-9]{4}-[0-9]{2}')->name('leaderboard.monthly');
    Route::get('leaderboard/monthly/{board}/{ym}/user/{login}', [Controllers\ScoreLeaderboardController::class, 'monthlyDetail'])
        ->where('ym', '[0-9]{4}-[0-9]{2}')->name('leaderboard.monthly.detail');
    Route::get('leaderboard/{board}', [Controllers\ScoreLeaderboardController::class, 'show'])->name('leaderboard.show');
    Route::get('leaderboard/{board}/user/{login}', [Controllers\ScoreLeaderboardController::class, 'detail'])->name('leaderboard.detail');
});

// Login page (required by auth middleware)
Route::get('/login', static function () {
    return view('auth.login');
})->name('login');

// Github Social Login
Route::get('/auth/github', [Controllers\Auth\LoginController::class, 'redirectToGitHub'])->name('github_login');
Route::get('/auth/github/callback', [Controllers\Auth\LoginController::class, 'handleGitHubCallback'])
    ->middleware('throttle:10,1'); // Limit to 10 attempts per minute per IP

// Logout
Route::post('/logout', static function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->name('logout');
