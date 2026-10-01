<?php

declare(strict_types=1);

use App\Http\Controllers\Posts\PostMediaContentController;
use App\Support\SpaRedirect;
use Illuminate\Support\Facades\Route;

// Only browser-facing GETs remain here: legacy deep-links bounce into the SPA,
// and media/{media}/raw is the same-origin proxy the SPA editors fetch (the
// storage bucket serves display URLs without CORS headers, which blocks the
// canvas-based image/video editors). Every mutation moved to /api/v1.
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('analytics', SpaRedirect::to('/analytics'))->middleware('metrics.enabled')->name('analytics.index');

    Route::get('calendar', SpaRedirect::to('/calendar'))->name('calendar.index');
    Route::get('calendar/{yyyymm}', fn (string $yyyymm) => redirect("/app/calendar?month={$yyyymm}"))
        ->where('yyyymm', '\d{4}-\d{2}')->name('calendar.month');

    Route::get('queue', SpaRedirect::to('/queue'))->name('queue.show');

    Route::get('posts', SpaRedirect::to('/posts'))->name('posts.index');
    // {postId} is deliberately unbound: this redirect must also work for stale
    // links to deleted/foreign posts — the SPA renders its own 404 instead of a
    // bare framework error page.
    Route::get('posts/{postId}', fn (string $postId) => redirect("/app/posts/{$postId}"))->name('posts.show');

    Route::get('media/{media}/raw', [PostMediaContentController::class, 'show'])->name('media.raw');
});
