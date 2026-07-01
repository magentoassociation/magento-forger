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
Route::middleware(['is_admin'])->group(function () {
    // todo: we can't modify labels, remove
    Route::get('labels/process-labels', [Controllers\LabelController::class, 'processLabels'])->name('labels-processLabels');
    Route::post('labels/process-labels', [Controllers\LabelController::class, 'uploadLabels'])->name('labels-uploadLabels');
});
// old routes
Route::get('issuesByMonth', [Controllers\IssuesByMonthController::class, 'index'])->name('issues-issuesByMonth');
Route::get('prsByMonth', [Controllers\PrsByMonthController::class, 'index'])->name('prs-PRsByMonth');
Route::get('labels/allLabels', [Controllers\LabelController::class, 'listAllLabels'])->name('labels-listAllLabels');
Route::get('labels/prsMissingComponent', [Controllers\LabelController::class, 'listPrWithoutComponentLabel'])->name('labels-PRsWithoutComponentLabel');
Route::get('/api/charts/{method}', [Controllers\ChartController::class, 'dispatch']);
Route::get('/api/universe-bar', [Controllers\UniverseBarController::class, 'render']);

// new routes
Route::get('leaderboard', [Controllers\LeaderboardController::class, 'index'])->name('leaderboard');
Route::get('leaderboard/{metric}', [Controllers\LeaderboardController::class, 'show'])->name('leaderboard.show');
Route::get('maintainer/leaderboard', [Controllers\MaintainerLeaderboardController::class, 'index'])->name('maintainer.leaderboard');
Route::get('maintainer/leaderboard/{metric}', [Controllers\MaintainerLeaderboardController::class, 'show'])->name('maintainer.leaderboard.show');
Route::get('scores', [Controllers\ScoreLeaderboardController::class, 'index'])->name('scores.index');
Route::get('scores/highlights', [Controllers\ScoreLeaderboardController::class, 'highlights'])->name('scores.highlights');
Route::get('scores/monthly/{board}', [Controllers\ScoreLeaderboardController::class, 'monthlyIndex'])->name('scores.monthly.index');
Route::get('scores/monthly/{board}/{ym}', [Controllers\ScoreLeaderboardController::class, 'monthly'])
    ->where('ym', '[0-9]{4}-[0-9]{2}')->name('scores.monthly');
Route::get('scores/{board}', [Controllers\ScoreLeaderboardController::class, 'show'])->name('scores.show');
Route::get('scores/{board}/user/{login}', [Controllers\ScoreLeaderboardController::class, 'detail'])->name('scores.detail');

// Login page (required by auth middleware)
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

// Github Social Login
Route::get('/auth/github', [Controllers\Auth\LoginController::class, 'redirectToGitHub'])->name('github_login');
Route::get('/auth/github/callback', [Controllers\Auth\LoginController::class, 'handleGitHubCallback'])
    ->middleware('throttle:10,1'); // Limit to 10 attempts per minute per IP

// Logout
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->name('logout');
