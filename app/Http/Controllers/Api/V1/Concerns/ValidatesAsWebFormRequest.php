<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

trait ValidatesAsWebFormRequest
{
    /**
     * The web FormRequests authorize via route('post'), which API routes don't
     * bind — posts resolve through ResolvesWorkspacePost by `{id}` instead. The
     * clone keeps input, files, session and user resolver, so validation runs
     * exactly as on the web flow.
     *
     * @template T of FormRequest
     *
     * @param  class-string<T>  $formRequest
     * @return T
     */
    protected function formRequestForPost(string $formRequest, Request $request, Post $post): FormRequest
    {
        $resolved = $formRequest::createFrom($request);
        $resolved->setContainer(app())->setRedirector(app('redirect'));
        $resolved->route()?->setParameter('post', $post);
        $resolved->validateResolved();

        return $resolved;
    }
}
