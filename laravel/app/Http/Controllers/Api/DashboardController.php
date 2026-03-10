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

    /**
     * Calculate all dashboard stats in a single aggregation query.
     * Replaces 5+ separate queries and PHP-side pack calculation loops.
     */
    private function calculateStats($categoryId = null, $search = null): array
    {
        $query = DB::table('products as p')
            ->leftJoin(DB::raw('(
                SELECT ri.product_id, SUM(ri.committed_qty) as committed_qty
                FROM job_reservation_items ri
                JOIN job_reservations r ON ri.reservation_id = r.id
                WHERE r.status IN (\'active\', \'in_progress\', \'on_hold\')
                AND r.deleted_at IS NULL
                GROUP BY ri.product_id
            ) as committed'), 'committed.product_id', '=', 'p.id')
            ->where('p.is_active', true);

        if ($categoryId) {
            $query->whereExists(function ($sub) use ($categoryId) {
                $sub->select(DB::raw(1))
                    ->from('category_product as cp')
                    ->whereColumn('cp.product_id', 'p.id')
                    ->where('cp.category_id', $categoryId);
            });
        }

        if ($search) {
            $searchLower = strtolower($search);
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(p.sku) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(p.description) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(p.part_number) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        $agg = $query->selectRaw('
            COUNT(*) as skus_tracked,
            SUM(CASE WHEN p.pack_size > 1
                THEN FLOOR(COALESCE(p.quantity_on_hand, 0) / p.pack_size)
                ELSE COALESCE(p.quantity_on_hand, 0) END) as units_on_hand,
            SUM(CASE WHEN p.pack_size > 1
                THEN CEIL(COALESCE(committed.committed_qty, 0) / p.pack_size)
                ELSE COALESCE(committed.committed_qty, 0) END) as units_committed,
            SUM(CASE WHEN p.status IN (\'low_stock\', \'critical\') THEN 1 ELSE 0 END) as low_stock_alerts,
            SUM(CASE WHEN p.status = \'critical\' THEN 1 ELSE 0 END) as critical_count
        ')->first();

        $unitsOnHand = (int) ($agg->units_on_hand ?? 0);
        $unitsCommitted = (int) ($agg->units_committed ?? 0);

        return [
            'skus_tracked'     => (int) ($agg->skus_tracked ?? 0),
            'units_on_hand'    => $unitsOnHand,
            'units_committed'  => $unitsCommitted,
            'units_available'  => max(0, $unitsOnHand - $unitsCommitted),
            'low_stock_alerts' => (int) ($agg->low_stock_alerts ?? 0),
            'critical_count'   => (int) ($agg->critical_count ?? 0),
            'pending_orders'   => Order::whereIn('status', ['pending', 'processing'])->count(),
        ];
    }

    /**
     * Apply sort to a query, including DB-level sorting for calculated committed/available fields.
     * Eliminates the need to load all records into memory just to sort by these fields.
     */
    private function applySort($query, string $sortBy, string $sortDir)
    {
        $committedSubquery = "COALESCE((
            SELECT SUM(ri.committed_qty)
            FROM job_reservation_items ri
            JOIN job_reservations r ON ri.reservation_id = r.id
            WHERE ri.product_id = products.id
            AND r.status IN ('active', 'in_progress', 'on_hold')
            AND r.deleted_at IS NULL
        ), 0)";

        if ($sortBy === 'quantity_committed') {
            return $query->orderByRaw("{$committedSubquery} {$sortDir}");
        }

        if ($sortBy === 'quantity_available') {
            return $query->orderByRaw("(products.quantity_on_hand - {$committedSubquery}) {$sortDir}");
        }

        return $query->orderBy($sortBy, $sortDir);
    }

    public function index(Request $request)
    {
        $categoryId = $request->get('category_id');
        $perPage    = $request->get('per_page', 50);
        $sortBy     = $request->get('sort_by', 'sku');
        $sortDir    = $request->get('sort_dir', 'asc');
        $search     = $request->get('search');

        // Validate sort column to prevent SQL injection
        $allowedSortColumns = ['sku', 'description', 'quantity_on_hand', 'quantity_committed', 'quantity_available', 'status'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'sku';
        }
        $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        // Get committed quantities for row-level enrichment (pack-aware display)
        $committedByProduct = $this->getCommittedByProduct();

        // Calculate all stats in a single aggregation query (replaces 5+ separate queries)
        $stats = $this->calculateStats($categoryId, $search);

        $inventoryQuery = Product::with(['inventoryLocations', 'categories'])
            ->where('is_active', true);

        if ($categoryId) {
            $inventoryQuery->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        if ($search) {
            $searchLower = strtolower($search);
            $inventoryQuery->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(part_number) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        // DB-level sort for all columns — no more in-memory sort+paginate
        $inventory = $this->applySort($inventoryQuery, $sortBy, $sortDir)->paginate($perPage);

        // Enrich page items with pack-aware committed quantities
        $inventory->getCollection()->transform(function ($product) use ($committedByProduct) {
            $committedQty   = $committedByProduct[$product->id] ?? 0;
            $committedPacks = $product->eachesToPacksNeeded($committedQty);
            $onHandPacks    = $product->eachesToFullPacks($product->quantity_on_hand);

            $product->quantity_committed       = $committedQty;
            $product->quantity_committed_packs = $committedPacks;
            $product->quantity_available       = $product->quantity_on_hand - $committedQty;
            $product->quantity_available_packs = max(0, $onHandPacks - $committedPacks);
            return $product;
        });

        $lowStock = Product::where('status', 'low_stock')
            ->where('is_active', true)
            ->when($categoryId, function ($q) use ($categoryId) {
                return $q->whereHas('categories', function ($sq) use ($categoryId) {
                    $sq->where('categories.id', $categoryId);
                });
            })
            ->get();

        $criticalStock = Product::where('status', 'critical')
            ->where('is_active', true)
            ->when($categoryId, function ($q) use ($categoryId) {
                return $q->whereHas('categories', function ($sq) use ($categoryId) {
                    $sq->where('categories.id', $categoryId);
                });
            })
            ->get();

        // Get committed parts from fulfillment system
        $committedParts = Product::whereHas('reservationItems', function ($query) {
                $query->whereHas('reservation', function ($resQuery) {
                    $resQuery->whereIn('status', ['active', 'in_progress', 'on_hold']);
                });
            })
            ->with(['reservationItems' => function ($query) {
                $query->whereHas('reservation', function ($resQuery) {
                    $resQuery->whereIn('status', ['active', 'in_progress', 'on_hold']);
                })->with('reservation');
            }])
            ->where('is_active', true)
            ->when($categoryId, function ($q) use ($categoryId) {
                return $q->whereHas('categories', function ($sq) use ($categoryId) {
                    $sq->where('categories.id', $categoryId);
                });
            })
            ->get()
            ->map(function ($product) {
                $committedQty   = $product->reservationItems->sum('committed_qty');
                $committedPacks = $product->eachesToPacksNeeded($committedQty);
                $onHandPacks    = $product->eachesToFullPacks($product->quantity_on_hand);

                $product->quantity_committed       = $committedQty;
                $product->quantity_committed_packs = $committedPacks;
                $product->quantity_available       = $product->quantity_on_hand - $committedQty;
                $product->quantity_available_packs = max(0, $onHandPacks - $committedPacks);
                return $product;
            });

        return response()->json([
            'stats'           => $stats,
            'inventory'       => $inventory,
            'low_stock'       => $lowStock,
            'critical_stock'  => $criticalStock,
            'committed_parts' => $committedParts,
        ]);
    }

    public function inventoryByStatus(Request $request, $status)
    {
        $categoryId = $request->get('category_id');
        $perPage    = $request->get('per_page', 50);
        $sortBy     = $request->get('sort_by', 'sku');
        $sortDir    = $request->get('sort_dir', 'asc');
        $search     = $request->get('search');

        // Validate sort column
        $allowedSortColumns = ['sku', 'description', 'quantity_on_hand', 'quantity_committed', 'quantity_available', 'status'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'sku';
        }
        $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        // Get committed quantities for row-level enrichment
        $committedByProduct = $this->getCommittedByProduct();

        $query = Product::where('status', $status)
            ->where('is_active', true)
            ->when($categoryId, function ($q) use ($categoryId) {
                return $q->whereHas('categories', function ($sq) use ($categoryId) {
                    $sq->where('categories.id', $categoryId);
                });
            })
            ->when($search, function ($q) use ($search) {
                $searchLower = strtolower($search);
                return $q->where(function ($sq) use ($searchLower) {
                    $sq->whereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"])
                       ->orWhereRaw('LOWER(description) LIKE ?', ["%{$searchLower}%"])
                       ->orWhereRaw('LOWER(part_number) LIKE ?', ["%{$searchLower}%"]);
                });
            })
            ->with('categories');

        // DB-level sort for all columns — no more in-memory sort+paginate
        $products = $this->applySort($query, $sortBy, $sortDir)->paginate($perPage);

        $products->getCollection()->transform(function ($product) use ($committedByProduct) {
            $committedQty   = $committedByProduct[$product->id] ?? 0;
            $committedPacks = $product->eachesToPacksNeeded($committedQty);
            $onHandPacks    = $product->eachesToFullPacks($product->quantity_on_hand);

            $product->quantity_committed       = $committedQty;
            $product->quantity_committed_packs = $committedPacks;
            $product->quantity_available       = $product->quantity_on_hand - $committedQty;
            $product->quantity_available_packs = max(0, $onHandPacks - $committedPacks);
            return $product;
        });

        return response()->json($products);
    }

    public function stats()
    {
        return response()->json($this->calculateStats());
    }
}
