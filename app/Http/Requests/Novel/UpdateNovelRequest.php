<?php

namespace App\Http\Requests\Novel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNovelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'url'],
            'status' => ['sometimes', 'in:ongoing,completed,hiatus'],
            'genres' => ['sometimes', 'array'],
            'genres.*' => ['exists:genres,id'],
        ];
    }
}
