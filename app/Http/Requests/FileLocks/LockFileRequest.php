<?php

namespace App\Http\Requests\FileLocks;

use Illuminate\Foundation\Http\FormRequest;

class LockFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:1000'],
            'ref'  => ['nullable', 'string', 'max:256'],
        ];
    }
}
