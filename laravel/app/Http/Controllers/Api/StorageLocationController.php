<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StorageLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StorageLocationController extends Controller
{
    /**
     * Display a listing of storage locations
     */
    public function index(Request $request)
    {
        $query = StorageLocation::query();

        // Filter by active status
        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Search by name or code
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Order
        $query->ordered();

        // Pagination
        $perPage = $request->get('per_page', 50);
        if ($perPage === 'all') {
            $locations = $query->get();
            return response()->json($locations);
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * Get locations with inventory usage statistics
     */
    public function withStats(Request $request)
    {
        $query = StorageLocation::query()->with('inventoryLocations.product');

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        $locations = $query->ordered()->get();

        // Add statistics to each location
        $locationsWithStats = $locations->map(function ($location) {
            $inventoryLocs = $location->inventoryLocations;

            return [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'type' => $location->type,
                'description' => $location->description,
                'aisle' => $location->aisle,
                'bay' => $location->bay,
                'level' => $location->level,
                'position' => $location->position,
                'full_address' => $location->full_address,
                'capacity' => $location->capacity,
                'capacity_unit' => $location->capacity_unit,
                'is_active' => $location->is_active,
                'sort_order' => $location->sort_order,
                'notes' => $location->notes,
                'created_at' => $location->created_at,
                'updated_at' => $location->updated_at,
                'stats' => [
                    'products_count' => $inventoryLocs->count(),
                    'total_quantity' => $inventoryLocs->sum('quantity'),
                    'total_committed' => $inventoryLocs->sum('quantity_committed'),
                    'total_available' => $inventoryLocs->sum(function ($il) {
                        return $il->quantity - $il->quantity_committed;
                    }),
                    'total_value' => $inventoryLocs->sum(function ($il) {
                        return $il->quantity * ($il->product->unit_cost ?? 0);
                    }),
                ],
            ];
        });

        return response()->json($locationsWithStats);
    }

    /**
     * Store a newly created storage location
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:storage_locations,name',
            'code' => 'nullable|string|max:50|unique:storage_locations,code',
            'type' => 'required|in:warehouse,shelf,bin,rack,zone,other',
            'description' => 'nullable|string',
            'aisle' => 'nullable|string|max:50',
            'bay' => 'nullable|string|max:50',
            'level' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:50',
            'capacity' => 'nullable|numeric|min:0',
            'capacity_unit' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Auto-generate code if not provided
        if (empty($request->code)) {
            $request->merge(['code' => $this->generateLocationCode($request->name)]);
        }

        $location = StorageLocation::create($validator->validated());

        return response()->json($location, 201);
    }

    /**
     * Display the specified storage location
     */
    public function show(StorageLocation $storageLocation)
    {
        $storageLocation->load('inventoryLocations.product');

        $stats = [
            'products_count' => $storageLocation->inventoryLocations->count(),
            'total_quantity' => $storageLocation->inventoryLocations->sum('quantity'),
            'total_committed' => $storageLocation->inventoryLocations->sum('quantity_committed'),
            'total_available' => $storageLocation->inventoryLocations->sum(function ($il) {
                return $il->quantity - $il->quantity_committed;
            }),
        ];

        return response()->json([
            'location' => $storageLocation,
            'stats' => $stats,
            'inventory_locations' => $storageLocation->inventoryLocations,
        ]);
    }

    /**
     * Update the specified storage location
     */
    public function update(Request $request, StorageLocation $storageLocation)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:storage_locations,name,' . $storageLocation->id,
            'code' => 'nullable|string|max:50|unique:storage_locations,code,' . $storageLocation->id,
            'type' => 'sometimes|required|in:warehouse,shelf,bin,rack,zone,other',
            'description' => 'nullable|string',
            'aisle' => 'nullable|string|max:50',
            'bay' => 'nullable|string|max:50',
            'level' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:50',
            'capacity' => 'nullable|numeric|min:0',
            'capacity_unit' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $storageLocation->update($validator->validated());

        return response()->json($storageLocation);
    }

    /**
     * Remove the specified storage location
     */
    public function destroy(StorageLocation $storageLocation)
    {
        // Check if location is being used
        if ($storageLocation->inventoryLocations()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete location that is currently in use. Please remove all inventory from this location first.',
                'inventory_count' => $storageLocation->inventoryLocations()->count(),
            ], 422);
        }

        $storageLocation->delete();

        return response()->json(['message' => 'Storage location deleted successfully']);
    }

    /**
     * Get available location names for autocomplete
     */
    public function locationNames(Request $request)
    {
        $query = StorageLocation::active();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $locations = $query->ordered()->limit(50)->pluck('name');

        return response()->json($locations);
    }

    /**
     * Generate a location code from name
     */
    private function generateLocationCode($name)
    {
        // Remove special characters and convert to uppercase
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));

        // Limit to 10 characters
        $code = substr($code, 0, 10);

        // Ensure uniqueness
        $originalCode = $code;
        $counter = 1;
        while (StorageLocation::where('code', $code)->exists()) {
            $code = $originalCode . $counter;
            $counter++;
        }

        return $code;
    }
}
