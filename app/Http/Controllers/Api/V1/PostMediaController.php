<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceMedia;
use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspacePost;
use App\Http\Controllers\Api\V1\Concerns\ValidatesAsWebFormRequest;
use App\Http\Controllers\Posts\PostMediaController as WebPostMediaController;
use App\Http\Requests\Post\StorePostMediaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostMediaController extends WebPostMediaController
{
    use ResolvesWorkspaceMedia, ResolvesWorkspacePost, ValidatesAsWebFormRequest;

    public function storeMedia(Request $request, string $postId): JsonResponse
    {
        $post = $this->findPostOrFail($postId);
        $form = $this->formRequestForPost(StorePostMediaRequest::class, $request, $post);

        return $this->store($form, $post);
    }

    public function updateMediaAlt(Request $request, string $postId, string $mediaId): JsonResponse
    {
        $post = $this->findPostOrFail($postId);
        $media = $this->findMediaOrFail($mediaId);

        return $this->updateAlt($post, $media, $request);
    }

    public function removeMedia(string $postId, string $mediaId): JsonResponse
    {
        $post = $this->findPostOrFail($postId);
        $media = $this->findMediaOrFail($mediaId);
        $request = request();
        abort_unless($request->user()->can('update', $post), 403);

        return $this->destroy($post, $media);
    }
}
