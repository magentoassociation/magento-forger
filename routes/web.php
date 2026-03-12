<?php
declare(strict_types=1);

use App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [Controllers\WelcomeController::class, 'index'])->name('home');
Route::get('issuesByMonth', [Controllers\IssuesByMonthController::class, 'index'])->name('issues-issuesByMonth');
Route::get('prsByMonth', [Controllers\PrsByMonthController::class, 'index'])->name('prs-PRsByMonth');
Route::middleware(['is_admin'])->group(function () {
    Route::get('labels/allLabels', [Controllers\LabelController::class, 'listAllLabels'])->name('labels-listAllLabels');
    Route::get('labels/process-labels', [Controllers\LabelController::class, 'processLabels'])->name('labels-processLabels');
});
Route::post('labels/process-labels', [Controllers\LabelController::class, 'uploadLabels'])->name('labels-uploadLabels');
Route::get('labels/prsMissingComponent', [Controllers\LabelController::class, 'listPrWithoutComponentLabel'])->name('labels-PRsWithoutComponentLabel');
Route::get('leaderboard', [Controllers\LeaderboardController::class, 'index'])->name('leaderboard');
Route::get('leaderboard/{year}', [Controllers\LeaderboardController::class, 'showYear'])->where('year', '[0-9]+')->name('leaderboard-year');
Route::get('/api/charts/{method}', [Controllers\ChartController::class, 'dispatch']);
Route::get('/api/universe-bar', [Controllers\UniverseBarController::class, 'render']);

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

// Render employment form
Route::middleware('auth')->group(function () {
    Route::get('/employment', [Controllers\EmploymentController::class, 'create'])->name('employment');
    Route::post('/employment', [Controllers\EmploymentController::class, 'store']);
    Route::get('/employment/{id}/edit', [Controllers\EmploymentController::class, 'edit'])->name('employment.edit');
    Route::put('/employment/{id}', [Controllers\EmploymentController::class, 'update'])->name('employment.update');
    Route::delete('/employment/{id}', [Controllers\EmploymentController::class, 'destroy'])->name('employment.destroy');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    // Company Owner Management
    Route::get('/my-companies', [Controllers\CompanyOwnerController::class, 'index'])->name('company-owner.index');
    Route::get('/my-companies/{id}/edit', [Controllers\CompanyOwnerController::class, 'edit'])->name('company-owner.edit');
    Route::put('/my-companies/{id}', [Controllers\CompanyOwnerController::class, 'update'])->name('company-owner.update');
    Route::post('/api/companies/propose', [Controllers\Api\CompanyProposalController::class, 'propose'])
        ->middleware('throttle:30,60'); // 30 submissions per hour per user
});
