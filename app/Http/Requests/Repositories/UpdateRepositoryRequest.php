<?php

namespace App\Http\Requests\Repositories;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRepositoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('repository'));
    }

    public function rules(): array
    {
        return [
            'name'           => ['sometimes', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'description'    => ['nullable', 'string', 'max:500'],
            'visibility'     => ['sometimes', 'in:public,private,internal'],
            'lfs_enabled'    => ['sometimes', 'boolean'],
            'default_branch' => ['sometimes', 'string', 'max:60'],
            'is_archived'    => ['sometimes', 'boolean'],
        ];
    }
}
