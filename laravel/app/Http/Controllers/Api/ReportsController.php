<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsController extends Controller
{
    /**
     * Get low stock and critical stock report
     */
    public function lowStockReport(Request $request)
    {
        $lowStock = Product::where('status', 'low_stock')
            ->where('is_active', true)
            ->with(['category', 'supplier', 'inventoryLocations'])
            ->get()
            ->map(function ($product) {
                return $this->enrichProductData($product);
            });

        $critical = Product::where('status', 'critical')
            ->where('is_active', true)
            ->with(['category', 'supplier', 'inventoryLocations'])
            ->get()
            ->map(function ($product) {
                return $this->enrichProductData($product);
            });

        return response()->json([
            'low_stock' => $lowStock,
            'critical' => $critical,
            'summary' => [
                'low_stock_count' => $lowStock->count(),
                'critical_count' => $critical->count(),
                'total_affected' => $lowStock->count() + $critical->count(),
                'estimated_value_at_risk' => $lowStock->sum('total_value') + $critical->sum('total_value'),
            ],
        ]);
    }

    /**
     * Get committed parts report
     */
    public function committedPartsReport(Request $request)
    {
        $committedProducts = Product::where('quantity_committed', '>', 0)
            ->where('is_active', true)
            ->with(['category', 'supplier', 'jobReservations' => function($query) {
                $query->where('status', 'active');
            }])
            ->get()
            ->map(function ($product) {
                $data = $this->enrichProductData($product);
                $data['reservations'] = $product->jobReservations->map(function ($reservation) {
                    return [
                        'id' => $reservation->id,
                        'job_number' => $reservation->job_number,
                        'quantity' => $reservation->quantity_reserved,
                        'reserved_date' => $reservation->reserved_date,
                    ];
                });
                return $data;
            });

        return response()->json([
            'committed_products' => $committedProducts,
            'summary' => [
                'total_products' => $committedProducts->count(),
                'total_quantity_committed' => $committedProducts->sum('committed'),
                'total_value_committed' => $committedProducts->sum(function($p) {
                    return $p['committed'] * $p['unit_cost'];
                }),
            ],
        ]);
    }

    /**
     * Stock velocity analysis
     */
    public function stockVelocityAnalysis(Request $request)
    {
        $days = $request->get('days', 90);
        $since = Carbon::now()->subDays($days);

        $products = Product::where('is_active', true)
            ->with(['category', 'supplier'])
            ->get();

        $velocityData = $products->map(function ($product) use ($since) {
            // Get transaction history
            $transactions = InventoryTransaction::where('product_id', $product->id)
                ->where('transaction_date', '>=', $since)
                ->get();

            $receipts = $transactions->where('type', 'receipt')->sum('quantity');
            $shipments = abs($transactions->where('type', 'shipment')->sum('quantity'));
            $adjustments = $transactions->where('type', 'adjustment')->sum('quantity');

            $turnoverRate = $product->quantity_on_hand > 0
                ? round(($shipments / max($product->quantity_on_hand, 1)) * 100, 2)
                : 0;

            // Classify velocity
            $velocity = 'slow';
            if ($turnoverRate > 200) $velocity = 'fast';
            elseif ($turnoverRate > 100) $velocity = 'medium';

            // Days until stockout
            $daysUntilStockout = null;
            if ($product->average_daily_use > 0) {
                $daysUntilStockout = round($product->quantity_available / $product->average_daily_use);
            }

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'description' => $product->description,
                'category' => $product->category?->name,
                'on_hand' => $product->quantity_on_hand,
                'available' => $product->quantity_available,
                'receipts' => $receipts,
                'shipments' => $shipments,
                'turnover_rate' => $turnoverRate,
                'velocity' => $velocity,
                'average_daily_use' => $product->average_daily_use,
                'days_until_stockout' => $daysUntilStockout,
            ];
        });

        // Sort by velocity (fastest first)
        $sorted = $velocityData->sortByDesc('turnover_rate')->values();

        return response()->json([
            'products' => $sorted,
            'summary' => [
                'fast_movers' => $sorted->where('velocity', 'fast')->count(),
                'medium_movers' => $sorted->where('velocity', 'medium')->count(),
                'slow_movers' => $sorted->where('velocity', 'slow')->count(),
                'total_analyzed' => $sorted->count(),
            ],
        ]);
    }

    /**
     * Reorder recommendations
     */
    public function reorderRecommendations(Request $request)
    {
        $products = Product::where('is_active', true)
            ->where(function($query) {
                // Products at or below reorder point (using actual DB columns)
                $query->whereRaw('(quantity_on_hand - quantity_committed) <= reorder_point')
                    // Or products below minimum
                    ->orWhereRaw('(quantity_on_hand - quantity_committed) <= minimum_quantity');
            })
            ->with(['category', 'supplier'])
            ->get()
            ->map(function ($product) {
                return $this->enrichProductData($product, true);
            })
            ->sortByDesc('priority_score')
            ->values();

        return response()->json([
            'recommendations' => $products,
            'summary' => [
                'items_to_reorder' => $products->count(),
                'total_order_value' => $products->sum('recommended_order_value'),
                'critical_items' => $products->where('status', 'critical')->count(),
            ],
        ]);
    }

    /**
     * Obsolete inventory detection
     */
    public function obsoleteInventory(Request $request)
    {
        $inactiveDays = $request->get('inactive_days', 180);
        $since = Carbon::now()->subDays($inactiveDays);

        $products = Product::where('is_active', true)
            ->where('is_discontinued', false)
            ->with(['category', 'supplier'])
            ->get();

        $obsoleteCandidates = $products->filter(function ($product) use ($since) {
            // Get last transaction
            $lastTransaction = InventoryTransaction::where('product_id', $product->id)
                ->where('type', 'shipment')
                ->orderBy('transaction_date', 'desc')
                ->first();

            if (!$lastTransaction) {
                return true; // Never shipped = potentially obsolete
            }

            return $lastTransaction->transaction_date < $since;
        })->map(function ($product) {
            $lastShipment = InventoryTransaction::where('product_id', $product->id)
                ->where('type', 'shipment')
                ->orderBy('transaction_date', 'desc')
                ->first();

            $daysSinceLastUse = $lastShipment
                ? Carbon::now()->diffInDays($lastShipment->transaction_date)
                : 999;

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'description' => $product->description,
                'category' => $product->category?->name,
                'on_hand' => $product->quantity_on_hand,
                'unit_cost' => $product->unit_cost,
                'total_value' => $product->quantity_on_hand * $product->unit_cost,
                'last_shipment_date' => $lastShipment?->transaction_date,
                'days_since_last_use' => $daysSinceLastUse,
                'is_used_in_bom' => $product->usedInProducts()->count() > 0,
            ];
        })->values();

        return response()->json([
            'obsolete_candidates' => $obsoleteCandidates,
            'summary' => [
                'total_items' => $obsoleteCandidates->count(),
                'total_value_at_risk' => $obsoleteCandidates->sum('total_value'),
                'used_in_bom' => $obsoleteCandidates->where('is_used_in_bom', true)->count(),
            ],
        ]);
    }

    /**
     * Usage analytics over time
     */
    public function usageAnalytics(Request $request)
    {
        $days = $request->get('days', 30);
        $since = Carbon::now()->subDays($days);

        $transactions = InventoryTransaction::where('transaction_date', '>=', $since)
            ->with('product')
            ->get();

        // Group by date
        $byDate = $transactions->groupBy(function ($transaction) {
            return Carbon::parse($transaction->transaction_date)->format('Y-m-d');
        })->map(function ($dayTransactions, $date) {
            return [
                'date' => $date,
                'receipts' => $dayTransactions->where('type', 'receipt')->sum('quantity'),
                'shipments' => abs($dayTransactions->where('type', 'shipment')->sum('quantity')),
                'adjustments' => $dayTransactions->where('type', 'adjustment')->count(),
                'cycle_counts' => $dayTransactions->where('type', 'cycle_count')->count(),
                'total_transactions' => $dayTransactions->count(),
            ];
        })->values();

        // Group by category
        $byCategory = $transactions->groupBy(function ($transaction) {
            return $transaction->product->category?->name ?? 'Uncategorized';
        })->map(function ($categoryTransactions, $category) {
            return [
                'category' => $category,
                'receipts' => $categoryTransactions->where('type', 'receipt')->sum('quantity'),
                'shipments' => abs($categoryTransactions->where('type', 'shipment')->sum('quantity')),
                'transaction_count' => $categoryTransactions->count(),
            ];
        })->values();

        return response()->json([
            'by_date' => $byDate,
            'by_category' => $byCategory,
            'summary' => [
                'total_receipts' => $transactions->where('type', 'receipt')->sum('quantity'),
                'total_shipments' => abs($transactions->where('type', 'shipment')->sum('quantity')),
                'total_adjustments' => $transactions->where('type', 'adjustment')->count(),
                'period_days' => $days,
            ],
        ]);
    }

    /**
     * Export report to CSV
     */
    public function exportReport(Request $request)
    {
        $reportType = $request->get('type', 'low_stock');

        switch ($reportType) {
            case 'low_stock':
                return $this->exportLowStock();
            case 'committed':
                return $this->exportCommitted();
            case 'velocity':
                return $this->exportVelocity($request);
            case 'reorder':
                return $this->exportReorder();
            case 'obsolete':
                return $this->exportObsolete($request);
            default:
                return response()->json(['message' => 'Invalid report type'], 400);
        }
    }

    // Helper methods
    private function enrichProductData($product, $includeRecommendations = false)
    {
        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'description' => $product->description,
            'category' => $product->category?->name,
            'supplier' => $product->supplier?->name,
            'on_hand' => $product->quantity_on_hand,
            'committed' => $product->quantity_committed,
            'available' => $product->quantity_available,
            'minimum' => $product->minimum_quantity,
            'reorder_point' => $product->reorder_point,
            'unit_cost' => $product->unit_cost,
            'total_value' => $product->quantity_on_hand * $product->unit_cost,
            'status' => $product->status,
            'lead_time_days' => $product->lead_time_days,
        ];

        if ($includeRecommendations) {
            $shortage = max(0, $product->reorder_point - $product->quantity_available);
            $recommendedQty = $shortage + ($product->safety_stock ?? 0);

            $data['shortage'] = $shortage;
            $data['recommended_order_qty'] = $recommendedQty;
            $data['recommended_order_value'] = $recommendedQty * $product->unit_cost;
            $data['days_until_stockout'] = $product->days_until_stockout;

            // Priority score (higher = more urgent)
            $priorityScore = 0;
            if ($product->status === 'critical') $priorityScore += 100;
            elseif ($product->status === 'low_stock') $priorityScore += 50;

            if ($product->days_until_stockout && $product->days_until_stockout < 7) {
                $priorityScore += 50;
            }

            $data['priority_score'] = $priorityScore;
        }

        return $data;
    }

    private function exportLowStock()
    {
        $data = $this->lowStockReport(request());
        $items = collect($data->original['low_stock'])->concat($data->original['critical']);

        return $this->generateCSV($items, 'low_stock_report', [
            'SKU', 'Description', 'Category', 'Supplier', 'On Hand', 'Available',
            'Minimum', 'Status', 'Value'
        ]);
    }

    private function generateCSV($data, $filename, $headers)
    {
        $filename = $filename . '_' . date('Y-m-d_His') . '.csv';

        $callback = function() use ($data, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($data as $row) {
                fputcsv($file, array_values((array)$row));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // Additional export methods would go here...
    private function exportCommitted() { /* Similar to exportLowStock */ }
    private function exportVelocity($request) { /* Similar to exportLowStock */ }
    private function exportReorder() { /* Similar to exportLowStock */ }
    private function exportObsolete($request) { /* Similar to exportLowStock */ }
}
