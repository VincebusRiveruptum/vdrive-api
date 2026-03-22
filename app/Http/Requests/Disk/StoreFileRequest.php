<?php

namespace App\Http\Requests\Disk;

use Illuminate\Foundation\Http\FormRequest;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:102400',
            'folder_id' => 'nullable|exists:folders,id'
        ];
    }
}
