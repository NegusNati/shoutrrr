<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Settings\NotificationPreferencesController;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationsController extends NotificationPreferencesController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->preferencesPayload($user));
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update([
            'notification_preferences' => NotificationPreferences::fromArray(
                $request->validated('preferences'),
            ),
        ]);

        return response()->json(['message' => 'Notification preferences updated.']);
    }
}
