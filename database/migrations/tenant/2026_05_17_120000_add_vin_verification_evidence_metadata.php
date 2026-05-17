<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns('vehicle_check_ins');
        $this->addColumns('vehicles');
    }

    public function down(): void
    {
        $this->dropColumns('vehicle_check_ins');
        $this->dropColumns('vehicles');
    }

    protected function addColumns(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'vin_source')) {
                $blueprint->string('vin_source', 60)->nullable()->after('vin_verification_method');
            }

            if (! Schema::hasColumn($table, 'vin_ocr_status')) {
                $blueprint->string('vin_ocr_status', 60)->nullable()->after('vin_source');
            }

            if (! Schema::hasColumn($table, 'vin_ocr_confidence')) {
                $blueprint->decimal('vin_ocr_confidence', 5, 2)->nullable()->after('vin_ocr_status');
            }

            if (! Schema::hasColumn($table, 'vin_unreadable_reason')) {
                $blueprint->string('vin_unreadable_reason', 80)->nullable()->after('vin_ocr_confidence');
            }
        });
    }

    protected function dropColumns(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            foreach (['vin_unreadable_reason', 'vin_ocr_confidence', 'vin_ocr_status', 'vin_source'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
