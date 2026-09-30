<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceConversation;
use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceMedia;
use App\Http\Controllers\Messaging\ConversationMediaController as InboxConversationMediaController;
use App\Http\Requests\Messaging\StoreConversationMediaRequest;
use App\Models\Conversation;
use App\Models\PostMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attachment uploads for an outbound DM draft (multipart `file`). 404s on
 * platforms that cannot carry a DM attachment — the web route's
 * `conversation.supports-media` middleware, inlined here.
 */
class ConversationMediaController extends InboxConversationMediaController
{
    use ResolvesWorkspaceConversation;
    use ResolvesWorkspaceMedia;

    public function store(StoreConversationMediaRequest $request, Conversation|string $conversationId): JsonResponse
    {
        return parent::store($request, $this->findMediaConversationOrFail($conversationId));
    }

    /** Update an attachment's alt text. Body: { alt_text: string|null }. */
    public function updateAlt(Conversation|string $conversationId, PostMedia|string $mediaId, Request $request): JsonResponse
    {
        return parent::updateAlt($this->findMediaConversationOrFail($conversationId), $this->findMediaOrFail($mediaId), $request);
    }

    public function destroy(Conversation|string $conversationId, PostMedia|string $mediaId): JsonResponse
    {
        return parent::destroy($this->findMediaConversationOrFail($conversationId), $this->findMediaOrFail($mediaId));
    }

    private function findMediaConversationOrFail(Conversation|string $conversationId): Conversation
    {
        $model = $this->findConversationOrFail($conversationId);

        abort_unless($model->platform->supportsDirectMessageMedia(), 404);

        return $model;
    }
}
