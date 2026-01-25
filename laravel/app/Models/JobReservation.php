<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobReservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'job_number',
        'release_number',
        'job_name',
        'requested_by',
        'needed_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'needed_by' => 'date',
        'release_number' => 'integer',
    ];

    /**
     * Get the reservation items
     */
    public function items()
    {
        return $this->hasMany(JobReservationItem::class, 'reservation_id');
    }

    /**
     * Get total requested quantity
     */
    public function getTotalRequestedAttribute()
    {
        return $this->items()->sum('requested_qty');
    }

    /**
     * Get total committed quantity
     */
    public function getTotalCommittedAttribute()
    {
        return $this->items()->sum('committed_qty');
    }

    /**
     * Get total consumed quantity
     */
    public function getTotalConsumedAttribute()
    {
        return $this->items()->sum('consumed_qty');
    }

    /**
     * Status labels for display
     */
    public static function statusLabels(): array
    {
        return [
            'draft' => 'Draft - Details still being gathered',
            'active' => 'Active - Inventory committed, waiting to be pulled',
            'in_progress' => 'In Progress - Work started, team consuming inventory',
            'fulfilled' => 'Fulfilled - All inventory reconciled',
            'on_hold' => 'On Hold - Reserved but temporarily paused',
            'cancelled' => 'Cancelled - No inventory being held',
        ];
    }

    /**
     * Get human-readable status
     */
    public function getStatusLabelAttribute()
    {
        $labels = self::statusLabels();
        return $labels[$this->status] ?? $this->status;
    }
}
