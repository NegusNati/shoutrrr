<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspacePost;
use App\Http\Controllers\Api\V1\Concerns\ValidatesAsWebFormRequest;
use App\Http\Controllers\Posts\PostVideoUploadController as WebPostVideoUploadController;
use App\Http\Requests\Post\SignVideoUploadRequest;
use App\Http\Requests\Post\StoreVideoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostVideoUploadController extends WebPostVideoUploadController
{
    use ResolvesWorkspacePost, ValidatesAsWebFormRequest;

    public function signUrl(Request $request, string $postId): JsonResponse
    {
        $post = $this->findPostOrFail($postId);
        $form = $this->formRequestForPost(SignVideoUploadRequest::class, $request, $post);

        return $this->url($form, $post);
    }

    public function storeVideo(Request $request, string $postId): JsonResponse
    {
        $post = $this->findPostOrFail($postId);
        $form = $this->formRequestForPost(StoreVideoRequest::class, $request, $post);

        return $this->store($form, $post);
    }
}
