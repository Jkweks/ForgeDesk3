<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds a storage_location_id foreign key to inventory_locations
     * and migrates existing string-based locations to the new storage_locations table.
     */
    public function up(): void
    {
        // Add the new foreign key column (nullable during migration)
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->foreignId('storage_location_id')->nullable()->after('product_id')->constrained('storage_locations')->onDelete('cascade');
            $table->index('storage_location_id');
        });

        // Migrate existing location strings to storage_locations
        $this->migrateExistingLocations();

        // After migration, we can make location column nullable (but keep it for backward compatibility)
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->string('location')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropForeign(['storage_location_id']);
            $table->dropColumn('storage_location_id');
            $table->string('location')->nullable(false)->change();
        });
    }

    /**
     * Migrate existing location strings to storage_locations table
     */
    private function migrateExistingLocations(): void
    {
        // Get all unique locations from inventory_locations
        $uniqueLocations = DB::table('inventory_locations')
            ->whereNotNull('location')
            ->distinct()
            ->pluck('location');

        foreach ($uniqueLocations as $locationName) {
            // Parse the location name to extract components
            $components = $this->parseLocationName($locationName);

            // Create or find the storage location
            $storageLocationId = DB::table('storage_locations')->insertGetId([
                'name' => $locationName,
                'aisle' => $components['aisle'],
                'rack' => $components['rack'],
                'shelf' => $components['shelf'],
                'bin' => $components['bin'],
                'slug' => $this->generateSlug($components),
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update inventory_locations to reference the new storage location
            DB::table('inventory_locations')
                ->where('location', $locationName)
                ->update(['storage_location_id' => $storageLocationId]);
        }
    }

    /**
     * Parse location name to extract hierarchical components
     * Examples:
     * - "Aisle 1" -> ['aisle' => '1']
     * - "Aisle 1, Rack A" -> ['aisle' => '1', 'rack' => 'A']
     * - "Warehouse A" -> ['aisle' => 'Warehouse A']
     * - "Bin 23" -> ['bin' => '23']
     */
    private function parseLocationName(string $locationName): array
    {
        $components = [
            'aisle' => null,
            'rack' => null,
            'shelf' => null,
            'bin' => null,
        ];

        // Try to parse common patterns
        if (preg_match('/Aisle\s*(\S+)/i', $locationName, $matches)) {
            $components['aisle'] = $matches[1];
        }

        if (preg_match('/Rack\s*(\S+)/i', $locationName, $matches)) {
            $components['rack'] = $matches[1];
        }

        if (preg_match('/Shelf\s*(\S+)/i', $locationName, $matches)) {
            $components['shelf'] = $matches[1];
        }

        if (preg_match('/Bin\s*(\S+)/i', $locationName, $matches)) {
            $components['bin'] = $matches[1];
        }

        // If no components were parsed, use the whole name as aisle
        if (!$components['aisle'] && !$components['rack'] && !$components['shelf'] && !$components['bin']) {
            $components['aisle'] = $locationName;
        }

        return $components;
    }

    /**
     * Generate a slug path from location components
     * Example: ['aisle' => '1', 'rack' => '2', 'shelf' => '3'] -> "1.2.3"
     */
    private function generateSlug(array $components): ?string
    {
        $parts = array_filter([
            $components['aisle'],
            $components['rack'],
            $components['shelf'],
            $components['bin'],
        ]);

        return !empty($parts) ? implode('.', $parts) : null;
    }
};
