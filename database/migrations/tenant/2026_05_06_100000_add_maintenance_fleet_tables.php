<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_fleet_accounts')) {
            Schema::create('maintenance_fleet_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('fleet_number')->unique();
                $table->unsignedBigInteger('customer_id');
                $table->string('status', 40)->default('active');
                $table->string('contract_type', 60)->default('standard');
                $table->date('contract_starts_on')->nullable();
                $table->date('contract_ends_on')->nullable();
                $table->decimal('credit_limit', 12, 2)->nullable();
                $table->boolean('monthly_billing_enabled')->default(false);
                $table->boolean('approval_required')->default(false);
                $table->decimal('approval_limit', 12, 2)->nullable();
                $table->string('billing_cycle_day', 10)->nullable();
                $table->json('preventive_schedule')->nullable();
                $table->text('terms')->nullable();
                $table->text('internal_notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('customer_id', 'mnt_fleet_customer_fk')->references('id')->on('customers')->cascadeOnDelete();
                $table->foreign('created_by', 'mnt_fleet_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['status', 'contract_type'], 'mnt_fleet_status_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_fleet_accounts');
    }
};
