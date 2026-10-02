<?php

declare(strict_types=1);

use App\Support\SpaRedirect;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('engagement', SpaRedirect::to('/engagement'))
        ->middleware('engagement.enabled')
        ->name('engagement.index');
    // {replyId} is deliberately NOT bound: stale links to deleted/foreign replies
    // should reach the SPA (deep-linked by id) rather than 404 here.
    Route::get('engagement/{replyId}/thread', fn (string $replyId) => redirect("/app/engagement?reply={$replyId}"))
        ->middleware('engagement.enabled')->name('engagement.thread');
});
