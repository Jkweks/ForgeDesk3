<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\JobReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Calculate committed quantities from the fulfillment system (job_reservation_items)
     * Only includes reservations with active statuses: active, in_progress, on_hold
     */
    private function getCommittedFromFulfillment($categoryId = null)
    {
        $query = DB::table('job_reservation_items as ri')
            ->join('job_reservations as r', 'ri.reservation_id', '=', 'r.id')
            ->join('products as p', 'ri.product_id', '=', 'p.id')
            ->whereIn('r.status', ['active', 'in_progress', 'on_hold'])
            ->whereNull('r.deleted_at')
            ->where('p.is_active', true);

        if ($categoryId) {
            $query->where('p.category_id', $categoryId);
        }

        return $query->sum('ri.committed_qty');
    }

    /**
     * Get committed quantities grouped by product ID
     */
    private function getCommittedByProduct()
    {
        return DB::table('job_reservation_items as ri')
            ->join('job_reservations as r', 'ri.reservation_id', '=', 'r.id')
            ->whereIn('r.status', ['active', 'in_progress', 'on_hold'])
            ->whereNull('r.deleted_at')
            ->select('ri.product_id', DB::raw('SUM(ri.committed_qty) as committed_qty'))
            ->groupBy('ri.product_id')
            ->pluck('committed_qty', 'product_id')
            ->toArray();
    }

    public function index(Request $request)
    {
        $categoryId = $request->get('category_id');
        $perPage = $request->get('per_page', 50);
        $sortBy = $request->get('sort_by', 'sku');
        $sortDir = $request->get('sort_dir', 'asc');
        $search = $request->get('search');

        // Validate sort column to prevent SQL injection
        $allowedSortColumns = ['sku', 'description', 'quantity_on_hand', 'quantity_committed', 'quantity_available', 'status'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'sku';
        }
        $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        // Get committed quantities from fulfillment system
        $committedByProduct = $this->getCommittedByProduct();

        // Base query for stats (apply category filter if present)
        $statsQuery = Product::where('is_active', true);
        if ($categoryId) {
            $statsQuery->where('category_id', $categoryId);
        }
        if ($search) {
            $searchLower = strtolower($search);
            $statsQuery->where(function($q) use ($searchLower) {
                $q->whereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(part_number) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        // Calculate units_available using fulfillment system
        $unitsOnHand = (clone $statsQuery)->sum('quantity_on_hand');
        $unitsCommitted = $this->getCommittedFromFulfillment($categoryId);

        $stats = [
            'skus_tracked' => (clone $statsQuery)->count(),
            'units_on_hand' => $unitsOnHand,
            'units_committed' => $unitsCommitted,
            'units_available' => $unitsOnHand - $unitsCommitted,
            'low_stock_alerts' => (clone $statsQuery)->where(function($q) {
                $q->where('status', 'low_stock')->orWhere('status', 'critical');
            })->count(),
            'critical_count' => (clone $statsQuery)->where('status', 'critical')->count(),
            'pending_orders' => Order::whereIn('status', ['pending', 'processing'])->count(),
        ];

        $inventoryQuery = Product::with(['inventoryLocations', 'category'])
            ->where('is_active', true);

        if ($categoryId) {
            $inventoryQuery->where('category_id', $categoryId);
        }

        if ($search) {
            $searchLower = strtolower($search);
            $inventoryQuery->where(function($q) use ($searchLower) {
                $q->whereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(part_number) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        // Handle sorting - for calculated fields, we need to sort after fetching
        if (in_array($sortBy, ['quantity_committed', 'quantity_available'])) {
            // Fetch all matching products first, enrich, then sort and paginate manually
            $allProducts = $inventoryQuery->get();

            // Enrich with committed quantities
            $allProducts->transform(function ($product) use ($committedByProduct) {
                $committedQty = $committedByProduct[$product->id] ?? 0;
                $product->quantity_committed = $committedQty;
                $product->quantity_available = $product->quantity_on_hand - $committedQty;
                return $product;
            });

            // Sort by the calculated field
            $allProducts = $sortDir === 'asc'
                ? $allProducts->sortBy($sortBy)->values()
                : $allProducts->sortByDesc($sortBy)->values();

            // Manual pagination
            $total = $allProducts->count();
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;
            $items = $allProducts->slice($offset, $perPage)->values();

            $inventory = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            // Standard database sorting
            $inventory = $inventoryQuery->orderBy($sortBy, $sortDir)->paginate($perPage);

            // Enrich inventory items with real-time committed quantities from fulfillment
            $inventory->getCollection()->transform(function ($product) use ($committedByProduct) {
                $committedQty = $committedByProduct[$product->id] ?? 0;
                $product->quantity_committed = $committedQty;
                $product->quantity_available = $product->quantity_on_hand - $committedQty;
                return $product;
            });
        }

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

        // Get committed parts from fulfillment system
        $committedParts = Product::whereHas('reservationItems', function($query) {
                $query->whereHas('reservation', function($resQuery) {
                    $resQuery->whereIn('status', ['active', 'in_progress', 'on_hold']);
                });
            })
            ->with(['reservationItems' => function($query) {
                $query->whereHas('reservation', function($resQuery) {
                    $resQuery->whereIn('status', ['active', 'in_progress', 'on_hold']);
                })->with('reservation');
            }])
            ->where('is_active', true)
            ->when($categoryId, function($q) use ($categoryId) {
                return $q->where('category_id', $categoryId);
            })
            ->get()
            ->map(function ($product) {
                $committedQty = $product->reservationItems->sum('committed_qty');
                $product->quantity_committed = $committedQty;
                $product->quantity_available = $product->quantity_on_hand - $committedQty;
                return $product;
            });

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
        $perPage = $request->get('per_page', 50);
        $sortBy = $request->get('sort_by', 'sku');
        $sortDir = $request->get('sort_dir', 'asc');
        $search = $request->get('search');

        // Validate sort column
        $allowedSortColumns = ['sku', 'description', 'quantity_on_hand', 'quantity_committed', 'quantity_available', 'status'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'sku';
        }
        $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        // Get committed quantities from fulfillment system
        $committedByProduct = $this->getCommittedByProduct();

        $query = Product::where('status', $status)
            ->where('is_active', true)
            ->when($categoryId, function($q) use ($categoryId) {
                return $q->where('category_id', $categoryId);
            })
            ->when($search, function($q) use ($search) {
                $searchLower = strtolower($search);
                return $q->where(function($sq) use ($searchLower) {
                    $sq->whereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                       ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"])
                       ->orWhereRaw('LOWER(part_number) LIKE ?', ["%{$searchLower}%"]);
                });
            })
            ->with('category');

        // Handle sorting for calculated fields
        if (in_array($sortBy, ['quantity_committed', 'quantity_available'])) {
            $allProducts = $query->get();

            $allProducts->transform(function ($product) use ($committedByProduct) {
                $committedQty = $committedByProduct[$product->id] ?? 0;
                $product->quantity_committed = $committedQty;
                $product->quantity_available = $product->quantity_on_hand - $committedQty;
                return $product;
            });

            $allProducts = $sortDir === 'asc'
                ? $allProducts->sortBy($sortBy)->values()
                : $allProducts->sortByDesc($sortBy)->values();

            $total = $allProducts->count();
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;
            $items = $allProducts->slice($offset, $perPage)->values();

            $products = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $products = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

            $products->getCollection()->transform(function ($product) use ($committedByProduct) {
                $committedQty = $committedByProduct[$product->id] ?? 0;
                $product->quantity_committed = $committedQty;
                $product->quantity_available = $product->quantity_on_hand - $committedQty;
                return $product;
            });
        }

        return response()->json($products);
    }

    public function stats()
    {
        // Calculate committed from fulfillment system
        $unitsCommitted = $this->getCommittedFromFulfillment();
        $unitsOnHand = Product::where('is_active', true)->sum('quantity_on_hand');

        return response()->json([
            'skus_tracked' => Product::where('is_active', true)->count(),
            'units_on_hand' => $unitsOnHand,
            'units_committed' => $unitsCommitted,
            'units_available' => $unitsOnHand - $unitsCommitted,
            'low_stock_alerts' => Product::whereIn('status', ['low_stock', 'critical'])->count(),
            'critical_count' => Product::where('status', 'critical')->count(),
            'pending_orders' => Order::whereIn('status', ['pending', 'processing'])->count(),
        ]);
    }
}