<?php

namespace App\Http\Requests\Repositories;

use Illuminate\Foundation\Http\FormRequest;

class AddCollaboratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageCollaborators', $this->route('repository'));
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role'    => ['required', 'in:read,write,maintain,admin'],
        ];
    }
}
