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
     * Uses the fulfillment system (job_reservation_items) to calculate committed quantities
     */
    public function committedPartsReport(Request $request)
    {
        // Get products that have active reservation items
        // Active statuses per fulfillment process: 'active', 'in_progress', 'on_hold'
        $committedProducts = Product::where('is_active', true)
            ->whereHas('reservationItems', function($query) {
                $query->whereHas('reservation', function($resQuery) {
                    $resQuery->whereIn('status', ['active', 'in_progress', 'on_hold']);
                });
            })
            ->with(['category', 'supplier', 'reservationItems' => function($query) {
                $query->whereHas('reservation', function($resQuery) {
                    $resQuery->whereIn('status', ['active', 'in_progress', 'on_hold']);
                })->with('reservation');
            }])
            ->get()
            ->map(function ($product) {
                $data = $this->enrichProductData($product);

                // Calculate committed quantity from active reservation items (in eaches)
                $committedQty = $product->reservationItems->sum('committed_qty');
                $data['committed'] = $committedQty;
                $data['committed_packs'] = $product->eachesToPacksNeeded($committedQty);
                $data['committed_display'] = $product->hasPackSize() ? $data['committed_packs'] : $committedQty;
                $data['available'] = $product->quantity_on_hand - $committedQty;
                $data['available_packs'] = $product->hasPackSize() ? $product->eachesToPacksNeeded($data['available']) : $data['available'];
                $data['available_display'] = $product->hasPackSize() ? $data['available_packs'] : $data['available'];
                $data['pack_size'] = $product->pack_size;
                $data['has_pack_size'] = $product->hasPackSize();
                $data['counting_unit'] = $product->counting_unit;
                $data['on_hand_packs'] = $product->quantity_on_hand_packs;

                // Map reservation items to reservation details with pack calculations
                $data['reservations'] = $product->reservationItems->map(function ($item) use ($product) {
                    return [
                        'id' => $item->reservation->id,
                        'job_number' => $item->reservation->job_number,
                        'release_number' => $item->reservation->release_number,
                        'job_name' => $item->reservation->job_name,
                        'quantity' => $item->committed_qty,
                        'quantity_packs' => $product->eachesToPacksNeeded($item->committed_qty),
                        'requested_qty' => $item->requested_qty,
                        'consumed_qty' => $item->consumed_qty,
                        'status' => $item->reservation->status,
                        'needed_by' => $item->reservation->needed_by,
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
                    return $p['committed_display'] * $p['display_cost'];
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

            // Count all inventory removals as shipments (negative quantity transactions)
            // This includes: shipment, issue, job_issue, and negative adjustments
            $shipments = abs($transactions->filter(function($t) {
                return $t->quantity < 0;
            })->sum('quantity'));

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

            // Use pack-based quantities if applicable
            $onHandDisplay = $product->hasPackSize() ? $product->quantity_on_hand_packs : $product->quantity_on_hand;
            $availableDisplay = $product->hasPackSize() ? $product->quantity_available_packs : $product->quantity_available;

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'description' => $product->description,
                'category' => $product->category?->name,
                'on_hand' => $product->quantity_on_hand,
                'on_hand_display' => $onHandDisplay,
                'available' => $product->quantity_available,
                'available_display' => $availableDisplay,
                'receipts' => $receipts,
                'shipments' => $shipments,
                'turnover_rate' => $turnoverRate,
                'velocity' => $velocity,
                'average_daily_use' => $product->average_daily_use,
                'days_until_stockout' => $daysUntilStockout,
                'pack_size' => $product->pack_size,
                'counting_unit' => $product->counting_unit,
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

            // Use pack-based quantities and pricing if applicable
            $displayQuantity = $product->hasPackSize() ? $product->quantity_on_hand_packs : $product->quantity_on_hand;
            $displayCost = $product->hasPackSize() ? $product->pack_cost : $product->unit_cost;

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'description' => $product->description,
                'category' => $product->category?->name,
                'on_hand' => $product->quantity_on_hand,
                'on_hand_display' => $displayQuantity,
                'unit_cost' => $product->unit_cost,
                'display_cost' => $displayCost,
                'total_value' => $displayQuantity * $displayCost,
                'last_shipment_date' => $lastShipment?->transaction_date,
                'days_since_last_use' => $daysSinceLastUse,
                'is_used_in_bom' => $product->usedInProducts()->count() > 0,
                'pack_size' => $product->pack_size,
                'counting_unit' => $product->counting_unit,
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
        // Use pack-based pricing and quantities when available
        $displayQuantity = $product->hasPackSize() ? $product->quantity_on_hand_packs : $product->quantity_on_hand;
        $displayCost = $product->hasPackSize() ? $product->pack_cost : $product->unit_cost;
        $displayPrice = $product->hasPackSize() ? $product->pack_price : $product->unit_price;

        // Convert reorder_point and minimum to packs if applicable
        $reorderPointDisplay = $product->hasPackSize() ? $product->eachesToPacksNeeded($product->reorder_point) : $product->reorder_point;
        $minimumDisplay = $product->hasPackSize() ? $product->eachesToPacksNeeded($product->minimum_quantity) : $product->minimum_quantity;

        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'description' => $product->description,
            'category' => $product->category?->name,
            'supplier' => $product->supplier?->name,
            'on_hand' => $product->quantity_on_hand,
            'on_hand_packs' => $product->quantity_on_hand_packs,
            'on_hand_display' => $displayQuantity,
            'committed' => $product->quantity_committed,
            'committed_packs' => $product->quantity_committed_packs,
            'committed_display' => $product->hasPackSize() ? $product->quantity_committed_packs : $product->quantity_committed,
            'available' => $product->quantity_available,
            'available_packs' => $product->quantity_available_packs,
            'available_display' => $product->hasPackSize() ? $product->quantity_available_packs : $product->quantity_available,
            'minimum' => $product->minimum_quantity,
            'minimum_display' => $minimumDisplay,
            'reorder_point' => $product->reorder_point,
            'reorder_point_display' => $reorderPointDisplay,
            'unit_cost' => $product->unit_cost,
            'unit_price' => $product->unit_price,
            'pack_cost' => $product->pack_cost,
            'pack_price' => $product->pack_price,
            'display_cost' => $displayCost,
            'display_price' => $displayPrice,
            'total_value' => $displayQuantity * $displayCost,
            'status' => $product->status,
            'lead_time_days' => $product->lead_time_days,
            'pack_size' => $product->pack_size,
            'has_pack_size' => $product->hasPackSize(),
            'counting_unit' => $product->counting_unit,
        ];

        if ($includeRecommendations) {
            $shortage = max(0, $product->reorder_point - $product->quantity_available);
            $recommendedQty = $shortage + ($product->safety_stock ?? 0);
            $target = $product->reorder_point + $shortage;

            // Convert shortage, recommended qty, and target to packs if applicable
            $shortageDisplay = $product->hasPackSize() ? $product->eachesToPacksNeeded($shortage) : $shortage;
            $recommendedQtyDisplay = $product->hasPackSize() ? $product->eachesToPacksNeeded($recommendedQty) : $recommendedQty;
            $targetDisplay = $product->hasPackSize() ? $product->eachesToPacksNeeded($target) : $target;

            $data['shortage'] = $shortage;
            $data['shortage_display'] = $shortageDisplay;
            $data['target'] = $target;
            $data['target_display'] = $targetDisplay;
            $data['recommended_order_qty'] = $recommendedQty;
            $data['recommended_order_qty_display'] = $recommendedQtyDisplay;
            $data['recommended_order_value'] = $recommendedQtyDisplay * $displayCost;
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

        // Map to proper CSV format with pack-based quantities
        $csvData = $items->map(function($item) {
            return [
                $item['sku'],
                $item['description'],
                $item['category'] ?? '',
                $item['supplier'] ?? '',
                $item['on_hand_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['available_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['minimum'] ?? '',
                $item['status'],
                number_format($item['total_value'], 2),
            ];
        });

        return $this->generateCSV($csvData, 'low_stock_report', [
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

    private function exportCommitted()
    {
        $data = $this->committedPartsReport(request());
        $items = collect($data->original['committed_products']);

        // Map to proper CSV format with pack-based quantities
        $csvData = $items->map(function($item) {
            return [
                $item['sku'],
                $item['description'],
                $item['category'] ?? '',
                $item['supplier'] ?? '',
                $item['on_hand_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['committed_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['available_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                number_format($item['display_cost'], 2),
                number_format($item['total_value'], 2),
            ];
        });

        return $this->generateCSV($csvData, 'committed_parts_report', [
            'SKU', 'Description', 'Category', 'Supplier', 'On Hand', 'Committed',
            'Available', 'Unit Cost', 'Total Value'
        ]);
    }

    private function exportVelocity($request)
    {
        $data = $this->stockVelocityAnalysis($request);
        $items = collect($data->original['products']);

        // Map to proper CSV format with pack-based quantities
        $csvData = $items->map(function($item) {
            return [
                $item['sku'],
                $item['description'],
                $item['category'] ?? '',
                $item['on_hand_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['available_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['receipts'],
                $item['shipments'],
                $item['turnover_rate'] . '%',
                ucfirst($item['velocity']),
                $item['days_until_stockout'] ?? 'N/A',
            ];
        });

        return $this->generateCSV($csvData, 'velocity_analysis_report', [
            'SKU', 'Description', 'Category', 'On Hand', 'Available', 'Receipts',
            'Shipments', 'Turnover Rate', 'Velocity', 'Days Until Stockout'
        ]);
    }

    private function exportReorder()
    {
        $data = $this->reorderRecommendations(request());
        $items = collect($data->original['recommendations']);

        // Map to proper CSV format with pack-based quantities
        $csvData = $items->map(function($item) {
            return [
                $item['sku'],
                $item['description'],
                $item['category'] ?? '',
                $item['supplier'] ?? '',
                $item['available_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['reorder_point_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['shortage_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                $item['recommended_order_qty_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                number_format($item['recommended_order_value'], 2),
                ucfirst($item['status']),
                $item['lead_time_days'] ?? '',
            ];
        });

        return $this->generateCSV($csvData, 'reorder_recommendations_report', [
            'SKU', 'Description', 'Category', 'Supplier', 'Available', 'Reorder Point',
            'Shortage', 'Recommended Qty', 'Recommended Value', 'Status', 'Lead Time Days'
        ]);
    }

    private function exportObsolete($request)
    {
        $data = $this->obsoleteInventory($request);
        $items = collect($data->original['obsolete_candidates']);

        // Map to proper CSV format with pack-based quantities
        $csvData = $items->map(function($item) {
            return [
                $item['sku'],
                $item['description'],
                $item['category'] ?? '',
                $item['on_hand_display'] . ' ' . ($item['counting_unit'] ?? 'ea'),
                number_format($item['display_cost'], 2),
                number_format($item['total_value'], 2),
                $item['last_shipment_date'] ?? 'Never',
                $item['days_since_last_use'] ?? 'N/A',
                $item['is_used_in_bom'] ? 'Yes' : 'No',
            ];
        });

        return $this->generateCSV($csvData, 'obsolete_inventory_report', [
            'SKU', 'Description', 'Category', 'On Hand', 'Unit Cost', 'Total Value',
            'Last Shipment Date', 'Days Since Last Use', 'Used in BOM'
        ]);
    }

    /**
     * Generate PDF for Low Stock report
     */
    public function lowStockPdf(Request $request)
    {
        $data = $this->lowStockReport($request);
        $reportData = $data->original;

        $products = collect($reportData['low_stock'])->merge($reportData['critical']);
        $summary = [
            'critical_stock' => $reportData['summary']['critical_count'],
            'low_stock' => $reportData['summary']['low_stock_count'],
            'total_items' => $reportData['summary']['total_affected'],
            'total_value' => $reportData['summary']['estimated_value_at_risk'],
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.low-stock-report', [
            'products' => $products,
            'summary' => $summary
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('low-stock-report-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate PDF for Committed Parts report
     */
    public function committedPartsPdf(Request $request)
    {
        $data = $this->committedPartsReport($request);
        $reportData = $data->original;

        // Get unique active job count
        $activeJobs = collect($reportData['committed_products'])
            ->flatMap(function($product) {
                return $product['reservations'] ?? [];
            })
            ->pluck('id')
            ->unique()
            ->count();

        $summary = [
            'unique_parts' => $reportData['summary']['total_products'],
            'total_committed_quantity' => $reportData['summary']['total_quantity_committed'],
            'total_committed_value' => $reportData['summary']['total_value_committed'],
            'active_jobs' => $activeJobs,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.committed-parts-report', [
            'products' => $reportData['committed_products'],
            'summary' => $summary
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('committed-parts-report-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate PDF for Velocity Analysis report
     */
    public function velocityAnalysisPdf(Request $request)
    {
        $data = $this->stockVelocityAnalysis($request);
        $reportData = $data->original;
        $days = $request->get('days', 90);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.velocity-analysis-report', [
            'products' => $reportData['products'],
            'summary' => $reportData['summary'],
            'days' => $days
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('velocity-analysis-report-' . $days . 'd-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate PDF for Reorder Recommendations report
     */
    public function reorderRecommendationsPdf(Request $request)
    {
        $data = $this->reorderRecommendations($request);
        $reportData = $data->original;

        $recommendations = collect($reportData['recommendations']);

        // Calculate priority counts for summary
        $highPriority = $recommendations->filter(function($rec) {
            return $rec['priority_score'] >= 100;
        })->count();

        $mediumPriority = $recommendations->filter(function($rec) {
            return $rec['priority_score'] >= 50 && $rec['priority_score'] < 100;
        })->count();

        $summary = [
            'total_items' => $reportData['summary']['items_to_reorder'],
            'high_priority' => $highPriority,
            'medium_priority' => $mediumPriority,
            'total_estimated_value' => $reportData['summary']['total_order_value'],
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.reorder-recommendations-report', [
            'recommendations' => $recommendations,
            'summary' => $summary
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('reorder-recommendations-report-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate PDF for Obsolete Inventory report
     */
    public function obsoleteInventoryPdf(Request $request)
    {
        $data = $this->obsoleteInventory($request);
        $reportData = $data->original;
        $inactive_days = $request->get('inactive_days', 90);

        $candidates = collect($reportData['obsolete_candidates'])->map(function($candidate) {
            return array_merge($candidate, [
                'last_activity_date' => $candidate['last_shipment_date'],
                'days_since_activity' => $candidate['days_since_last_use'],
            ]);
        });

        $totalQuantity = $candidates->sum('on_hand');
        $avgDaysInactive = $candidates->count() > 0
            ? round($candidates->avg('days_since_last_use'))
            : 0;

        $summary = [
            'obsolete_count' => $reportData['summary']['total_items'],
            'total_quantity' => $totalQuantity,
            'total_value' => $reportData['summary']['total_value_at_risk'],
            'average_days_inactive' => $avgDaysInactive,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.obsolete-inventory-report', [
            'candidates' => $candidates,
            'summary' => $summary,
            'inactive_days' => $inactive_days
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('obsolete-inventory-report-' . $inactive_days . 'd-' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate PDF for Usage Analytics report
     */
    public function usageAnalyticsPdf(Request $request)
    {
        $data = $this->usageAnalytics($request);
        $reportData = $data->original;
        $days = $request->get('days', 30);
        $since = Carbon::now()->subDays($days);

        // Get all transactions for more detailed breakdown
        $allTransactions = InventoryTransaction::where('transaction_date', '>=', $since)
            ->with('product.category')
            ->get();

        // Transform by_date to include issues (negative quantity transactions)
        $byDate = collect($reportData['by_date'])->mapWithKeys(function($dayData) use ($allTransactions) {
            $date = $dayData['date'];
            $dayTrans = $allTransactions->filter(function($t) use ($date) {
                return Carbon::parse($t->transaction_date)->format('Y-m-d') === $date;
            });

            return [$date => [
                'total' => $dayTrans->count(),
                'receipts' => $dayTrans->where('type', 'receipt')->sum('quantity'),
                'shipments' => abs($dayTrans->where('type', 'shipment')->sum('quantity')),
                'issues' => abs($dayTrans->whereIn('type', ['issue', 'job_issue'])->sum('quantity')),
                'adjustments' => $dayTrans->where('type', 'adjustment')->count(),
            ]];
        });

        // Transform by_category to include product_count and percentage
        $totalTransactions = $allTransactions->count();
        $byCategory = $allTransactions->groupBy(function($t) {
            return $t->product->category?->name ?? 'Uncategorized';
        })->map(function($catTrans, $category) use ($totalTransactions) {
            return [
                'transaction_count' => $catTrans->count(),
                'product_count' => $catTrans->pluck('product_id')->unique()->count(),
                'percentage' => $totalTransactions > 0
                    ? round(($catTrans->count() / $totalTransactions) * 100, 1)
                    : 0,
            ];
        });

        // Build summary
        $uniqueProducts = $allTransactions->pluck('product_id')->unique()->count();
        $activeCategories = $allTransactions->pluck('product.category.id')->filter()->unique()->count();

        $summary = [
            'total_transactions' => $totalTransactions,
            'unique_products' => $uniqueProducts,
            'daily_average' => $days > 0 ? round($totalTransactions / $days, 1) : 0,
            'active_categories' => $activeCategories,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.usage-analytics-report', [
            'by_date' => $byDate,
            'by_category' => $byCategory,
            'summary' => $summary,
            'days' => $days
        ]);

        $pdf->setPaper('letter', 'landscape');
        return $pdf->stream('usage-analytics-report-' . $days . 'd-' . date('Y-m-d') . '.pdf');
    }
}
