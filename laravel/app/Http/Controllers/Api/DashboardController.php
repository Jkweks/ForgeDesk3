<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $categoryId = $request->get('category_id');

        // Base query for stats (apply category filter if present)
        $statsQuery = Product::where('is_active', true);
        if ($categoryId) {
            $statsQuery->where('category_id', $categoryId);
        }

        $stats = [
            'skus_tracked' => (clone $statsQuery)->count(),
            'units_on_hand' => (clone $statsQuery)->sum('quantity_on_hand'),
            'units_available' => DB::table('products')
                ->where('is_active', true)
                ->when($categoryId, function($q) use ($categoryId) {
                    return $q->where('category_id', $categoryId);
                })
                ->sum(DB::raw('quantity_on_hand - quantity_committed')),
            'low_stock_alerts' => (clone $statsQuery)->where(function($q) {
                $q->where('status', 'low_stock')->orWhere('status', 'critical');
            })->count(),
            'critical_count' => (clone $statsQuery)->where('status', 'critical')->count(),
            'pending_orders' => Order::whereIn('status', ['pending', 'processing'])->count(),
        ];

        $inventoryQuery = Product::with(['committedInventory.order', 'inventoryLocations', 'category'])
            ->where('is_active', true);

        if ($categoryId) {
            $inventoryQuery->where('category_id', $categoryId);
        }

        $inventory = $inventoryQuery->orderBy('sku')->paginate(50);

        $lowStock = Product::where('status', 'low_stock')
            ->where('is_active', true)
            ->when($categoryId, function($q) use ($categoryId) {
                return $q->where('category_id', $categoryId);
            })
            ->get();

        $criticalStock = Product::where('status', 'critical')
            ->where('is_active', true)
            ->when($categoryId, function($q) use ($categoryId) {
                return $q->where('category_id', $categoryId);
            })
            ->get();

        $committedParts = Product::whereHas('committedInventory')
            ->with(['committedInventory.order', 'committedInventory.orderItem'])
            ->where('quantity_committed', '>', 0)
            ->when($categoryId, function($q) use ($categoryId) {
                return $q->where('category_id', $categoryId);
            })
            ->get();

        return response()->json([
            'stats' => $stats,
            'inventory' => $inventory,
            'low_stock' => $lowStock,
            'critical_stock' => $criticalStock,
            'committed_parts' => $committedParts,
        ]);
    }

    public function inventoryByStatus(Request $request, $status)
    {
        $categoryId = $request->get('category_id');

        $products = Product::where('status', $status)
            ->where('is_active', true)
            ->when($categoryId, function($q) use ($categoryId) {
                return $q->where('category_id', $categoryId);
            })
            ->with('committedInventory.order', 'category')
            ->paginate(50);

        return response()->json($products);
    }

    public function stats()
    {
        return response()->json([
            'skus_tracked' => Product::where('is_active', true)->count(),
            'units_on_hand' => Product::sum('quantity_on_hand'),
            'units_available' => DB::table('products')->sum(DB::raw('quantity_on_hand - quantity_committed')),
            'low_stock_alerts' => Product::whereIn('status', ['low_stock', 'critical'])->count(),
            'critical_count' => Product::where('status', 'critical')->count(),
            'pending_orders' => Order::whereIn('status', ['pending', 'processing'])->count(),
        ]);
    }
}