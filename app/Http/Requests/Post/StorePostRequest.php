<?php

declare(strict_types=1);

namespace App\Http\Requests\Post;

use App\Http\Requests\Post\Concerns\PostPayloadRules;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    use PostPayloadRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Post::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->postBodyRules();
    }
}
