<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('updateRole', $this->route('user'));

        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
        ];
    }
}
