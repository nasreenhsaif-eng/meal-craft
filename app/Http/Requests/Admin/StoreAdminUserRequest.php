<?php

namespace App\Http\Requests\Admin;

use App\Support\AdminPortalPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCraftMasterGeneral() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'admin_permissions' => ['nullable', 'array'],
            'admin_permissions.*' => ['string', Rule::in(AdminPortalPermission::keys())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
