<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Use ComprehensiveSeeder for full database with hierarchical storage locations
        // WARNING: This will PURGE ALL EXISTING DATA before seeding!
        $this->call([
            ComprehensiveSeeder::class,
        ]);

        // Legacy seeders (comment out ComprehensiveSeeder above to use these)
        // $this->call([
        //     AdminSeeder::class,
        //     ProductSeeder::class,
        //     InventoryLocationSeeder::class,
        //     JobReservationSeeder::class,
        // ]);
    }
}