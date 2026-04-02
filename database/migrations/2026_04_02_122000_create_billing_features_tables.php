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
        Schema::create('billing_features', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('billing_feature_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_feature_id')->constrained('billing_features')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['billing_feature_id', 'plan_id']);
        });

        if (! Schema::hasTable('plan_features')) {
            return;
        }

        $planFeatures = DB::table('plan_features')
            ->orderBy('plan_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $featureIdsByName = [];

        foreach ($planFeatures as $planFeature) {
            $name = trim((string) ($planFeature->title ?? ''));

            if ($name === '') {
                continue;
            }

            $lookupKey = mb_strtolower($name);

            if (! array_key_exists($lookupKey, $featureIdsByName)) {
                $baseSlug = Str::slug($name);
                $slug = $baseSlug !== '' ? $baseSlug : 'feature';
                $suffix = 2;

                while (DB::table('billing_features')->where('slug', $slug)->exists()) {
                    $slug = ($baseSlug !== '' ? $baseSlug : 'feature') . '-' . $suffix++;
                }

                $featureId = DB::table('billing_features')->insertGetId([
                    'name' => $name,
                    'slug' => $slug,
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => count($featureIdsByName),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $featureIdsByName[$lookupKey] = $featureId;
            }

            DB::table('billing_feature_plan')->updateOrInsert([
                'billing_feature_id' => $featureIdsByName[$lookupKey],
                'plan_id' => $planFeature->plan_id,
            ], [
                'sort_order' => (int) ($planFeature->sort_order ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_feature_plan');
        Schema::dropIfExists('billing_features');
    }
};
