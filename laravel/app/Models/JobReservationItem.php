<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobReservationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'product_id',
        'requested_qty',
        'committed_qty',
        'consumed_qty',
    ];

    protected $casts = [
        'reservation_id' => 'integer',
        'product_id' => 'integer',
        'requested_qty' => 'integer',
        'committed_qty' => 'integer',
        'consumed_qty' => 'integer',
    ];

    /**
     * Get the reservation this item belongs to
     */
    public function reservation()
    {
        return $this->belongsTo(JobReservation::class, 'reservation_id');
    }

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the released quantity (committed - consumed)
     */
    public function getReleasedQtyAttribute()
    {
        return $this->committed_qty - $this->consumed_qty;
    }

    /**
     * Get the shortfall (requested - committed)
     */
    public function getShortfallAttribute()
    {
        return max(0, $this->requested_qty - $this->committed_qty);
    }
}
