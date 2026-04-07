<?php

namespace App\Http\Requests\Repositories;

use Illuminate\Foundation\Http\FormRequest;

class CreateRepositoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization !== null
            && $this->user() !== null
            && $this->user()->can('view', $organization);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'vcs_type' => ['required', 'in:git,svn'],
            'visibility' => ['required', 'in:public,private,internal'],
            'lfs_enabled' => ['boolean'],
            'default_branch' => ['nullable', 'string', 'max:60'],
            'forge_project_id' => ['nullable', 'string'],
        ];
    }
}
