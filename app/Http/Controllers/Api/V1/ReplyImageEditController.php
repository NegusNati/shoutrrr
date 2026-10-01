<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceMedia;
use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceReply;
use App\Http\Controllers\Engagement\ReplyImageEditController as InboxReplyImageEditController;
use App\Http\Requests\Engagement\StoreReplyImageEditRequest;
use App\Http\Requests\Engagement\UpdateReplyImageEditRequest;
use App\Models\PostMedia;
use App\Models\PostTargetReply;
use Illuminate\Http\JsonResponse;

/**
 * Beauty-edit endpoints for an outbound engagement reply image (multipart
 * `composed` + `settings` from the client-side image editor).
 */
class ReplyImageEditController extends InboxReplyImageEditController
{
    use ResolvesWorkspaceMedia;
    use ResolvesWorkspaceReply;

    public function store(StoreReplyImageEditRequest $request, PostTargetReply|string $replyId): JsonResponse
    {
        return parent::store($request, $this->findReplyOrFail($replyId));
    }

    public function update(UpdateReplyImageEditRequest $request, PostTargetReply|string $replyId, PostMedia|string $mediaId): JsonResponse
    {
        return parent::update($request, $this->findReplyOrFail($replyId), $this->findMediaOrFail($mediaId));
    }
}
