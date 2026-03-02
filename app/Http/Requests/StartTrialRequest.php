<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'company_name' => ['required', 'string', 'max:190'],

            // subdomain like: mido (letters/numbers/hyphen) and not start/end with hyphen
            'subdomain' => [
                'required',
                'string',
                'max:63',
                'regex:/^(?!-)[a-z0-9-]{1,63}(?<!-)$/i',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('subdomain')) {
            $sub = strtolower(trim($this->input('subdomain')));
            $this->merge([
                // هنخزّن الدومين كامل عشان Rule::unique يشتغل بسهولة
                'subdomain' => $sub,
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'subdomain.regex' => 'Subdomain must contain letters/numbers/hyphen and cannot start or end with a hyphen.',
        ];
    }
}
