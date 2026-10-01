<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Only the public invitation deep-link survives here: it bounces the bearer
// into the SPA. Workspace lifecycle, invitation accept/deny, and onboarding
// actions all moved to /api/v1.
Route::get('invitation/{token}', fn (string $token) => redirect("/app/invitation/{$token}"))
    ->middleware('throttle:5,1')
    ->name('workspace.invitation');
