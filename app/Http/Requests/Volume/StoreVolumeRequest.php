<?php

namespace App\Http\Requests\Volume;

use Illuminate\Foundation\Http\FormRequest;

class StoreVolumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'volume_number' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
