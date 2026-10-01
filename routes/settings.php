<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\Settings\ApiKeysController;
use App\Http\Controllers\Settings\ConnectionsController;
use App\Http\Controllers\Settings\NativeTrackingController;
use App\Http\Controllers\Settings\NotificationPreferencesController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SyncPipelinesController;
use App\Http\Controllers\Settings\WorkspaceSettingsController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/workspace', [WorkspaceSettingsController::class, 'showOverview'])->name('settings.workspace');
    Route::patch('settings/workspace', [WorkspaceSettingsController::class, 'update'])->name('settings.workspace.update');
    Route::put('settings/workspace/timezone', [WorkspaceSettingsController::class, 'updateTimezone'])->name('settings.workspace.timezone');
    Route::get('settings/workspace/members', [WorkspaceSettingsController::class, 'showMembers'])->name('settings.workspace.members');
    Route::post('settings/workspace/invite', [WorkspaceSettingsController::class, 'inviteUser'])->name('settings.workspace.invite');
    Route::patch('settings/workspace/members/{membership}', [WorkspaceSettingsController::class, 'updateMemberRole'])->name('settings.workspace.members.update');
    Route::delete('settings/workspace/members/{membership}', [WorkspaceSettingsController::class, 'removeMember'])->name('settings.workspace.members.remove');
    Route::delete('settings/workspace/invitations/{invitation}', [WorkspaceSettingsController::class, 'cancelInvitation'])->name('settings.workspace.invitations.cancel');

    Route::get('settings/workspace/api-keys', [ApiKeysController::class, 'index'])->name('settings.workspace.api-keys');
    Route::post('settings/workspace/api-keys', [ApiKeysController::class, 'store'])->name('settings.workspace.api-keys.store');
    Route::delete('settings/workspace/api-keys/{apiKey}', [ApiKeysController::class, 'destroy'])->name('settings.workspace.api-keys.destroy');

    Route::get('sync', [SyncPipelinesController::class, 'index'])->name('sync.index');
    Route::post('sync', [SyncPipelinesController::class, 'store'])->name('sync.store');
    Route::patch('sync/{syncPipeline}', [SyncPipelinesController::class, 'update'])->name('sync.update');
    Route::delete('sync/{syncPipeline}', [SyncPipelinesController::class, 'destroy'])->name('sync.destroy');

    Route::post('sync/native-tracking/{account}', [NativeTrackingController::class, 'store'])->name('sync.native-tracking.store');
    Route::delete('sync/native-tracking/{account}', [NativeTrackingController::class, 'destroy'])->name('sync.native-tracking.destroy');

    Route::get('settings/connections', [ConnectionsController::class, 'edit'])->name('connections.edit');
    Route::delete('settings/connections/{socialAccount}', [ConnectionsController::class, 'destroy'])->name('connections.destroy');

    Route::get('settings/notifications', [NotificationPreferencesController::class, 'edit'])->name('notifications.preferences');
    Route::put('settings/notifications', [NotificationPreferencesController::class, 'update'])->name('notifications.preferences.update');

    // Instance settings now live in the SPA (/app/*) — keep the old URLs
    // working for bookmarks and stale links.
    Route::redirect('settings/instance', '/app/settings/instance');
    Route::redirect('settings/instance/polling', '/app/settings/instance/polling');
    Route::redirect('settings/instance/platforms', '/app/settings/instance/platforms');
    Route::redirect('settings/instance/usage', '/app/settings/instance/usage');
    Route::redirect('settings/instance/admins', '/app/settings/instance/admins');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/workspace/subscription', [BillingController::class, 'index'])->name('billing.index');
    Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::post('billing/portal', [BillingController::class, 'portal'])->name('billing.portal');

    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
