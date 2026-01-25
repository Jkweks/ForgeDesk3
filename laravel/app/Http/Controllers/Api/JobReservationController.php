<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobReservation;
use App\Models\JobReservationItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class JobReservationController extends Controller
{
    /**
     * List all job reservations with summary data
     */
    public function index()
    {
        try {
            $reservations = JobReservation::with('items')
                ->orderByRaw("CASE WHEN status IN ('fulfilled', 'cancelled') THEN 1 ELSE 0 END")
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($reservation) {
                    return [
                        'id' => $reservation->id,
                        'job_number' => $reservation->job_number,
                        'release_number' => $reservation->release_number,
                        'job_name' => $reservation->job_name,
                        'requested_by' => $reservation->requested_by,
                        'needed_by' => $reservation->needed_by?->format('Y-m-d'),
                        'status' => $reservation->status,
                        'status_label' => $reservation->status_label,
                        'notes' => $reservation->notes,
                        'created_at' => $reservation->created_at->format('Y-m-d H:i:s'),
                        'items_count' => $reservation->items->count(),
                        'total_requested' => $reservation->items->sum('requested_qty'),
                        'total_committed' => $reservation->items->sum('committed_qty'),
                        'total_consumed' => $reservation->items->sum('consumed_qty'),
                    ];
                });

            return response()->json([
                'reservations' => $reservations,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list reservations', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Failed to list reservations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed reservation with line items
     */
    public function show($id)
    {
        try {
            $reservation = JobReservation::with(['items.product'])->findOrFail($id);

            $items = $reservation->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'requested_qty' => $item->requested_qty,
                    'committed_qty' => $item->committed_qty,
                    'consumed_qty' => $item->consumed_qty,
                    'released_qty' => $item->released_qty,
                    'shortfall' => $item->shortfall,
                    'product' => [
                        'id' => $item->product->id,
                        'sku' => $item->product->sku,
                        'part_number' => $item->product->part_number,
                        'finish' => $item->product->finish,
                        'description' => $item->product->description,
                        'location' => $item->product->location,
                        'quantity_on_hand' => $item->product->quantity_on_hand,
                        'quantity_available' => $item->product->quantity_available,
                    ],
                ];
            });

            return response()->json([
                'reservation' => [
                    'id' => $reservation->id,
                    'job_number' => $reservation->job_number,
                    'release_number' => $reservation->release_number,
                    'job_name' => $reservation->job_name,
                    'requested_by' => $reservation->requested_by,
                    'needed_by' => $reservation->needed_by?->format('Y-m-d'),
                    'status' => $reservation->status,
                    'status_label' => $reservation->status_label,
                    'notes' => $reservation->notes,
                    'created_at' => $reservation->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $reservation->updated_at->format('Y-m-d H:i:s'),
                ],
                'items' => $items,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch reservation', [
                'reservation_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch reservation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update reservation status with validation
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:draft,active,in_progress,fulfilled,on_hold,cancelled',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            try {
                $reservation = JobReservation::with('items.product')->findOrFail($id);
                $previousStatus = $reservation->status;
                $targetStatus = $request->status;

                // Prevent changes to terminal states
                if (in_array($previousStatus, ['fulfilled', 'cancelled'])) {
                    return response()->json([
                        'error' => 'Cannot modify reservation',
                        'message' => "Reservation is {$previousStatus} and cannot be changed",
                    ], 422);
                }

                $warnings = [];
                $insufficientItems = [];

                // Validate transition to in_progress
                if ($targetStatus === 'in_progress') {
                    foreach ($reservation->items as $item) {
                        if ($item->product->quantity_on_hand < $item->committed_qty) {
                            $insufficientItems[] = [
                                'product_id' => $item->product_id,
                                'sku' => $item->product->sku,
                                'part_number' => $item->product->part_number,
                                'finish' => $item->product->finish,
                                'committed_qty' => $item->committed_qty,
                                'on_hand' => $item->product->quantity_on_hand,
                                'shortage' => $item->committed_qty - $item->product->quantity_on_hand,
                                'location' => $item->product->location,
                            ];
                        }
                    }

                    if (!empty($insufficientItems)) {
                        $warnings[] = 'Some items have insufficient stock to fulfill commitment';
                    }
                }

                // Update status
                $reservation->status = $targetStatus;
                $reservation->save();

                DB::commit();

                Log::info('Reservation status updated', [
                    'reservation_id' => $id,
                    'previous_status' => $previousStatus,
                    'new_status' => $targetStatus,
                ]);

                return response()->json([
                    'message' => 'Status updated successfully',
                    'reservation' => [
                        'id' => $reservation->id,
                        'job_number' => $reservation->job_number,
                        'release_number' => $reservation->release_number,
                        'previous_status' => $previousStatus,
                        'new_status' => $targetStatus,
                        'status_label' => $reservation->status_label,
                    ],
                    'warnings' => $warnings,
                    'insufficient_items' => $insufficientItems,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to update reservation status', [
                'reservation_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete a job reservation with actual consumption quantities
     */
    public function complete(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'consumed_quantities' => 'required|array',
                'consumed_quantities.*' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            try {
                $reservation = JobReservation::with('items.product')->findOrFail($id);

                // Validate status
                if ($reservation->status !== 'in_progress') {
                    return response()->json([
                        'error' => 'Invalid status',
                        'message' => 'Reservation must be in_progress to complete',
                    ], 422);
                }

                $consumedQuantities = $request->consumed_quantities;
                $totalConsumed = 0;
                $totalReleased = 0;
                $itemsData = [];

                // Validate and process each item
                foreach ($reservation->items as $item) {
                    $productId = $item->product_id;

                    if (!isset($consumedQuantities[$productId])) {
                        return response()->json([
                            'error' => 'Missing consumption data',
                            'message' => "Consumed quantity required for product ID {$productId}",
                        ], 422);
                    }

                    $actualQty = $consumedQuantities[$productId];

                    // Validate consumed quantity
                    if ($actualQty < $item->consumed_qty) {
                        return response()->json([
                            'error' => 'Invalid consumption',
                            'message' => "Cannot reduce consumed quantity below already consumed ({$item->consumed_qty})",
                        ], 422);
                    }

                    if ($actualQty > $item->committed_qty) {
                        return response()->json([
                            'error' => 'Invalid consumption',
                            'message' => "Cannot consume more than committed quantity ({$item->committed_qty})",
                        ], 422);
                    }

                    // Calculate deltas
                    $consumedDelta = $actualQty - $item->consumed_qty;
                    $releasedQty = $item->committed_qty - $actualQty;

                    $totalConsumed += $consumedDelta;
                    $totalReleased += $releasedQty;

                    // Update reservation item
                    $item->consumed_qty = $actualQty;
                    $item->save();

                    // Update product stock
                    if ($consumedDelta > 0) {
                        $product = $item->product;
                        $stockBefore = $product->quantity_on_hand;
                        $product->quantity_on_hand -= $consumedDelta;
                        $product->save();

                        Log::info('Stock updated for product', [
                            'product_id' => $productId,
                            'consumed_delta' => $consumedDelta,
                            'stock_before' => $stockBefore,
                            'stock_after' => $product->quantity_on_hand,
                        ]);
                    }

                    $itemsData[] = [
                        'product_id' => $productId,
                        'sku' => $item->product->sku,
                        'part_number' => $item->product->part_number,
                        'finish' => $item->product->finish,
                        'consumed' => $actualQty,
                        'consumed_delta' => $consumedDelta,
                        'released' => $releasedQty,
                    ];
                }

                // Update reservation status to fulfilled
                $reservation->status = 'fulfilled';
                $reservation->save();

                DB::commit();

                Log::info('Job reservation completed', [
                    'reservation_id' => $id,
                    'job_number' => $reservation->job_number,
                    'release_number' => $reservation->release_number,
                    'total_consumed' => $totalConsumed,
                    'total_released' => $totalReleased,
                ]);

                return response()->json([
                    'message' => 'Job completed successfully',
                    'reservation' => [
                        'id' => $reservation->id,
                        'job_number' => $reservation->job_number,
                        'release_number' => $reservation->release_number,
                        'status' => $reservation->status,
                        'total_consumed' => $totalConsumed,
                        'total_released' => $totalReleased,
                    ],
                    'items' => $itemsData,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to complete reservation', [
                'reservation_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Failed to complete reservation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get status labels
     */
    public function statusLabels()
    {
        return response()->json([
            'labels' => JobReservation::statusLabels(),
        ]);
    }
}
