<?php

namespace App\Http\Requests\Disk;

use Illuminate\Foundation\Http\FormRequest;

class CopyItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => 'required|integer',
            'type' => 'required|in:file,folder',
            'destination_folder_id' => 'nullable|exists:folders,id'
        ];
    }
}
