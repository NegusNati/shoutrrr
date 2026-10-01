<?php

use App\Support\SpaRedirect;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/app/settings/profile');

    Route::get('settings/profile', SpaRedirect::to('/settings/profile'))->name('profile.edit');
    Route::get('settings/workspace', SpaRedirect::to('/settings/workspace'))->name('settings.workspace');
    Route::get('settings/workspace/members', SpaRedirect::to('/settings/workspace/members'))->name('settings.workspace.members');
    Route::get('settings/workspace/api-keys', SpaRedirect::to('/settings/workspace/api-keys'))->name('settings.workspace.api-keys');
    Route::get('sync', SpaRedirect::to('/settings/sync'))->name('sync.index');
    Route::get('settings/connections', SpaRedirect::to('/settings/connections'))->name('connections.edit');
    Route::get('settings/notifications', SpaRedirect::to('/settings/notifications'))->name('notifications.preferences');
    Route::get('settings/instance', SpaRedirect::to('/settings/instance'))->name('instance-settings.edit');
    Route::get('settings/instance/polling', SpaRedirect::to('/settings/instance/polling'))->name('instance-settings.polling');
    Route::get('settings/instance/platforms', SpaRedirect::to('/settings/instance/platforms'))->name('instance-settings.platforms');
    Route::get('settings/instance/usage', SpaRedirect::to('/settings/instance/usage'))->name('instance-settings.usage');
    Route::get('settings/instance/admins', SpaRedirect::to('/settings/instance/admins'))->name('instance-settings.admins');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/workspace/subscription', SpaRedirect::to('/settings/workspace/subscription'))->name('billing.index');
    Route::get('settings/security', SpaRedirect::to('/settings/security'))
        ->middleware(RequirePassword::class)
        ->name('security.edit');
    Route::get('settings/appearance', SpaRedirect::to('/settings/appearance'))->name('appearance.edit');
});
