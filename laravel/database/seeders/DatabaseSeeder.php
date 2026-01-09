<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed in correct order: Users -> Products -> Locations & Reservations
        $this->call([
            AdminSeeder::class,
            ProductSeeder::class,
            InventoryLocationSeeder::class,
            JobReservationSeeder::class,
        ]);
    }
}