<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('branch_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->after('customer_id')->constrained('vehicles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
