<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspacePost;
use App\Http\Controllers\Api\V1\Concerns\ValidatesAsWebFormRequest;
use App\Http\Controllers\Gifs\PostGifController as WebPostGifController;
use App\Http\Requests\Gifs\AttachGifRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostGifController extends WebPostGifController
{
    use ResolvesWorkspacePost, ValidatesAsWebFormRequest;

    public function attach(Request $request, string $postId): JsonResponse
    {
        $post = $this->findPostOrFail($postId);
        $form = $this->formRequestForPost(AttachGifRequest::class, $request, $post);

        return $this->store($form, $post);
    }
}
