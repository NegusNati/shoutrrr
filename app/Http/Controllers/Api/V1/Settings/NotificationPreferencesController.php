<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferencesController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'preferences' => $request->user()->notificationPreferences()->toArray(),
            'alwaysOn' => collect(NotificationType::cases())
                ->filter(fn (NotificationType $t): bool => $t->inAppAlwaysOn())
                ->map(fn (NotificationType $t): string => $t->value)
                ->values()
                ->all(),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $request->user()->update([
            'notification_preferences' => NotificationPreferences::fromArray(
                $request->validated('preferences'),
            ),
        ]);

        return response()->json(['preferences' => $request->user()->notificationPreferences()->toArray()]);
    }
}
