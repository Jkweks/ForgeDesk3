<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StorageLocation;
use App\Models\InventoryLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorageLocationReconciliationController extends Controller
{
    /**
     * Get reconciliation status and issues
     */
    public function status()
    {
        $issues = [];

        // Check for inventory_locations with missing storage_location_id
        $missingStorageLocation = InventoryLocation::whereNull('storage_location_id')
            ->whereNotNull('location')
            ->count();

        if ($missingStorageLocation > 0) {
            $issues[] = [
                'type' => 'missing_storage_location',
                'severity' => 'warning',
                'count' => $missingStorageLocation,
                'message' => "{$missingStorageLocation} inventory locations are not linked to storage locations",
                'action' => 'migrate_string_locations'
            ];
        }

        // Check for orphaned inventory_locations (storage_location_id points to deleted location)
        $orphaned = InventoryLocation::whereNotNull('storage_location_id')
            ->whereDoesntHave('storageLocation')
            ->count();

        if ($orphaned > 0) {
            $issues[] = [
                'type' => 'orphaned_inventory_locations',
                'severity' => 'error',
                'count' => $orphaned,
                'message' => "{$orphaned} inventory locations reference deleted storage locations",
                'action' => 'fix_orphaned'
            ];
        }

        // Check for storage locations with no inventory
        $emptyStorageLocations = StorageLocation::whereDoesntHave('inventoryLocations')
            ->whereDoesntHave('children')
            ->count();

        if ($emptyStorageLocations > 0) {
            $issues[] = [
                'type' => 'empty_storage_locations',
                'severity' => 'info',
                'count' => $emptyStorageLocations,
                'message' => "{$emptyStorageLocations} storage locations have no inventory",
                'action' => 'cleanup_empty'
            ];
        }

        // Check for duplicate storage locations
        $duplicates = DB::table('storage_locations')
            ->select('slug', DB::raw('count(*) as count'))
            ->whereNotNull('slug')
            ->groupBy('slug')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            $issues[] = [
                'type' => 'duplicate_slugs',
                'severity' => 'error',
                'count' => $duplicates->sum('count'),
                'message' => "Found duplicate location slugs",
                'action' => 'fix_duplicates'
            ];
        }

        return response()->json([
            'status' => count($issues) === 0 ? 'healthy' : 'needs_attention',
            'total_storage_locations' => StorageLocation::count(),
            'total_inventory_locations' => InventoryLocation::count(),
            'issues' => $issues,
            'last_check' => now()->toISOString(),
        ]);
    }

    /**
     * Migrate string-based locations to hierarchical storage locations
     */
    public function migrateStringLocations()
    {
        $migrated = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            // Get all inventory_locations with location string but no storage_location_id
            $inventoryLocations = InventoryLocation::whereNull('storage_location_id')
                ->whereNotNull('location')
                ->get();

            foreach ($inventoryLocations as $invLocation) {
                try {
                    // Check if storage location already exists with this name
                    $storageLocation = StorageLocation::where('name', $invLocation->location)->first();

                    if (!$storageLocation) {
                        // Parse the location name to extract components
                        $components = StorageLocation::parseLocationName($invLocation->location);

                        // Create new storage location
                        $storageLocation = StorageLocation::create([
                            'name' => $invLocation->location,
                            'aisle' => $components['aisle'],
                            'rack' => $components['rack'],
                            'shelf' => $components['shelf'],
                            'bin' => $components['bin'],
                            'is_active' => true,
                            'sort_order' => 0,
                        ]);
                    }

                    // Link inventory location to storage location
                    $invLocation->storage_location_id = $storageLocation->id;
                    $invLocation->save();

                    $migrated++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'inventory_location_id' => $invLocation->id,
                        'location_string' => $invLocation->location,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'migrated' => $migrated,
                'errors' => $errors,
                'message' => "Successfully migrated {$migrated} locations"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fix orphaned inventory locations
     */
    public function fixOrphaned()
    {
        $fixed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            $orphaned = InventoryLocation::whereNotNull('storage_location_id')
                ->whereDoesntHave('storageLocation')
                ->get();

            foreach ($orphaned as $invLocation) {
                try {
                    if ($invLocation->location) {
                        // Try to recreate storage location from string
                        $components = StorageLocation::parseLocationName($invLocation->location);

                        $storageLocation = StorageLocation::create([
                            'name' => $invLocation->location,
                            'aisle' => $components['aisle'],
                            'rack' => $components['rack'],
                            'shelf' => $components['shelf'],
                            'bin' => $components['bin'],
                            'is_active' => true,
                            'sort_order' => 0,
                        ]);

                        $invLocation->storage_location_id = $storageLocation->id;
                        $invLocation->save();

                        $fixed++;
                    } else {
                        // No location string, nullify the reference
                        $invLocation->storage_location_id = null;
                        $invLocation->save();

                        $fixed++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'inventory_location_id' => $invLocation->id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'fixed' => $fixed,
                'errors' => $errors,
                'message' => "Successfully fixed {$fixed} orphaned locations"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Fix failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cleanup empty storage locations
     */
    public function cleanupEmpty(Request $request)
    {
        $dryRun = $request->input('dry_run', true);
        $deleted = 0;

        DB::beginTransaction();
        try {
            $emptyLocations = StorageLocation::whereDoesntHave('inventoryLocations')
                ->whereDoesntHave('children')
                ->get();

            foreach ($emptyLocations as $location) {
                if (!$dryRun) {
                    $location->delete();
                }
                $deleted++;
            }

            if (!$dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            return response()->json([
                'success' => true,
                'mode' => $dryRun ? 'dry_run' : 'actual',
                'deleted' => $deleted,
                'message' => $dryRun
                    ? "Would delete {$deleted} empty locations"
                    : "Successfully deleted {$deleted} empty locations"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fix duplicate slugs by regenerating them
     */
    public function fixDuplicates()
    {
        $fixed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            // Find locations with duplicate slugs
            $duplicates = DB::table('storage_locations')
                ->select('slug', DB::raw('GROUP_CONCAT(id) as ids'))
                ->whereNotNull('slug')
                ->groupBy('slug')
                ->having(DB::raw('count(*)'), '>', 1)
                ->get();

            foreach ($duplicates as $duplicate) {
                $ids = explode(',', $duplicate->ids);
                // Keep first one, regenerate slugs for others
                array_shift($ids);

                foreach ($ids as $id) {
                    try {
                        $location = StorageLocation::find($id);
                        if ($location) {
                            // Regenerate slug with ID suffix
                            $location->slug = $location->generateSlug() . '-' . $location->id;
                            $location->save();
                            $fixed++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = [
                            'location_id' => $id,
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'fixed' => $fixed,
                'errors' => $errors,
                'message' => "Successfully fixed {$fixed} duplicate slugs"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Fix failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync all location quantities (recalculate totals)
     */
    public function syncQuantities()
    {
        $synced = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            $products = DB::table('products')->get();

            foreach ($products as $product) {
                try {
                    $totals = DB::table('inventory_locations')
                        ->where('product_id', $product->id)
                        ->selectRaw('
                            COALESCE(SUM(quantity), 0) as total_quantity,
                            COALESCE(SUM(quantity_committed), 0) as total_committed
                        ')
                        ->first();

                    DB::table('products')
                        ->where('id', $product->id)
                        ->update([
                            'quantity_on_hand' => $totals->total_quantity ?? 0,
                            'quantity_committed' => $totals->total_committed ?? 0,
                        ]);

                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'synced' => $synced,
                'errors' => $errors,
                'message' => "Successfully synced {$synced} products"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed report of location usage
     */
    public function report()
    {
        $report = [
            'storage_locations' => [
                'total' => StorageLocation::count(),
                'active' => StorageLocation::where('is_active', true)->count(),
                'inactive' => StorageLocation::where('is_active', false)->count(),
                'root_locations' => StorageLocation::whereNull('parent_id')->count(),
                'with_inventory' => StorageLocation::has('inventoryLocations')->count(),
                'empty' => StorageLocation::doesntHave('inventoryLocations')->count(),
            ],
            'inventory_locations' => [
                'total' => InventoryLocation::count(),
                'with_storage_location' => InventoryLocation::whereNotNull('storage_location_id')->count(),
                'without_storage_location' => InventoryLocation::whereNull('storage_location_id')->count(),
            ],
            'hierarchy_depth' => $this->getMaxDepth(),
            'top_locations_by_inventory' => $this->getTopLocationsByInventory(),
        ];

        return response()->json($report);
    }

    /**
     * Get maximum hierarchy depth
     */
    private function getMaxDepth(): int
    {
        $maxDepth = 0;

        function checkDepth($location, $depth = 0) use (&$maxDepth) {
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }

            foreach ($location->children as $child) {
                checkDepth($child, $depth + 1);
            }
        }

        $roots = StorageLocation::whereNull('parent_id')->with('children')->get();
        foreach ($roots as $root) {
            checkDepth($root, 0);
        }

        return $maxDepth;
    }

    /**
     * Get top locations by inventory count
     */
    private function getTopLocationsByInventory(int $limit = 10): array
    {
        return StorageLocation::withCount('inventoryLocations')
            ->orderByDesc('inventory_locations_count')
            ->limit($limit)
            ->get()
            ->map(function ($location) {
                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'full_path' => $location->full_path,
                    'inventory_count' => $location->inventory_locations_count,
                ];
            })
            ->toArray();
    }
}
