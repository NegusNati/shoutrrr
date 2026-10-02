<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceMedia;
use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspacePost;
use App\Http\Controllers\Api\V1\Concerns\ValidatesAsWebFormRequest;
use App\Http\Controllers\Posts\PostImageEditController as WebPostImageEditController;
use App\Http\Requests\Post\StorePostImageEditRequest;
use App\Http\Requests\Post\UpdatePostImageEditRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostImageEditController extends WebPostImageEditController
{
    use ResolvesWorkspaceMedia, ResolvesWorkspacePost, ValidatesAsWebFormRequest;

    public function storeEdit(Request $request, string $postId): JsonResponse
    {
        $post = $this->findPostOrFail($postId);
        $form = $this->formRequestForPost(StorePostImageEditRequest::class, $request, $post);

        return $this->store($form, $post);
    }

    public function updateEdit(Request $request, string $postId, string $mediaId): JsonResponse
    {
        $post = $this->findPostOrFail($postId);
        $media = $this->findMediaOrFail($mediaId);
        $form = $this->formRequestForPost(UpdatePostImageEditRequest::class, $request, $post);

        return $this->update($form, $post, $media);
    }
}
