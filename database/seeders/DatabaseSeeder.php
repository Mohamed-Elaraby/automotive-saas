<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // <seven-scaffold-seeders>
        $this->call(ProductSeeder::class);
        $this->call(ProductCapabilitiesSeeder::class);
        $this->call(PlanSeeder::class);
        $this->call(ReferenceDataSeeder::class);
        $this->call(CentralAdminSeeder::class);
        $this->call(TenantSparePartsDemoSeeder::class);

// \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
