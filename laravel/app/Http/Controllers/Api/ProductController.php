<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->with(['inventoryLocations']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('location')) {
            $query->where('location', $request->location);
        }

        return response()->json($query->paginate(50));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|unique:products|max:255',
            'description' => 'required|max:255',
            'long_description' => 'nullable',
            'category' => 'nullable|max:255',
            'location' => 'nullable|max:255',
            'unit_cost' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'quantity_on_hand' => 'required|integer|min:0',
            'minimum_quantity' => 'required|integer|min:0',
            'maximum_quantity' => 'nullable|integer|min:0',
            'unit_of_measure' => 'required|max:10',
            'supplier' => 'nullable|max:255',
            'supplier_sku' => 'nullable|max:255',
            'lead_time_days' => 'nullable|integer|min:0',
        ]);

        $product = Product::create($validated);
        $product->updateStatus();

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load([
            'inventoryLocations',
            'jobReservations.reservedBy',
            'jobReservations.releasedBy',
            'inventoryTransactions',
            'orderItems.order',
            'committedInventory.order'
        ]));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'sku' => ['required', 'max:255', Rule::unique('products')->ignore($product->id)],
            'description' => 'required|max:255',
            'long_description' => 'nullable',
            'category' => 'nullable|max:255',
            'location' => 'nullable|max:255',
            'unit_cost' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'minimum_quantity' => 'required|integer|min:0',
            'maximum_quantity' => 'nullable|integer|min:0',
            'unit_of_measure' => 'required|max:10',
            'supplier' => 'nullable|max:255',
            'supplier_sku' => 'nullable|max:255',
            'lead_time_days' => 'nullable|integer|min:0',
        ]);

        $product->update($validated);
        $product->updateStatus();

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(null, 204);
    }

    public function adjustInventory(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:receipt,shipment,adjustment,transfer,return',
            'reference_number' => 'nullable|max:255',
            'notes' => 'nullable',
        ]);

        $product->adjustQuantity(
            $validated['quantity'],
            $validated['type'],
            $validated['reference_number'] ?? null,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Inventory adjusted successfully',
            'product' => $product->fresh()
        ]);
    }

    public function getTransactions(Product $product)
    {
        $transactions = $product->inventoryTransactions()
            ->with('user')
            ->latest('transaction_date')
            ->paginate(50);
        
        return response()->json($transactions);
    }
}