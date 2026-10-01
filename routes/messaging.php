<?php

declare(strict_types=1);

use App\Support\SpaRedirect;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('messages', SpaRedirect::to('/messages'))->middleware('messages.enabled')->name('messages.index');
    // {conversationId} is deliberately NOT bound: stale links should reach the
    // SPA (deep-linked by id) rather than 404 here.
    Route::get('messages/{conversationId}/thread', fn (string $conversationId) => redirect("/app/messages?conversation={$conversationId}"))->middleware('messages.enabled')->name('messages.thread');
});
