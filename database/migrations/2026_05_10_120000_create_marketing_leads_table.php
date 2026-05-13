<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32);
            $table->string('locale', 5)->default('en');
            $table->string('full_name');
            $table->string('company_name')->nullable();
            $table->string('business_type', 64)->nullable();
            $table->string('country', 64)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('email');
            $table->unsignedSmallInteger('branches_count')->nullable();
            $table->string('interested_system', 64)->nullable();
            $table->string('preferred_language', 16)->nullable();
            $table->text('message')->nullable();
            $table->string('source_page')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('status', 32)->default('new');
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->index('kind', 'mkt_leads_kind_idx');
            $table->index('status', 'mkt_leads_status_idx');
            $table->index(['kind', 'status'], 'mkt_leads_kind_status_idx');
            $table->index('email', 'mkt_leads_email_idx');
            $table->index('created_at', 'mkt_leads_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
    }
};
