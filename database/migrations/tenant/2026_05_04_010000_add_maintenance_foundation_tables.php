<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'customer_number')) {
                $table->string('customer_number')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('customers', 'customer_type')) {
                $table->string('customer_type', 40)->default('individual')->after('email');
            }
            if (! Schema::hasColumn('customers', 'company_name')) {
                $table->string('company_name')->nullable()->after('customer_type');
            }
            if (! Schema::hasColumn('customers', 'tax_number')) {
                $table->string('tax_number')->nullable()->after('company_name');
            }
            if (! Schema::hasColumn('customers', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('tax_number');
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $this->addVehicleColumn($table, 'vehicle_number', fn () => $table->string('vehicle_number')->nullable()->unique()->after('id'));
            $this->addVehicleColumn($table, 'plate_source', fn () => $table->string('plate_source')->nullable()->after('plate_number'));
            $this->addVehicleColumn($table, 'plate_country', fn () => $table->string('plate_country')->nullable()->after('plate_source'));
            $this->addVehicleColumn($table, 'trim', fn () => $table->string('trim')->nullable()->after('year'));
            $this->addVehicleColumn($table, 'color', fn () => $table->string('color')->nullable()->after('trim'));
            $this->addVehicleColumn($table, 'odometer', fn () => $table->unsignedInteger('odometer')->nullable()->after('color'));
            $this->addVehicleColumn($table, 'fuel_type', fn () => $table->string('fuel_type', 60)->nullable()->after('odometer'));
            $this->addVehicleColumn($table, 'transmission', fn () => $table->string('transmission', 60)->nullable()->after('fuel_type'));
            $this->addVehicleColumn($table, 'engine_number', fn () => $table->string('engine_number')->nullable()->after('transmission'));
            $this->addVehicleColumn($table, 'warranty_status', fn () => $table->string('warranty_status', 60)->nullable()->after('engine_number'));
            $this->addVehicleColumn($table, 'last_service_date', fn () => $table->date('last_service_date')->nullable()->after('warranty_status'));
            $this->addVehicleColumn($table, 'next_service_due_at', fn () => $table->date('next_service_due_at')->nullable()->after('last_service_date'));
            $this->addVehicleColumn($table, 'vin_verified_at', fn () => $table->timestamp('vin_verified_at')->nullable()->after('vin'));
            $this->addVehicleColumn($table, 'vin_verified_by', fn () => $table->foreignId('vin_verified_by')->nullable()->after('vin_verified_at')->constrained('users')->nullOnDelete());
            $this->addVehicleColumn($table, 'vin_verification_method', fn () => $table->string('vin_verification_method', 40)->nullable()->after('vin_verified_by'));
            $this->addVehicleColumn($table, 'vin_confidence_score', fn () => $table->decimal('vin_confidence_score', 5, 2)->nullable()->after('vin_verification_method'));
            $this->addVehicleColumn($table, 'vin_source_image_id', fn () => $table->unsignedBigInteger('vin_source_image_id')->nullable()->after('vin_confidence_score'));
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $this->addWorkOrderColumn($table, 'service_advisor_id', fn () => $table->foreignId('service_advisor_id')->nullable()->after('vehicle_id')->constrained('users')->nullOnDelete());
            $this->addWorkOrderColumn($table, 'priority', fn () => $table->string('priority', 40)->default('normal')->after('status'));
            $this->addWorkOrderColumn($table, 'vehicle_status', fn () => $table->string('vehicle_status', 60)->default('in_workshop')->after('priority'));
            $this->addWorkOrderColumn($table, 'payment_status', fn () => $table->string('payment_status', 60)->default('unpaid')->after('vehicle_status'));
            $this->addWorkOrderColumn($table, 'expected_delivery_at', fn () => $table->timestamp('expected_delivery_at')->nullable()->after('opened_at'));
            $this->addWorkOrderColumn($table, 'customer_visible_notes', fn () => $table->text('customer_visible_notes')->nullable()->after('notes'));
            $this->addWorkOrderColumn($table, 'internal_notes', fn () => $table->text('internal_notes')->nullable()->after('customer_visible_notes'));
        });

        $this->createIfMissing('vehicle_check_ins', function (Blueprint $table) {
            $table->id();
            $table->string('check_in_number')->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('service_advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 60)->default('checked_in');
            $table->unsignedInteger('odometer')->nullable();
            $table->unsignedTinyInteger('fuel_level')->nullable();
            $table->json('warning_lights')->nullable();
            $table->json('personal_belongings')->nullable();
            $table->text('customer_complaint')->nullable();
            $table->text('existing_damage_notes')->nullable();
            $table->text('customer_visible_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('expected_delivery_at')->nullable();
            $table->string('vin_number')->nullable();
            $table->timestamp('vin_verified_at')->nullable();
            $table->foreignId('vin_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vin_verification_method', 40)->nullable();
            $table->decimal('vin_confidence_score', 5, 2)->nullable();
            $table->unsignedBigInteger('vin_source_image_id')->nullable();
            $table->text('customer_signature')->nullable();
            $table->text('service_advisor_signature')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['vehicle_id', 'checked_in_at']);
        });

        $this->createIfMissing('maintenance_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->morphs('attachable');
            $table->string('category', 80)->default('other');
            $table->string('file_disk', 80)->default('public');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id']);
            $table->index(['category', 'captured_at']);
        });

        $this->createIfMissing('vehicle_condition_maps', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('check_in_id')->nullable()->constrained('vehicle_check_ins')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->string('type', 40);
            $table->string('status', 60)->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'type']);
            $table->index(['check_in_id', 'type']);
        });

        $this->createIfMissing('vehicle_condition_map_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_id')->constrained('vehicle_condition_maps')->cascadeOnDelete();
            $table->string('vehicle_area_code', 80);
            $table->string('label');
            $table->string('note_type', 40);
            $table->string('severity', 40)->default('low');
            $table->text('description')->nullable();
            $table->text('customer_visible_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->foreignId('photo_id')->nullable()->constrained('maintenance_attachments')->nullOnDelete();
            $table->timestamps();

            $table->index(['map_id', 'vehicle_area_code']);
        });

        $this->createIfMissing('maintenance_service_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('service_number')->nullable()->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->unsignedInteger('estimated_minutes')->default(0);
            $table->decimal('default_labor_price', 12, 2)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->unsignedInteger('warranty_days')->default(0);
            $table->string('required_skill')->nullable();
            $table->string('required_bay_type')->nullable();
            $table->boolean('is_package')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        $this->createIfMissing('maintenance_estimates', function (Blueprint $table) {
            $table->id();
            $table->string('estimate_number')->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('check_in_id')->nullable()->constrained('vehicle_check_ins')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->string('status', 60)->default('draft');
            $table->date('valid_until')->nullable();
            $table->timestamp('expected_delivery_at')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->text('terms')->nullable();
            $table->text('customer_visible_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('approval_method', 60)->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['customer_id', 'vehicle_id']);
        });

        $this->createIfMissing('maintenance_estimate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained('maintenance_estimates')->cascadeOnDelete();
            $table->foreignId('service_catalog_item_id')->nullable();
            $table->foreign('service_catalog_item_id', 'maint_est_lines_service_item_fk')
                ->references('id')
                ->on('maintenance_service_catalog_items')
                ->nullOnDelete();
            $table->string('line_type', 40)->default('labor');
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->string('approval_status', 60)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('maintenance_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('estimate_id')->nullable()->constrained('maintenance_estimates')->nullOnDelete();
            $table->string('status', 60)->default('draft');
            $table->string('payment_status', 60)->default('unpaid');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'payment_status']);
        });

        $this->createIfMissing('maintenance_timeline_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('check_in_id')->nullable()->constrained('vehicle_check_ins')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('event_type', 100);
            $table->string('title');
            $table->text('customer_visible_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['work_order_id', 'created_at']);
            $table->index(['check_in_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_timeline_entries');
        Schema::dropIfExists('maintenance_invoices');
        Schema::dropIfExists('maintenance_estimate_lines');
        Schema::dropIfExists('maintenance_estimates');
        Schema::dropIfExists('maintenance_service_catalog_items');
        Schema::dropIfExists('vehicle_condition_map_items');
        Schema::dropIfExists('vehicle_condition_maps');
        Schema::dropIfExists('maintenance_attachments');
        Schema::dropIfExists('vehicle_check_ins');

        Schema::table('work_orders', function (Blueprint $table) {
            foreach (['service_advisor_id', 'priority', 'vehicle_status', 'payment_status', 'expected_delivery_at', 'customer_visible_notes', 'internal_notes'] as $column) {
                if (Schema::hasColumn('work_orders', $column)) {
                    if ($column === 'service_advisor_id') {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            foreach ([
                'vehicle_number', 'plate_source', 'plate_country', 'trim', 'color', 'odometer', 'fuel_type',
                'transmission', 'engine_number', 'warranty_status', 'last_service_date', 'next_service_due_at',
                'vin_verified_at', 'vin_verification_method', 'vin_confidence_score', 'vin_source_image_id',
            ] as $column) {
                if (Schema::hasColumn('vehicles', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('vehicles', 'vin_verified_by')) {
                $table->dropConstrainedForeignId('vin_verified_by');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            foreach (['customer_number', 'customer_type', 'company_name', 'tax_number', 'internal_notes'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addVehicleColumn(Blueprint $table, string $column, Closure $callback): void
    {
        if (! Schema::hasColumn('vehicles', $column)) {
            $callback();
        }
    }

    private function addWorkOrderColumn(Blueprint $table, string $column, Closure $callback): void
    {
        if (! Schema::hasColumn('work_orders', $column)) {
            $callback();
        }
    }

    protected function createIfMissing(string $table, callable $callback): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, $callback);
    }
};
