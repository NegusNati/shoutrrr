<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceConversation;
use App\Http\Controllers\Messaging\ConversationVideoUploadController as InboxConversationVideoUploadController;
use App\Http\Requests\Messaging\SignConversationVideoUploadRequest;
use App\Http\Requests\Messaging\StoreConversationVideoRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

/**
 * Presigned direct-to-storage video uploads for a DM draft:
 * sign → PUT to storage → confirm.
 */
class ConversationVideoUploadController extends InboxConversationVideoUploadController
{
    use ResolvesWorkspaceConversation;

    /** Sign a video upload. Body: { content_type: 'video/mp4' }. */
    public function url(SignConversationVideoUploadRequest $request, Conversation|string $conversationId): JsonResponse
    {
        return parent::url($request, $this->findMediaConversationOrFail($conversationId));
    }

    /** Confirm a finished upload. Body: { key, duration_seconds, width, height, alt_text }. */
    public function store(StoreConversationVideoRequest $request, Conversation|string $conversationId): JsonResponse
    {
        return parent::store($request, $this->findMediaConversationOrFail($conversationId));
    }

    private function findMediaConversationOrFail(Conversation|string $conversationId): Conversation
    {
        $model = $this->findConversationOrFail($conversationId);

        abort_unless($model->platform->supportsDirectMessageMedia(), 404);

        return $model;
    }
}
