<?php

declare(strict_types=1);

namespace App\Http\Requests\Post\Concerns;

use App\Enums\PostFormat;
use Illuminate\Validation\Rule;

/**
 * The full composer save payload — shared by the web FormRequests and the
 * /api/v1 posts endpoints so a rule added for one surface can't silently drift
 * from the other (the nested-key stripping hazard in
 * {@see DerivesMentionHandleRules} applies to every layer that validates).
 */
trait PostPayloadRules
{
    use DerivesMentionHandleRules;

    /**
     * Body rules every post save accepts (create and update).
     *
     * @return array<string, mixed>
     */
    protected function postBodyRules(): array
    {
        return [
            'base_text' => ['sometimes', 'nullable', 'string'],
            'segments' => ['present', 'array'],
            'segments.*' => ['nullable', 'string'],
            'mentions' => ['array'],
            'mentions.*.id' => ['required', 'string'],
            'mentions.*.label' => ['required', 'string'],
            'mentions.*.handles' => ['array'],
            ...$this->mentionHandleRules(),
            'destination' => ['required', 'array'],
            'destination.kind' => ['required', Rule::in(['all', 'none', 'set', 'account', 'accounts'])],
            'destination.id' => ['nullable', 'string', 'required_if:destination.kind,set,account'],
            'destination.ids' => ['array', 'required_if:destination.kind,accounts'],
            'destination.ids.*' => ['string'],
            'segment_breaks' => ['array'],
            'segment_breaks.*' => ['string'],
            'placements' => ['array'],
            'placements.*.media_id' => ['required', 'string'],
            'placements.*.segment_ref' => ['required', 'string'],
            'placements.*.position' => ['required', 'integer'],
            'auto_repost' => ['sometimes', 'nullable', 'boolean'],
            'skip_sync' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Extra rules a full post edit accepts on top of the body — per-target
     * overrides, the attached media set, and the optimistic-concurrency token.
     *
     * @return array<string, mixed>
     */
    protected function postEditRules(): array
    {
        return [
            'targets' => ['array'],
            'targets.*.connected_account_id' => ['required', 'string'],
            'targets.*.auto_split' => ['boolean'],
            'targets.*.format' => ['nullable', Rule::enum(PostFormat::class)],
            'targets.*.content_override' => ['nullable', 'array'],
            'targets.*.content_override.text' => ['nullable', 'string'],
            'targets.*.content_override.segments' => ['array'],
            'targets.*.content_override.segments.*' => ['nullable', 'string'],
            'targets.*.content_override.media_ids' => ['array'],
            'targets.*.content_override.media_ids.*' => ['string'],
            'targets.*.segment_breaks' => ['nullable', 'array'],
            'targets.*.segment_breaks.*' => ['string'],
            'targets.*.placements' => ['nullable', 'array'],
            'targets.*.placements.*.media_id' => ['required', 'string'],
            'targets.*.placements.*.segment_ref' => ['required', 'string'],
            'targets.*.placements.*.position' => ['required', 'integer'],
            'media_ids' => ['array'],
            'media_ids.*' => ['string'],
            'expected_updated_at' => ['nullable', 'string'],
        ];
    }
}
