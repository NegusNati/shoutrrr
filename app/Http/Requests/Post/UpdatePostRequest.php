<?php

declare(strict_types=1);

namespace App\Http\Requests\Post;

use App\Http\Requests\Post\Concerns\PostPayloadRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    use PostPayloadRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('post'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->postBodyRules(),
            ...$this->postEditRules(),
        ];
    }
}
