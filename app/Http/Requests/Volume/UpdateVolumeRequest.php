<?php

namespace App\Http\Requests\Volume;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVolumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'volume_number' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
