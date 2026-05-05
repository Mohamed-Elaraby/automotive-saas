<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIfMissing('maintenance_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('appointment_number');
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('service_advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('check_in_id')->nullable()->constrained('vehicle_check_ins')->nullOnDelete();
            $table->string('type', 40)->default('appointment');
            $table->string('status', 60)->default('scheduled');
            $table->string('source', 80)->default('in_branch');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('expected_arrival_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('vehicle_make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('vehicle_year')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('vin_number')->nullable();
            $table->string('service_type')->nullable();
            $table->string('priority', 40)->default('normal');
            $table->text('customer_complaint')->nullable();
            $table->text('customer_visible_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('appointment_number', 'maint_appt_number_unique');
            $table->index(['branch_id', 'scheduled_at'], 'maint_appt_branch_scheduled_idx');
            $table->index(['status', 'scheduled_at'], 'maint_appt_status_scheduled_idx');
            $table->index(['customer_id', 'status'], 'maint_appt_customer_status_idx');
            $table->index(['vehicle_id', 'status'], 'maint_appt_vehicle_status_idx');
        });

        Schema::table('vehicle_check_ins', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicle_check_ins', 'appointment_id')) {
                $table->unsignedBigInteger('appointment_id')->nullable()->after('id');
                $table->foreign('appointment_id', 'veh_checkins_appt_fk')
                    ->references('id')
                    ->on('maintenance_appointments')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_check_ins', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_check_ins', 'appointment_id')) {
                $table->dropForeign('veh_checkins_appt_fk');
                $table->dropColumn('appointment_id');
            }
        });

        Schema::dropIfExists('maintenance_appointments');
    }

    protected function createIfMissing(string $table, callable $callback): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, $callback);
    }
};
