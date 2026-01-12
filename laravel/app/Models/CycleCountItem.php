<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CycleCountItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'product_id',
        'location_id',
        'system_quantity',
        'counted_quantity',
        'variance',
        'variance_status',
        'count_notes',
        'counted_by',
        'counted_at',
        'adjustment_created',
        'transaction_id',
    ];

    protected $casts = [
        'system_quantity' => 'integer',
        'counted_quantity' => 'integer',
        'variance' => 'integer',
        'counted_at' => 'datetime',
        'adjustment_created' => 'boolean',
    ];

    // Relationships
    public function session()
    {
        return $this->belongsTo(CycleCountSession::class, 'session_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    public function counter()
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function transaction()
    {
        return $this->belongsTo(InventoryTransaction::class);
    }

    // Computed properties
    public function getVariancePercentageAttribute()
    {
        if ($this->system_quantity == 0) {
            return $this->counted_quantity > 0 ? 100 : 0;
        }
        return round(($this->variance / $this->system_quantity) * 100, 1);
    }

    public function getIsAccurateAttribute()
    {
        return $this->variance == 0;
    }

    public function getNeedsReviewAttribute()
    {
        return abs($this->variance) > 5; // Threshold can be configurable
    }

    // Record count
    public function recordCount($countedQuantity, $userId, $notes = null)
    {
        $variance = $countedQuantity - $this->system_quantity;

        // Determine variance status
        $varianceStatus = 'pending';
        if ($variance == 0) {
            $varianceStatus = 'within_tolerance';
        } elseif (abs($variance) > 5) { // Configurable threshold
            $varianceStatus = 'requires_review';
        } else {
            $varianceStatus = 'within_tolerance';
        }

        $this->update([
            'counted_quantity' => $countedQuantity,
            'variance' => $variance,
            'variance_status' => $varianceStatus,
            'count_notes' => $notes,
            'counted_by' => $userId,
            'counted_at' => now(),
        ]);

        return $this;
    }

    // Approve variance and create adjustment
    public function approveVariance($userId)
    {
        if ($this->variance == 0) {
            $this->update(['variance_status' => 'approved']);
            return null;
        }

        // Create inventory adjustment transaction
        $transaction = InventoryTransaction::create([
            'product_id' => $this->product_id,
            'type' => 'cycle_count',
            'quantity' => $this->variance,
            'quantity_before' => $this->system_quantity,
            'quantity_after' => $this->counted_quantity,
            'reference_number' => $this->session->session_number,
            'reference_type' => 'cycle_count',
            'reference_id' => $this->session_id,
            'notes' => "Cycle count adjustment: Expected {$this->system_quantity}, Counted {$this->counted_quantity}. " . ($this->count_notes ?? ''),
            'user_id' => $userId,
            'transaction_date' => now(),
        ]);

        // Update product quantity
        $product = $this->product;
        $product->quantity_on_hand = $this->counted_quantity;
        $product->save();

        // Update location quantity if location-specific
        if ($this->location_id) {
            $location = $this->location;
            $location->quantity = $this->counted_quantity;
            $location->save();
        }

        $this->update([
            'variance_status' => 'approved',
            'adjustment_created' => true,
            'transaction_id' => $transaction->id,
        ]);

        return $transaction;
    }
}
