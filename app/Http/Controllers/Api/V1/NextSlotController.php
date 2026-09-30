<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PostingSchedule;
use App\Models\Workspace;
use App\Services\Posts\NextSlotResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Context;

class NextSlotController extends Controller
{
    public function __construct(private readonly NextSlotResolver $resolver) {}

    public function show(): JsonResponse
    {
        $workspace = Workspace::query()->findOrFail((string) Context::get('workspace_id'));

        /** @var PostingSchedule|null $schedule */
        $schedule = PostingSchedule::query()
            ->where('workspace_id', $workspace->id)
            ->with('slots')
            ->first();

        $hasSchedule = $schedule !== null && $schedule->slots->isNotEmpty();
        $slots = $hasSchedule ? $this->resolver->availableSlots($workspace) : [];
        $slot = $slots[0] ?? null;

        return response()->json([
            'has_schedule' => $hasSchedule,
            'slot' => $slot?->toIso8601String(),
            'slots' => array_map(
                fn ($slot): string => $slot->toIso8601String(),
                $slots,
            ),
            'timezone' => $schedule !== null ? $schedule->timezone : 'UTC',
        ]);
    }
}
