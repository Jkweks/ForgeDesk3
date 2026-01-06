<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobReservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'job_number',
        'job_name',
        'quantity_reserved',
        'reserved_date',
        'required_date',
        'released_date',
        'status',
        'quantity_fulfilled',
        'reserved_by',
        'released_by',
        'notes',
    ];

    protected $casts = [
        'reserved_date' => 'date',
        'required_date' => 'date',
        'released_date' => 'date',
    ];

    /**
     * Get the product this reservation is for
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who reserved this inventory
     */
    public function reservedBy()
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    /**
     * Get the user who released this reservation
     */
    public function releasedBy()
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * Get the remaining quantity to fulfill
     */
    public function getRemainingQuantityAttribute()
    {
        return $this->quantity_reserved - $this->quantity_fulfilled;
    }
}
