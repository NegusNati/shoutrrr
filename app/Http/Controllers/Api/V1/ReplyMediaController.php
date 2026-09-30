<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceMedia;
use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceReply;
use App\Http\Controllers\Engagement\ReplyMediaController as InboxReplyMediaController;
use App\Http\Requests\Engagement\StoreReplyMediaRequest;
use App\Models\PostMedia;
use App\Models\PostTargetReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attachment uploads for an outbound engagement reply draft (multipart `file`).
 */
class ReplyMediaController extends InboxReplyMediaController
{
    use ResolvesWorkspaceMedia;
    use ResolvesWorkspaceReply;

    public function store(StoreReplyMediaRequest $request, PostTargetReply|string $replyId): JsonResponse
    {
        return parent::store($request, $this->findReplyOrFail($replyId));
    }

    /** Update an attachment's alt text. Body: { alt_text: string|null }. */
    public function updateAlt(PostTargetReply|string $replyId, PostMedia|string $mediaId, Request $request): JsonResponse
    {
        return parent::updateAlt($this->findReplyOrFail($replyId), $this->findMediaOrFail($mediaId), $request);
    }

    public function destroy(PostTargetReply|string $replyId, PostMedia|string $mediaId): JsonResponse
    {
        return parent::destroy($this->findReplyOrFail($replyId), $this->findMediaOrFail($mediaId));
    }
}
