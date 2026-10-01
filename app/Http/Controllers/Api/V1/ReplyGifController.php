<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceReply;
use App\Http\Controllers\Gifs\ReplyGifController as InboxReplyGifController;
use App\Http\Requests\Gifs\AttachGifRequest;
use App\Models\PostTargetReply;
use Illuminate\Http\JsonResponse;

/**
 * Attach a GIF from the catalog to an engagement reply draft; the server
 * downloads and stores it as a PostMedia row.
 */
class ReplyGifController extends InboxReplyGifController
{
    use ResolvesWorkspaceReply;

    public function store(AttachGifRequest $request, PostTargetReply|string $replyId): JsonResponse
    {
        return parent::store($request, $this->findReplyOrFail($replyId));
    }
}
