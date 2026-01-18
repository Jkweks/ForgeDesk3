<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorageLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'aisle',
        'bay',
        'level',
        'position',
        'capacity',
        'capacity_unit',
        'is_active',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * Get inventory locations that use this storage location
     */
    public function inventoryLocations()
    {
        return $this->hasMany(InventoryLocation::class, 'location', 'name');
    }

    /**
     * Get full address of the location
     */
    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->aisle ? "Aisle {$this->aisle}" : null,
            $this->bay ? "Bay {$this->bay}" : null,
            $this->level ? "Level {$this->level}" : null,
            $this->position ? "Pos {$this->position}" : null,
        ]);

        return !empty($parts) ? implode(' ', $parts) : null;
    }

    /**
     * Get current utilization (count of products stored here)
     */
    public function getUtilizationAttribute()
    {
        return $this->inventoryLocations()->count();
    }

    /**
     * Get total quantity stored at this location
     */
    public function getTotalQuantityAttribute()
    {
        return $this->inventoryLocations()->sum('quantity');
    }

    /**
     * Scope: active locations only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: order by sort order then name
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
