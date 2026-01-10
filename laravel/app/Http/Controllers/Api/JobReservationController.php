<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobReservation;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobReservationController extends Controller
{
    /**
     * Get all reservations for a specific product
     */
    public function index(Product $product)
    {
        $reservations = $product->jobReservations()
            ->with(['reservedBy', 'releasedBy'])
            ->orderBy('status')
            ->orderBy('required_date', 'asc')
            ->orderBy('reserved_date', 'desc')
            ->get();

        return response()->json($reservations);
    }

    /**
     * Get all active reservations for a product
     */
    public function active(Product $product)
    {
        $reservations = $product->activeReservations()
            ->with(['reservedBy'])
            ->orderBy('required_date', 'asc')
            ->get();

        return response()->json($reservations);
    }

    /**
     * Create a new reservation
     */
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'job_number' => 'required|string|max:255',
            'job_name' => 'nullable|string|max:255',
            'quantity_reserved' => 'required|integer|min:1',
            'reserved_date' => 'required|date',
            'required_date' => 'nullable|date|after_or_equal:reserved_date',
            'notes' => 'nullable|string',
        ]);

        // Check if there's enough available inventory
        $availableQuantity = $product->quantity_on_hand - $product->quantity_committed;

        if ($validated['quantity_reserved'] > $availableQuantity) {
            return response()->json([
                'message' => 'Insufficient available inventory for reservation',
                'errors' => [
                    'quantity_reserved' => [
                        "Only {$availableQuantity} units available. Cannot reserve {$validated['quantity_reserved']} units."
                    ]
                ],
                'available' => $availableQuantity,
                'requested' => $validated['quantity_reserved']
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create reservation
            $reservation = $product->jobReservations()->create([
                'job_number' => $validated['job_number'],
                'job_name' => $validated['job_name'] ?? null,
                'quantity_reserved' => $validated['quantity_reserved'],
                'reserved_date' => $validated['reserved_date'],
                'required_date' => $validated['required_date'] ?? null,
                'status' => 'active',
                'quantity_fulfilled' => 0,
                'reserved_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            // Update product committed quantity
            $this->updateProductCommitted($product);

            DB::commit();

            return response()->json($reservation->load('reservedBy'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create reservation'], 500);
        }
    }

    /**
     * Update a reservation
     */
    public function update(Request $request, Product $product, JobReservation $reservation)
    {
        // Verify the reservation belongs to this product
        if ($reservation->product_id !== $product->id) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        $validated = $request->validate([
            'job_number' => 'required|string|max:255',
            'job_name' => 'nullable|string|max:255',
            'quantity_reserved' => 'required|integer|min:1',
            'required_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        // If changing quantity, check availability
        if ($validated['quantity_reserved'] != $reservation->quantity_reserved) {
            $difference = $validated['quantity_reserved'] - $reservation->quantity_reserved;
            $availableQuantity = $product->quantity_on_hand - $product->quantity_committed + $reservation->quantity_reserved;

            if ($validated['quantity_reserved'] > $availableQuantity) {
                return response()->json([
                    'message' => 'Insufficient available inventory',
                    'errors' => [
                        'quantity_reserved' => [
                            "Only {$availableQuantity} units available."
                        ]
                    ]
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $reservation->update([
                'job_number' => $validated['job_number'],
                'job_name' => $validated['job_name'] ?? $reservation->job_name,
                'quantity_reserved' => $validated['quantity_reserved'],
                'required_date' => $validated['required_date'] ?? $reservation->required_date,
                'notes' => $validated['notes'] ?? $reservation->notes,
            ]);

            // Update product committed quantity
            $this->updateProductCommitted($product);

            DB::commit();

            return response()->json($reservation->fresh()->load(['reservedBy', 'releasedBy']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update reservation'], 500);
        }
    }

    /**
     * Partially fulfill a reservation
     */
    public function fulfill(Request $request, Product $product, JobReservation $reservation)
    {
        // Verify the reservation belongs to this product
        if ($reservation->product_id !== $product->id) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        if ($reservation->status === 'fulfilled' || $reservation->status === 'cancelled') {
            return response()->json([
                'message' => 'Cannot fulfill a reservation that is already fulfilled or cancelled'
            ], 422);
        }

        $validated = $request->validate([
            'quantity_fulfilled' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $totalFulfilled = $reservation->quantity_fulfilled + $validated['quantity_fulfilled'];

        if ($totalFulfilled > $reservation->quantity_reserved) {
            return response()->json([
                'message' => 'Fulfill quantity exceeds reserved quantity',
                'errors' => [
                    'quantity_fulfilled' => [
                        "Cannot fulfill {$totalFulfilled} units when only {$reservation->quantity_reserved} were reserved."
                    ]
                ]
            ], 422);
        }

        DB::beginTransaction();
        try {
            $reservation->quantity_fulfilled = $totalFulfilled;

            // Update status
            if ($totalFulfilled >= $reservation->quantity_reserved) {
                $reservation->status = 'fulfilled';
                $reservation->released_date = now();
                $reservation->released_by = auth()->id();
            } else {
                $reservation->status = 'partially_fulfilled';
            }

            if ($validated['notes'] ?? false) {
                $reservation->notes = ($reservation->notes ? $reservation->notes . "\n" : '') .
                                     now()->format('Y-m-d H:i') . ': ' . $validated['notes'];
            }

            $reservation->save();

            // Update product committed quantity
            $this->updateProductCommitted($product);

            // Reduce product quantity
            $product->quantity_on_hand -= $validated['quantity_fulfilled'];
            $product->save();
            $product->updateStatus();

            DB::commit();

            return response()->json([
                'message' => 'Reservation fulfilled successfully',
                'reservation' => $reservation->fresh()->load(['reservedBy', 'releasedBy'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to fulfill reservation'], 500);
        }
    }

    /**
     * Release/Cancel a reservation
     */
    public function release(Request $request, Product $product, JobReservation $reservation)
    {
        // Verify the reservation belongs to this product
        if ($reservation->product_id !== $product->id) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        if ($reservation->status === 'fulfilled' || $reservation->status === 'cancelled') {
            return response()->json([
                'message' => 'Reservation is already fulfilled or cancelled'
            ], 422);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $reservation->status = 'cancelled';
            $reservation->released_date = now();
            $reservation->released_by = auth()->id();

            if ($validated['notes'] ?? false) {
                $reservation->notes = ($reservation->notes ? $reservation->notes . "\n" : '') .
                                     now()->format('Y-m-d H:i') . ': ' . $validated['notes'];
            }

            $reservation->save();

            // Update product committed quantity
            $this->updateProductCommitted($product);

            DB::commit();

            return response()->json([
                'message' => 'Reservation released successfully',
                'reservation' => $reservation->fresh()->load(['reservedBy', 'releasedBy'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to release reservation'], 500);
        }
    }

    /**
     * Delete a reservation (soft delete)
     */
    public function destroy(Product $product, JobReservation $reservation)
    {
        // Verify the reservation belongs to this product
        if ($reservation->product_id !== $product->id) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        // Only allow deletion of cancelled or fulfilled reservations
        if ($reservation->status === 'active' || $reservation->status === 'partially_fulfilled') {
            return response()->json([
                'message' => 'Cannot delete active reservations. Please release or fulfill first.',
                'errors' => ['status' => ['Active reservation cannot be deleted']]
            ], 422);
        }

        $reservation->delete();

        return response()->json(['message' => 'Reservation deleted successfully'], 200);
    }

    /**
     * Get reservation statistics for a product
     */
    public function statistics(Product $product)
    {
        $activeReservations = $product->activeReservations;

        $stats = [
            'total_reservations' => $product->jobReservations()->count(),
            'active_reservations_count' => $activeReservations->count(),
            'total_quantity_reserved' => $activeReservations->sum('quantity_reserved'),
            'total_quantity_fulfilled' => $product->jobReservations()
                ->whereIn('status', ['partially_fulfilled', 'fulfilled'])
                ->sum('quantity_fulfilled'),
            'quantity_on_hand' => $product->quantity_on_hand,
            'quantity_committed' => $product->quantity_committed,
            'quantity_available' => $product->quantity_available,
            'atp' => $product->quantity_on_hand - $product->quantity_committed, // Available-to-Promise
            'overdue_reservations' => $activeReservations->filter(function($r) {
                return $r->required_date && $r->required_date->isPast();
            })->count(),
            'upcoming_reservations' => $activeReservations->filter(function($r) {
                return $r->required_date && $r->required_date->isFuture() && $r->required_date->diffInDays(now()) <= 7;
            })->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get all unique job numbers
     */
    public function getAllJobs()
    {
        $jobs = JobReservation::select('job_number', 'job_name')
            ->distinct()
            ->orderBy('job_number', 'desc')
            ->limit(100)
            ->get()
            ->map(function($job) {
                return [
                    'job_number' => $job->job_number,
                    'job_name' => $job->job_name,
                    'label' => $job->job_name ? "{$job->job_number} - {$job->job_name}" : $job->job_number
                ];
            });

        return response()->json($jobs);
    }

    /**
     * Helper: Update product committed quantity from reservations
     */
    private function updateProductCommitted(Product $product)
    {
        $totalCommitted = $product->activeReservations()
            ->sum(DB::raw('quantity_reserved - quantity_fulfilled'));

        $product->quantity_committed = $totalCommitted;
        $product->save();
        $product->updateStatus();
    }
}
