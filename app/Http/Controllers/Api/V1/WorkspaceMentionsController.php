<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Http\Controllers\WorkspaceMentionController;
use App\Models\WorkspaceMention;
use App\Support\LinkedInOrg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceMentionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => WorkspaceMention::withoutGlobalScopes()
                ->where('workspace_id', $request->user()->current_workspace_id)
                ->orderBy('name')
                ->get()
                ->map(fn (WorkspaceMention $mention): array => WorkspaceMentionController::view($mention))
                ->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $allowedPlatforms = array_map(
            static fn (Platform $platform): string => $platform->value,
            Platform::cases(),
        );

        // Nested rules must cover every platform key: `validated()` strips any
        // `handles.*` key without an explicit rule (excludeUnvalidatedArrayKeys),
        // which silently dropped Meta handles before these rules were derived
        // from the enum (issue #123).
        $handleRules = [];
        foreach ([...$allowedPlatforms, 'linkedin_urn'] as $handleKey) {
            $handleRules['handles.'.$handleKey] = ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^@[A-Za-z0-9_.-]+$/'],
            'handles' => ['required', 'array'],
            ...$handleRules,
        ]);
        $handles = [];
        foreach ($allowedPlatforms as $platform) {
            $handle = trim((string) ($validated['handles'][$platform] ?? ''));
            if ($handle !== '') {
                $handles[$platform] = $handle;
            }
        }

        $linkedinUrn = LinkedInOrg::normalizeUrn($validated['handles']['linkedin_urn'] ?? null);
        if ($linkedinUrn !== null) {
            $handles['linkedin_urn'] = $linkedinUrn;
        }

        $mention = WorkspaceMention::withoutGlobalScopes()->updateOrCreate(
            [
                'workspace_id' => $request->user()->current_workspace_id,
                'name' => $validated['name'],
            ],
            ['handles' => $handles],
        );

        return response()->json(['mention' => WorkspaceMentionController::view($mention)], 201);
    }

    public function destroy(Request $request, string $workspaceMention): JsonResponse
    {
        WorkspaceMention::withoutGlobalScopes()
            ->where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail($workspaceMention)
            ->delete();

        return response()->json(['deleted' => true]);
    }
}
