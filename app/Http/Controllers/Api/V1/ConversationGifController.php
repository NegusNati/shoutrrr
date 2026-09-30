<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceConversation;
use App\Http\Controllers\Gifs\ConversationGifController as InboxConversationGifController;
use App\Http\Requests\Gifs\AttachGifRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

/**
 * Attach a GIF from the catalog to a DM draft; the server downloads and stores
 * it as a PostMedia row.
 */
class ConversationGifController extends InboxConversationGifController
{
    use ResolvesWorkspaceConversation;

    public function store(AttachGifRequest $request, Conversation|string $conversationId): JsonResponse
    {
        $model = $this->findConversationOrFail($conversationId);

        abort_unless($model->platform->supportsDirectMessageMedia(), 404);

        return parent::store($request, $model);
    }
}
