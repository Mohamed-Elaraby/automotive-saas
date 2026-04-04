<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function centralConnection(): ?string
    {
        return Config::get('tenancy.database.central_connection') ?: Config::get('database.default');
    }

    public function up(): void
    {
        $connection = $this->centralConnection();

        Schema::connection($connection)->table('plans', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('id')
                ->constrained('products')
                ->nullOnDelete();
        });

        $automotiveProductId = DB::connection($connection)->table('products')->insertGetId([
            'code' => 'automotive_service',
            'name' => 'Automotive Service Management',
            'slug' => 'automotive-service',
            'description' => 'Automotive service center management product.',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection($connection)
            ->table('plans')
            ->whereNull('product_id')
            ->update([
                'product_id' => $automotiveProductId,
            ]);
    }

    public function down(): void
    {
        $connection = $this->centralConnection();

        Schema::connection($connection)->table('plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
