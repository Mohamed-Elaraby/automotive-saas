<?php

declare(strict_types=1);

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingLead extends Model
{
    use HasFactory;

    public const KIND_BOOK_DEMO  = 'book_demo';
    public const KIND_START_TRIAL = 'start_trial';
    public const KIND_CONTACT    = 'contact';

    public const STATUS_NEW       = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_CLOSED    = 'closed';

    protected $table = 'marketing_leads';

    protected $fillable = [
        'kind',
        'locale',
        'full_name',
        'company_name',
        'business_type',
        'country',
        'phone',
        'email',
        'branches_count',
        'interested_system',
        'preferred_language',
        'message',
        'source_page',
        'ip',
        'user_agent',
        'status',
        'contacted_at',
    ];

    protected $casts = [
        'branches_count' => 'integer',
        'contacted_at'   => 'datetime',
    ];
}
