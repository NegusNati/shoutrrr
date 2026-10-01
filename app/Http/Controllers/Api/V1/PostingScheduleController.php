<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostingSchedule\UpdatePostingScheduleRequest;
use App\Models\PostingSchedule;
use App\Models\PostingScheduleSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

class PostingScheduleController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $workspaceId = (string) Context::get('workspace_id');
        $schedule = PostingSchedule::query()
            ->where('workspace_id', $workspaceId)
            ->with('slots')
            ->first();

        // Consistent shape whether or not a schedule exists: timezone is null
        // when unconfigured, slots is always an array.
        return response()->json([
            'timezone' => $schedule?->timezone,
            'canManage' => $request->user()->hasAllPermissions(['workspace.settings.manage'], $workspaceId),
            'slots' => $schedule
                ? $schedule->slots->map(fn (PostingScheduleSlot $slot): array => [
                    'weekday' => $slot->weekday,
                    'hour' => $slot->hour,
                    'minute' => $slot->minute,
                ])
                : [],
        ]);
    }

    /**
     * Replace the whole posting schedule. Body: { slots: [{weekday, hour, minute?}] }.
     * Duplicate weekday:hour:minute entries collapse; positions are reassigned.
     */
    public function update(UpdatePostingScheduleRequest $request): JsonResponse
    {
        $workspaceId = (string) Context::get('workspace_id');

        /** @var array{slots?: list<array{weekday: int, hour: int, minute?: int}>} $data */
        $data = $request->validated();
        $slots = $data['slots'] ?? [];

        DB::transaction(function () use ($workspaceId, $slots): void {
            $schedule = PostingSchedule::query()->firstOrCreate(
                ['workspace_id' => $workspaceId],
            );

            $schedule->slots()->delete();

            $seen = [];
            $position = 0;
            $rows = [];

            foreach ($slots as $slot) {
                $minute = $slot['minute'] ?? 0;
                $key = $slot['weekday'].':'.$slot['hour'].':'.$minute;

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $rows[] = [
                    'weekday' => $slot['weekday'],
                    'hour' => $slot['hour'],
                    'minute' => $minute,
                    'position' => $position++,
                ];
            }

            if ($rows !== []) {
                $schedule->slots()->createMany($rows);
            }
        });

        return $this->show($request);
    }
}
