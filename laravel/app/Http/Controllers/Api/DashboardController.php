<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'skus_tracked' => Product::where('is_active', true)->count(),
            'units_on_hand' => Product::sum('quantity_on_hand'),
            'units_available' => DB::table('products')->sum(DB::raw('quantity_on_hand - quantity_committed')),
            'low_stock_alerts' => Product::where('status', 'low_stock')->orWhere('status', 'critical')->count(),
            'critical_count' => Product::where('status', 'critical')->count(),
            'pending_orders' => Order::whereIn('status', ['pending', 'processing'])->count(),
        ];

        $inventory = Product::with(['committedInventory.order'])
            ->where('is_active', true)
            ->orderBy('sku')
            ->paginate(50);

        $lowStock = Product::where('status', 'low_stock')
            ->where('is_active', true)
            ->get();

        $criticalStock = Product::where('status', 'critical')
            ->where('is_active', true)
            ->get();

        $committedParts = Product::whereHas('committedInventory')
            ->with(['committedInventory.order', 'committedInventory.orderItem'])
            ->where('quantity_committed', '>', 0)
            ->get();

        return response()->json([
            'stats' => $stats,
            'inventory' => $inventory,
            'low_stock' => $lowStock,
            'critical_stock' => $criticalStock,
            'committed_parts' => $committedParts,
        ]);
    }

    public function inventoryByStatus($status)
    {
        $products = Product::where('status', $status)
            ->where('is_active', true)
            ->with('committedInventory.order')
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