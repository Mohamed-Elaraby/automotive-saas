<?php

namespace App\Http\Requests\Automotive\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserProductRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roles' => ['nullable', 'array'],
            'roles.*' => ['nullable', 'integer', 'exists:product_roles,id'],
        ];
    }
}
