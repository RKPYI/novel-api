<?php

namespace App\Http\Requests\Novel;

use Illuminate\Foundation\Http\FormRequest;

class StoreNovelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'url'],
            'status' => ['nullable', 'in:ongoing,completed,hiatus'],
            'uses_volumes' => ['nullable', 'boolean'],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['exists:genres,id'],
        ];
    }
}
