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
     * If $product is provided, returns reservations for that specific product (old API compatibility)
     */
    public function index($product = null)
    {
        try {
            // Old API: product-specific reservations
            if ($product !== null) {
                // Get reservations that include this product as a line item
                $reservations = JobReservation::whereHas('items', function ($query) use ($product) {
                    $query->where('product_id', $product);
                })
                ->with(['items' => function ($query) use ($product) {
                    $query->where('product_id', $product);
                }])
                ->orderByRaw("CASE WHEN status IN ('fulfilled', 'cancelled') THEN 1 ELSE 0 END")
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($reservation) {
                    $item = $reservation->items->first(); // Get this product's line item

                    return [
                        'id' => $reservation->id,
                        'job_number' => $reservation->job_number,
                        'job_name' => $reservation->job_name,
                        'status' => $reservation->status,
                        'quantity_reserved' => $item->committed_qty,
                        'quantity_fulfilled' => $item->consumed_qty,
                        'reserved_date' => $reservation->created_at->format('Y-m-d'),
                        'needed_date' => $reservation->needed_by?->format('Y-m-d'),
                        'notes' => $reservation->notes,
                        'created_at' => $reservation->created_at->toISOString(),
                        'updated_at' => $reservation->updated_at->toISOString(),
                    ];
                });

                return response()->json($reservations);
            }

            // New API: all job reservations
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
     * Update reservation header (job_name, requested_by, needed_by, notes)
     */
    public function updateReservation(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'job_name' => 'sometimes|nullable|string|max:255',
                'requested_by' => 'sometimes|nullable|string|max:255',
                'needed_by' => 'sometimes|nullable|date',
                'notes' => 'sometimes|nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            try {
                $reservation = JobReservation::findOrFail($id);

                // Prevent editing terminal states
                if (in_array($reservation->status, ['fulfilled', 'cancelled'])) {
                    return response()->json([
                        'error' => 'Cannot edit reservation',
                        'message' => "Reservation is {$reservation->status} and cannot be modified",
                    ], 422);
                }

                // Update allowed fields
                if ($request->has('job_name')) {
                    $reservation->job_name = $request->job_name;
                }
                if ($request->has('requested_by')) {
                    $reservation->requested_by = $request->requested_by;
                }
                if ($request->has('needed_by')) {
                    $reservation->needed_by = $request->needed_by;
                }
                if ($request->has('notes')) {
                    $reservation->notes = $request->notes;
                }

                $reservation->save();

                DB::commit();

                Log::info('Reservation updated', [
                    'reservation_id' => $id,
                    'job_number' => $reservation->job_number,
                    'updates' => $request->only(['job_name', 'requested_by', 'needed_by', 'notes']),
                ]);

                return response()->json([
                    'message' => 'Reservation updated successfully',
                    'reservation' => [
                        'id' => $reservation->id,
                        'job_number' => $reservation->job_number,
                        'release_number' => $reservation->release_number,
                        'job_name' => $reservation->job_name,
                        'requested_by' => $reservation->requested_by,
                        'needed_by' => $reservation->needed_by?->format('Y-m-d'),
                        'notes' => $reservation->notes,
                        'status' => $reservation->status,
                    ],
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to update reservation', [
                'reservation_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update reservation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a new line item to an existing reservation
     */
    public function addItem(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|exists:products,id',
                'requested_qty' => 'required|integer|min:1',
                'committed_qty' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            try {
                $reservation = JobReservation::findOrFail($id);

                // Prevent editing terminal states
                if (in_array($reservation->status, ['fulfilled', 'cancelled'])) {
                    return response()->json([
                        'error' => 'Cannot modify reservation',
                        'message' => "Reservation is {$reservation->status} and cannot be modified",
                    ], 422);
                }

                $product = Product::findOrFail($request->product_id);

                // Check if product already exists in this reservation
                $existingItem = JobReservationItem::where('reservation_id', $id)
                    ->where('product_id', $request->product_id)
                    ->first();

                if ($existingItem) {
                    return response()->json([
                        'error' => 'Product already exists',
                        'message' => 'This product is already in the reservation. Use update to modify quantities.',
                    ], 422);
                }

                // Validate committed quantity doesn't exceed available
                if ($request->committed_qty > $product->quantity_available) {
                    return response()->json([
                        'error' => 'Insufficient inventory',
                        'message' => "Only {$product->quantity_available} available. Cannot commit {$request->committed_qty}.",
                    ], 422);
                }

                // Create new item
                $item = JobReservationItem::create([
                    'reservation_id' => $id,
                    'product_id' => $request->product_id,
                    'requested_qty' => $request->requested_qty,
                    'committed_qty' => $request->committed_qty,
                    'consumed_qty' => 0,
                ]);

                DB::commit();

                Log::info('Line item added to reservation', [
                    'reservation_id' => $id,
                    'product_id' => $request->product_id,
                    'requested_qty' => $request->requested_qty,
                    'committed_qty' => $request->committed_qty,
                ]);

                return response()->json([
                    'message' => 'Item added successfully',
                    'item' => [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'requested_qty' => $item->requested_qty,
                        'committed_qty' => $item->committed_qty,
                        'consumed_qty' => $item->consumed_qty,
                        'product' => [
                            'id' => $product->id,
                            'sku' => $product->sku,
                            'part_number' => $product->part_number,
                            'finish' => $product->finish,
                            'description' => $product->description,
                            'quantity_on_hand' => $product->quantity_on_hand,
                            'quantity_available' => $product->quantity_available,
                        ],
                    ],
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to add item to reservation', [
                'reservation_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to add item',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a line item's quantities
     */
    public function updateItem(Request $request, $id, $itemId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'requested_qty' => 'sometimes|integer|min:1',
                'committed_qty' => 'sometimes|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            try {
                $reservation = JobReservation::findOrFail($id);

                // Prevent editing terminal states
                if (in_array($reservation->status, ['fulfilled', 'cancelled'])) {
                    return response()->json([
                        'error' => 'Cannot modify reservation',
                        'message' => "Reservation is {$reservation->status} and cannot be modified",
                    ], 422);
                }

                $item = JobReservationItem::where('id', $itemId)
                    ->where('reservation_id', $id)
                    ->with('product')
                    ->firstOrFail();

                // Cannot reduce committed below consumed
                if ($request->has('committed_qty') && $request->committed_qty < $item->consumed_qty) {
                    return response()->json([
                        'error' => 'Invalid quantity',
                        'message' => "Cannot reduce committed quantity below already consumed ({$item->consumed_qty})",
                    ], 422);
                }

                // Check available inventory if increasing committed_qty
                if ($request->has('committed_qty') && $request->committed_qty > $item->committed_qty) {
                    $increase = $request->committed_qty - $item->committed_qty;
                    if ($increase > $item->product->quantity_available) {
                        return response()->json([
                            'error' => 'Insufficient inventory',
                            'message' => "Only {$item->product->quantity_available} available. Cannot increase by {$increase}.",
                        ], 422);
                    }
                }

                // Update quantities
                if ($request->has('requested_qty')) {
                    $item->requested_qty = $request->requested_qty;
                }
                if ($request->has('committed_qty')) {
                    $item->committed_qty = $request->committed_qty;
                }

                $item->save();

                DB::commit();

                Log::info('Line item updated', [
                    'reservation_id' => $id,
                    'item_id' => $itemId,
                    'updates' => $request->only(['requested_qty', 'committed_qty']),
                ]);

                return response()->json([
                    'message' => 'Item updated successfully',
                    'item' => [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'requested_qty' => $item->requested_qty,
                        'committed_qty' => $item->committed_qty,
                        'consumed_qty' => $item->consumed_qty,
                    ],
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to update item', [
                'reservation_id' => $id,
                'item_id' => $itemId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update item',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a line item from a reservation
     */
    public function removeItem($id, $itemId)
    {
        try {
            DB::beginTransaction();

            try {
                $reservation = JobReservation::findOrFail($id);

                // Prevent editing terminal states
                if (in_array($reservation->status, ['fulfilled', 'cancelled'])) {
                    return response()->json([
                        'error' => 'Cannot modify reservation',
                        'message' => "Reservation is {$reservation->status} and cannot be modified",
                    ], 422);
                }

                $item = JobReservationItem::where('id', $itemId)
                    ->where('reservation_id', $id)
                    ->firstOrFail();

                // Cannot remove if already consumed
                if ($item->consumed_qty > 0) {
                    return response()->json([
                        'error' => 'Cannot remove item',
                        'message' => 'Cannot remove item that has already been consumed',
                    ], 422);
                }

                $productId = $item->product_id;
                $item->delete();

                DB::commit();

                Log::info('Line item removed from reservation', [
                    'reservation_id' => $id,
                    'item_id' => $itemId,
                    'product_id' => $productId,
                ]);

                return response()->json([
                    'message' => 'Item removed successfully',
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to remove item', [
                'reservation_id' => $id,
                'item_id' => $itemId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to remove item',
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

    /**
     * Get active reservations for a product (old API compatibility)
     */
    public function active($product)
    {
        // Return empty array for old product reservation system
        return response()->json([]);
    }

    /**
     * Get all jobs (old API compatibility)
     */
    public function getAllJobs()
    {
        // Return all job numbers from new reservation system
        try {
            $jobs = JobReservation::select('job_number', 'job_name')
                ->distinct()
                ->orderBy('job_number')
                ->get()
                ->map(function ($reservation) {
                    return [
                        'job_number' => $reservation->job_number,
                        'job_name' => $reservation->job_name,
                    ];
                });

            return response()->json($jobs);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    /**
     * Get reservation statistics for a product (old API compatibility)
     */
    public function statistics($product)
    {
        try {
            // Get product quantity_on_hand for ATP calculation
            $productModel = Product::find($product);
            if (!$productModel) {
                return response()->json([
                    'active_reservations_count' => 0,
                    'quantity_committed' => 0,
                    'atp' => 0,
                    'overdue_reservations' => 0,
                    'upcoming_reservations' => 0,
                ]);
            }

            // Calculate committed quantities from active reservations
            $activeReservations = JobReservationItem::where('product_id', $product)
                ->whereHas('reservation', function ($query) {
                    $query->whereIn('status', ['active', 'in_progress', 'on_hold']);
                })
                ->with('reservation')
                ->get();

            $quantityCommitted = $activeReservations->sum(function ($item) {
                return $item->committed_qty - $item->consumed_qty;
            });

            $activeCount = $activeReservations->unique('reservation_id')->count();

            // Count overdue (needed_by < today) and upcoming (needed_by in next 7 days)
            $today = now()->startOfDay();
            $nextWeek = now()->addDays(7)->endOfDay();

            $overdueCount = $activeReservations->filter(function ($item) use ($today) {
                return $item->reservation->needed_by &&
                       $item->reservation->needed_by < $today;
            })->unique('reservation_id')->count();

            $upcomingCount = $activeReservations->filter(function ($item) use ($today, $nextWeek) {
                return $item->reservation->needed_by &&
                       $item->reservation->needed_by >= $today &&
                       $item->reservation->needed_by <= $nextWeek;
            })->unique('reservation_id')->count();

            return response()->json([
                'active_reservations_count' => $activeCount,
                'quantity_committed' => $quantityCommitted,
                'atp' => $productModel->quantity_on_hand - $quantityCommitted,
                'overdue_reservations' => $overdueCount,
                'upcoming_reservations' => $upcomingCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get reservation statistics', [
                'product_id' => $product,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'active_reservations_count' => 0,
                'quantity_committed' => 0,
                'atp' => 0,
                'overdue_reservations' => 0,
                'upcoming_reservations' => 0,
            ]);
        }
    }

    /**
     * Store a new reservation for a product (old API compatibility)
     */
    public function store(Request $request, $product)
    {
        return response()->json([
            'error' => 'This feature has been replaced by the new Fulfillment Material Check process',
            'message' => 'Please use Fulfillment > Material Check to create job reservations',
        ], 410);
    }

    /**
     * Update a reservation (old API compatibility)
     */
    public function update(Request $request, $product, $reservation)
    {
        return response()->json([
            'error' => 'This feature has been replaced by the new Fulfillment process',
            'message' => 'Please use Fulfillment > Job Reservations to manage reservations',
        ], 410);
    }

    /**
     * Fulfill a reservation (old API compatibility)
     */
    public function fulfill(Request $request, $product, $reservation)
    {
        return response()->json([
            'error' => 'This feature has been replaced by the new Fulfillment process',
            'message' => 'Please use Fulfillment > Job Reservations to complete jobs',
        ], 410);
    }

    /**
     * Release a reservation (old API compatibility)
     */
    public function release(Request $request, $product, $reservation)
    {
        return response()->json([
            'error' => 'This feature has been replaced by the new Fulfillment process',
            'message' => 'Please use Fulfillment > Job Reservations to manage reservations',
        ], 410);
    }

    /**
     * Delete a reservation (old API compatibility)
     */
    public function destroy($product, $reservation)
    {
        return response()->json([
            'error' => 'This feature has been replaced by the new Fulfillment process',
            'message' => 'Please use Fulfillment > Job Reservations to manage reservations',
        ], 410);
    }
}
