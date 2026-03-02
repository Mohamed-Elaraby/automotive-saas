<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // stancl tenant id (string)
            $table->string('tenant_id');

            $table->foreignId('plan_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->enum('status', [
                'trialing',
                'active',
                'past_due',
                'canceled',
                'expired'
            ])->default('trialing');

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // Stripe / Paddle / etc
            $table->string('external_id')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
