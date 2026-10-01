<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceReply;
use App\Http\Controllers\Engagement\ReplyVideoUploadController as InboxReplyVideoUploadController;
use App\Http\Requests\Engagement\SignReplyVideoUploadRequest;
use App\Http\Requests\Engagement\StoreReplyVideoRequest;
use App\Models\PostTargetReply;
use Illuminate\Http\JsonResponse;

/**
 * Presigned direct-to-storage video uploads for an engagement reply draft:
 * sign → PUT to storage → confirm.
 */
class ReplyVideoUploadController extends InboxReplyVideoUploadController
{
    use ResolvesWorkspaceReply;

    /** Sign a video upload. Body: { content_type: 'video/mp4' }. */
    public function url(SignReplyVideoUploadRequest $request, PostTargetReply|string $replyId): JsonResponse
    {
        return parent::url($request, $this->findReplyOrFail($replyId));
    }

    /** Confirm a finished upload. Body: { key, duration_seconds, width, height, alt_text }. */
    public function store(StoreReplyVideoRequest $request, PostTargetReply|string $replyId): JsonResponse
    {
        return parent::store($request, $this->findReplyOrFail($replyId));
    }
}
