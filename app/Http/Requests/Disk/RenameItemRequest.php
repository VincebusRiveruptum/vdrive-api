<?php

namespace App\Http\Requests\Disk;

use Illuminate\Foundation\Http\FormRequest;

class RenameItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255'
        ];
    }
}
