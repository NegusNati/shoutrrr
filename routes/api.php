<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountSetsController;
use App\Http\Controllers\Api\V1\AuthOptionsController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\ConnectedAccountsController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\NotificationsController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PostActionsController;
use App\Http\Controllers\Api\V1\PostingScheduleController;
use App\Http\Controllers\Api\V1\PostsController;
use App\Http\Controllers\Api\V1\SharesController;
use App\Http\Controllers\Api\V1\WorkspaceInvitationsController;
use App\Http\Controllers\Api\V1\WorkspaceMentionsController;
use App\Http\Controllers\Api\V1\WorkspacesController;
use App\Http\Middleware\RecordApiUsage;
use App\Http\Middleware\RequireWriteScope;
use App\Http\Middleware\ResolveApiWorkspace;
use Illuminate\Support\Facades\Route;

// Public: the SPA's auth pages render before login and need the feature flags
// (registration enabled, social providers, password rules) without a session.
Route::get('auth/options', [AuthOptionsController::class, 'show']);

// Session-only: invitation accept/deny is keyed to the invitee's user account,
// not a workspace — kept outside the workspace-scoped group.
Route::middleware(['auth:api,sanctum', 'throttle:api'])->group(function (): void {
    Route::post('workspace-invitations/{invitation}/accept', [WorkspaceInvitationsController::class, 'accept']);
    Route::delete('workspace-invitations/{invitation}', [WorkspaceInvitationsController::class, 'deny']);
});

// Dual-auth: Passport API keys (auth:api) for external automation OR session
// cookies (auth:sanctum) for the first-party SPA.
Route::middleware(['auth:api,sanctum', ResolveApiWorkspace::class, 'throttle:api', RecordApiUsage::class])
    ->group(function (): void {
        Route::get('me', [MeController::class, 'show']);
        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::get('workspaces', [WorkspacesController::class, 'index']);
        Route::post('workspaces', [WorkspacesController::class, 'store']);
        Route::post('workspaces/switch', [WorkspacesController::class, 'switch']);

        Route::post('onboarding/welcomed', [OnboardingController::class, 'welcomed']);
        Route::post('onboarding/dismiss', [OnboardingController::class, 'dismiss']);
        Route::post('onboarding/steps/complete', [OnboardingController::class, 'completeStep']);

        Route::get('workspace-mentions', [WorkspaceMentionsController::class, 'index']);
        Route::delete('workspace-mentions/{workspaceMention}', [WorkspaceMentionsController::class, 'destroy']);

        Route::get('notifications', [NotificationsController::class, 'index']);
        Route::delete('notifications', [NotificationsController::class, 'destroyAll']);
        Route::post('notifications/read-all', [NotificationsController::class, 'markAllRead']);
        Route::delete('notifications/{notification}', [NotificationsController::class, 'destroy']);
        Route::post('notifications/{notification}/read', [NotificationsController::class, 'markRead']);

        Route::get('connected-accounts', [ConnectedAccountsController::class, 'index']);
        Route::get('posts', [PostsController::class, 'index']);
        Route::get('posts/{id}', [PostsController::class, 'show']);
        Route::get('account-sets', [AccountSetsController::class, 'index']);
        Route::get('calendar', [CalendarController::class, 'index']);
        Route::get('posting-schedule', [PostingScheduleController::class, 'show']);
        Route::get('posts/{id}/shares', [SharesController::class, 'index']);

        Route::middleware(RequireWriteScope::class)->group(function (): void {
            Route::post('posts', [PostsController::class, 'store']);
            Route::patch('posts/{id}', [PostsController::class, 'update']);
            Route::delete('posts/{id}', [PostsController::class, 'destroy']);

            Route::post('posts/{id}/schedule', [PostActionsController::class, 'schedule']);
            Route::post('posts/{id}/queue', [PostActionsController::class, 'queue']);
            Route::post('posts/{id}/publish', [PostActionsController::class, 'publish']);
            Route::post('posts/{id}/targets/{targetId}/retry', [PostActionsController::class, 'retry']);
            Route::post('posts/{id}/duplicate', [PostsController::class, 'duplicate']);

            Route::post('posts/{id}/shares', [SharesController::class, 'store']);
            Route::delete('posts/{id}/shares/{shareId}', [SharesController::class, 'destroy']);

            Route::post('media', [MediaController::class, 'store']);
            Route::delete('media/{mediaId}', [MediaController::class, 'destroy']);

            Route::post('account-sets', [AccountSetsController::class, 'store']);
            Route::patch('account-sets/{set}', [AccountSetsController::class, 'update']);
            Route::delete('account-sets/{set}', [AccountSetsController::class, 'destroy']);
        });
    });
