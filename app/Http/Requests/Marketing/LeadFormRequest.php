<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name'          => ['required', 'string', 'max:120'],
            'company_name'       => ['nullable', 'string', 'max:160'],
            'business_type'      => ['nullable', Rule::in(config('marketing.business_types', []))],
            'country'            => ['nullable', 'string', 'max:64'],
            'phone'              => ['nullable', 'string', 'max:64'],
            'email'              => ['required', 'email:rfc', 'max:160'],
            'branches_count'     => ['nullable', 'integer', 'min:1', 'max:9999'],
            'interested_system'  => ['nullable', Rule::in(config('marketing.interested_systems', []))],
            'preferred_language' => ['nullable', Rule::in(config('marketing.preferred_languages', ['en', 'ar']))],
            'message'            => ['nullable', 'string', 'max:4000'],
            'website'            => ['nullable', 'max:0'], // honeypot — must be empty
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name'          => __('marketing.form.full_name'),
            'company_name'       => __('marketing.form.company_name'),
            'business_type'      => __('marketing.form.business_type'),
            'country'            => __('marketing.form.country'),
            'phone'              => __('marketing.form.phone'),
            'email'              => __('marketing.form.email'),
            'branches_count'     => __('marketing.form.branches_count'),
            'interested_system'  => __('marketing.form.interested_system'),
            'preferred_language' => __('marketing.form.preferred_language'),
            'message'            => __('marketing.form.message'),
        ];
    }
}
