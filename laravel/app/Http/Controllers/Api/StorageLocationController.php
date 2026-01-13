<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StorageLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorageLocationController extends Controller
{
    /**
     * Get all storage locations (flat list)
     */
    public function index(Request $request)
    {
        $query = StorageLocation::query()->with(['parent', 'children']);

        // Filter by active status
        if ($request->has('active_only')) {
            $query->where('is_active', true);
        }

        // Filter by parent
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Filter by level (root locations)
        if ($request->has('roots_only')) {
            $query->whereNull('parent_id');
        }

        $locations = $query->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($locations);
    }

    /**
     * Get hierarchical tree of storage locations
     */
    public function tree()
    {
        $tree = StorageLocation::tree();
        return response()->json($tree);
    }

    /**
     * Get a specific storage location with details
     */
    public function show(StorageLocation $storageLocation)
    {
        $storageLocation->load([
            'parent',
            'children',
            'inventoryLocations.product'
        ]);

        // Add computed totals
        $data = $storageLocation->toArray();
        $data['total_quantity'] = $storageLocation->totalQuantity();
        $data['total_committed'] = $storageLocation->totalCommittedQuantity();
        $data['total_available'] = $storageLocation->totalAvailableQuantity();
        $data['product_count'] = $storageLocation->inventoryLocations()->distinct('product_id')->count();

        return response()->json($data);
    }

    /**
     * Create a new storage location
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:storage_locations,id',
            'name' => 'required|string|max:255',
            'aisle' => 'nullable|string|max:50',
            'rack' => 'nullable|string|max:50',
            'shelf' => 'nullable|string|max:50',
            'bin' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        // Auto-parse location name if components not provided
        if (!$validated['aisle'] && !$validated['rack'] && !$validated['shelf'] && !$validated['bin']) {
            $components = StorageLocation::parseLocationName($validated['name']);
            $validated = array_merge($validated, $components);
        }

        $location = StorageLocation::create($validated);

        return response()->json($location, 201);
    }

    /**
     * Update a storage location
     */
    public function update(Request $request, StorageLocation $storageLocation)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:storage_locations,id',
            'name' => 'required|string|max:255',
            'aisle' => 'nullable|string|max:50',
            'rack' => 'nullable|string|max:50',
            'shelf' => 'nullable|string|max:50',
            'bin' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        // Prevent circular references
        if (isset($validated['parent_id']) && $validated['parent_id']) {
            if ($this->wouldCreateCircularReference($storageLocation, $validated['parent_id'])) {
                return response()->json([
                    'message' => 'Cannot set parent: would create circular reference',
                    'errors' => ['parent_id' => ['Circular reference detected']]
                ], 422);
            }
        }

        $storageLocation->update($validated);

        return response()->json($storageLocation);
    }

    /**
     * Delete a storage location
     */
    public function destroy(StorageLocation $storageLocation)
    {
        // Check if location has inventory
        if ($storageLocation->inventoryLocations()->exists()) {
            return response()->json([
                'message' => 'Cannot delete location with inventory. Please move or remove inventory first.',
                'errors' => ['inventory' => ['Location contains inventory']]
            ], 422);
        }

        // Check if location has children
        if ($storageLocation->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete location with child locations. Please delete or move children first.',
                'errors' => ['children' => ['Location has child locations']]
            ], 422);
        }

        $storageLocation->delete();

        return response()->json(['message' => 'Storage location deleted successfully'], 200);
    }

    /**
     * Get ancestors (breadcrumb path) for a location
     */
    public function ancestors(StorageLocation $storageLocation)
    {
        $ancestors = $storageLocation->ancestors();
        return response()->json($ancestors);
    }

    /**
     * Get all descendants of a location
     */
    public function descendants(StorageLocation $storageLocation)
    {
        $descendants = $storageLocation->descendants()->get();
        return response()->json($descendants);
    }

    /**
     * Move a location to a new parent
     */
    public function move(Request $request, StorageLocation $storageLocation)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:storage_locations,id',
        ]);

        // Prevent circular references
        if (isset($validated['parent_id']) && $validated['parent_id']) {
            if ($this->wouldCreateCircularReference($storageLocation, $validated['parent_id'])) {
                return response()->json([
                    'message' => 'Cannot move location: would create circular reference',
                    'errors' => ['parent_id' => ['Circular reference detected']]
                ], 422);
            }
        }

        $storageLocation->parent_id = $validated['parent_id'];
        $storageLocation->save();

        return response()->json([
            'message' => 'Location moved successfully',
            'location' => $storageLocation->fresh(['parent', 'children'])
        ]);
    }

    /**
     * Parse location name to extract components
     */
    public function parse(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
        ]);

        $components = StorageLocation::parseLocationName($validated['name']);

        return response()->json([
            'name' => $validated['name'],
            'components' => $components,
            'formatted_name' => $this->formatComponentsToName($components),
        ]);
    }

    /**
     * Search locations by name or components
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|min:1',
        ]);

        $query = $validated['query'];

        $locations = StorageLocation::where('name', 'like', "%{$query}%")
            ->orWhere('aisle', 'like', "%{$query}%")
            ->orWhere('rack', 'like', "%{$query}%")
            ->orWhere('shelf', 'like', "%{$query}%")
            ->orWhere('bin', 'like', "%{$query}%")
            ->orWhere('slug', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->where('is_active', true)
            ->with(['parent'])
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json($locations);
    }

    /**
     * Get statistics for a location (including children)
     */
    public function statistics(StorageLocation $storageLocation)
    {
        $inventoryCount = $storageLocation->inventoryLocations()->count();
        $productCount = $storageLocation->inventoryLocations()->distinct('product_id')->count();
        $childCount = $storageLocation->children()->count();

        $stats = [
            'location_id' => $storageLocation->id,
            'location_name' => $storageLocation->name,
            'full_path' => $storageLocation->full_path,
            'total_quantity' => $storageLocation->totalQuantity(),
            'total_committed' => $storageLocation->totalCommittedQuantity(),
            'total_available' => $storageLocation->totalAvailableQuantity(),
            'inventory_count' => $inventoryCount,
            'product_count' => $productCount,
            'child_location_count' => $childCount,
            'is_leaf' => $childCount === 0,
            'level' => $storageLocation->level,
        ];

        return response()->json($stats);
    }

    /**
     * Bulk create locations from a path
     * Example: "1.2.3.4" creates Aisle 1 → Rack 2 → Shelf 3 → Bin 4
     */
    public function bulkCreateFromPath(Request $request)
    {
        $validated = $request->validate([
            'path' => 'required|string', // e.g., "1.2.3.4"
            'labels' => 'nullable|array', // Optional custom labels
        ]);

        $parts = explode('.', $validated['path']);
        $labels = $validated['labels'] ?? [];
        $parent = null;
        $locations = [];

        DB::beginTransaction();
        try {
            foreach ($parts as $index => $part) {
                $level = $index; // 0=aisle, 1=rack, 2=shelf, 3=bin
                $componentName = $this->getComponentName($level);
                $label = $labels[$index] ?? ucfirst($componentName) . ' ' . $part;

                // Check if location already exists
                $location = StorageLocation::where('parent_id', $parent ? $parent->id : null)
                    ->where($componentName, $part)
                    ->first();

                if (!$location) {
                    $data = [
                        'parent_id' => $parent ? $parent->id : null,
                        'name' => $label,
                        'is_active' => true,
                    ];
                    $data[$componentName] = $part;

                    $location = StorageLocation::create($data);
                }

                $locations[] = $location;
                $parent = $location;
            }

            DB::commit();

            return response()->json([
                'message' => 'Locations created successfully',
                'locations' => $locations,
                'leaf_location' => end($locations),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create locations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Check if setting a parent would create a circular reference
     */
    private function wouldCreateCircularReference(StorageLocation $location, int $newParentId): bool
    {
        if ($location->id === $newParentId) {
            return true;
        }

        $parent = StorageLocation::find($newParentId);
        while ($parent) {
            if ($parent->id === $location->id) {
                return true;
            }
            $parent = $parent->parent;
        }

        return false;
    }

    /**
     * Helper: Get component name by level
     */
    private function getComponentName(int $level): string
    {
        return ['aisle', 'rack', 'shelf', 'bin'][$level] ?? 'bin';
    }

    /**
     * Helper: Format components to name
     */
    private function formatComponentsToName(array $components): string
    {
        $parts = [];

        if ($components['aisle']) {
            $parts[] = "Aisle {$components['aisle']}";
        }
        if ($components['rack']) {
            $parts[] = "Rack {$components['rack']}";
        }
        if ($components['shelf']) {
            $parts[] = "Shelf {$components['shelf']}";
        }
        if ($components['bin']) {
            $parts[] = "Bin {$components['bin']}";
        }

        return !empty($parts) ? implode(', ', $parts) : '';
    }
}
