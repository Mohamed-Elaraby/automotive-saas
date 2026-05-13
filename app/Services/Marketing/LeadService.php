<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\Marketing\MarketingLead;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class LeadService
{
    /**
     * Persist a marketing lead and emit hooks for future CRM/email/WhatsApp integrations.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(string $kind, array $payload, Request $request): MarketingLead
    {
        $lead = MarketingLead::create([
            'kind'               => $kind,
            'locale'             => Arr::get($payload, 'locale', app()->getLocale()),
            'full_name'          => trim((string) Arr::get($payload, 'full_name', '')),
            'company_name'       => Arr::get($payload, 'company_name'),
            'business_type'      => Arr::get($payload, 'business_type'),
            'country'            => Arr::get($payload, 'country'),
            'phone'              => Arr::get($payload, 'phone'),
            'email'              => strtolower(trim((string) Arr::get($payload, 'email', ''))),
            'branches_count'     => Arr::get($payload, 'branches_count'),
            'interested_system'  => Arr::get($payload, 'interested_system'),
            'preferred_language' => Arr::get($payload, 'preferred_language'),
            'message'            => Arr::get($payload, 'message'),
            'source_page'        => $request->fullUrl(),
            'ip'                 => $request->ip(),
            'user_agent'         => substr((string) $request->userAgent(), 0, 500),
            'status'             => MarketingLead::STATUS_NEW,
        ]);

        Log::info('marketing.lead.created', [
            'id'    => $lead->id,
            'kind'  => $kind,
            'email' => $lead->email,
        ]);

        return $lead;
    }
}
