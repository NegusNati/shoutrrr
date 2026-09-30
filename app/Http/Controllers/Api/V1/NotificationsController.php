<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationsController extends Controller
{
    /**
     * Cursor-paginated notifications for the current workspace, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $cursor = $request->query('cursor');

        return response()->json(NotificationPresenter::collection(
            $request->user(),
            $request->user()->current_workspace_id,
            is_string($cursor) ? $cursor : null,
        ));
    }

    public function markRead(Request $request, string $notification): Response
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        return response()->noContent();
    }

    /**
     * Mark all unread notifications for the current workspace as read.
     */
    public function markAllRead(Request $request): Response
    {
        $workspaceId = $request->user()->current_workspace_id;

        $request->user()
            ->unreadNotifications()
            ->where(function ($query) use ($workspaceId): void {
                $query->where('data->workspace_id', $workspaceId)
                    ->orWhereNull('data->workspace_id');
            })
            ->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function destroy(Request $request, string $notification): Response
    {
        $request->user()->notifications()->findOrFail($notification)->delete();

        return response()->noContent();
    }

    /**
     * Delete all notifications for the current workspace.
     */
    public function destroyAll(Request $request): Response
    {
        $workspaceId = $request->user()->current_workspace_id;

        $request->user()
            ->notifications()
            ->where(function ($query) use ($workspaceId): void {
                $query->where('data->workspace_id', $workspaceId)
                    ->orWhereNull('data->workspace_id');
            })
            ->delete();

        return response()->noContent();
    }
}
