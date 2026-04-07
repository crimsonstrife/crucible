<?php

namespace App\Http\Requests\SshKeys;

use Illuminate\Foundation\Http\FormRequest;

class StoreSshKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\SshKey::class);
    }

    public function rules(): array
    {
        return [
            'title'          => ['required', 'string', 'max:100'],
            'public_key'     => ['required', 'string'],
            'is_deploy_key'  => ['boolean'],
            'deploy_repo_id' => ['nullable', 'uuid', 'exists:repositories,id'],
        ];
    }
}
