<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PlatformLimitsController extends Controller
{
    /**
     * The per-platform composition limits the composer enforces client-side
     * (text length, media counts, video duration) — the same payload
     * ComposerController embeds in the Inertia page.
     */
    public function index(): JsonResponse
    {
        return response()->json(['limits' => Platform::allLimits()]);
    }
}
