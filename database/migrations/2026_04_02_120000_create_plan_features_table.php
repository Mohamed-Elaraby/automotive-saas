<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (! Schema::hasColumn('plans', 'features')) {
            return;
        }

        $plans = DB::table('plans')->select('id', 'features')->get();

        foreach ($plans as $plan) {
            $decoded = json_decode((string) ($plan->features ?? ''), true);

            if (! is_array($decoded)) {
                continue;
            }

            $rows = [];
            $sortOrder = 0;

            foreach ($decoded as $key => $value) {
                $title = null;

                if (is_string($value) && trim($value) !== '') {
                    $title = trim($value);
                } elseif (is_string($key) && is_bool($value) && $value === true) {
                    $title = Str::headline($key);
                } elseif (is_int($key) && is_string($value) && trim($value) !== '') {
                    $title = trim($value);
                }

                if ($title === null) {
                    continue;
                }

                $rows[] = [
                    'plan_id' => $plan->id,
                    'title' => $title,
                    'sort_order' => $sortOrder++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($rows)) {
                DB::table('plan_features')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
