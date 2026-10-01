<?php

use App\Http\Middleware\NoIndex;
use App\Support\SpaRedirect;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::get('/share/{token}', fn (string $token) => redirect("/app/share/{$token}"))
    ->middleware([NoIndex::class, 'throttle:30,1'])
    ->name('share.show')
    ->where('token', '[A-Za-z0-9\-]+');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', SpaRedirect::to('/dashboard'))->name('dashboard');
});

// First-party SPA (TanStack Router, served from /app/*). Mounted outside the
// auth group — the SPA's route guard redirects to /app/login on a 401 from
// /api/v1/me, so the blade shell itself is public like the login page.
Route::get('app/{path?}', fn () => view('spa'))
    ->where('path', '.*')
    ->name('spa');

require __DIR__.'/workspace.php';
require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
require __DIR__.'/accounts.php';
require __DIR__.'/posts.php';
require __DIR__.'/engagement.php';
require __DIR__.'/messaging.php';
