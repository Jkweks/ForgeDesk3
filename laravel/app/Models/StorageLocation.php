<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class StorageLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'aisle',
        'rack',
        'shelf',
        'bin',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['full_path', 'level', 'has_children'];

    /**
     * Get the parent location
     */
    public function parent()
    {
        return $this->belongsTo(StorageLocation::class, 'parent_id');
    }

    /**
     * Get all child locations
     */
    public function children()
    {
        return $this->hasMany(StorageLocation::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Get all descendants recursively
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all inventory locations at this storage location
     */
    public function inventoryLocations()
    {
        return $this->hasMany(InventoryLocation::class, 'storage_location_id');
    }

    /**
     * Get all products stored at this location
     */
    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            InventoryLocation::class,
            'storage_location_id', // Foreign key on inventory_locations
            'id', // Foreign key on products
            'id', // Local key on storage_locations
            'product_id' // Local key on inventory_locations
        );
    }

    /**
     * Get the full hierarchical path as a string
     * Example: "Aisle 1 → Rack A → Shelf 3 → Bin 12"
     */
    public function getFullPathAttribute(): string
    {
        $path = collect($this->ancestors())->pluck('name')->toArray();
        $path[] = $this->name;
        return implode(' → ', $path);
    }

    /**
     * Get the hierarchical level (0 = root, 1 = first child, etc.)
     */
    public function getLevelAttribute(): int
    {
        return count($this->ancestors());
    }

    /**
     * Check if this location has children
     */
    public function getHasChildrenAttribute(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get all ancestors from root to parent
     */
    public function ancestors(): array
    {
        $ancestors = [];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($ancestors, $parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get the hierarchical path as an array
     * Example: ['1', 'A', '3', '12']
     */
    public function pathArray(): array
    {
        return array_filter([
            $this->aisle,
            $this->rack,
            $this->shelf,
            $this->bin,
        ]);
    }

    /**
     * Generate slug from components
     */
    public function generateSlug(): string
    {
        $parts = $this->pathArray();
        return !empty($parts) ? implode('.', $parts) : $this->id;
    }

    /**
     * Parse a location name to extract components
     * Example: "Aisle 1, Rack A, Shelf 3" -> ['aisle' => '1', 'rack' => 'A', 'shelf' => '3']
     */
    public static function parseLocationName(string $locationName): array
    {
        $components = [
            'aisle' => null,
            'rack' => null,
            'shelf' => null,
            'bin' => null,
        ];

        // Try to parse common patterns
        if (preg_match('/Aisle\s*[:=]?\s*(\S+)/i', $locationName, $matches)) {
            $components['aisle'] = $matches[1];
        }

        if (preg_match('/Rack\s*[:=]?\s*(\S+)/i', $locationName, $matches)) {
            $components['rack'] = $matches[1];
        }

        if (preg_match('/Shelf\s*[:=]?\s*(\S+)/i', $locationName, $matches)) {
            $components['shelf'] = $matches[1];
        }

        if (preg_match('/Bin\s*[:=]?\s*(\S+)/i', $locationName, $matches)) {
            $components['bin'] = $matches[1];
        }

        // Handle path-style notation (e.g., "1.2.3.4")
        if (preg_match('/^(\d+)\.?(\d+)?\.?(\d+)?\.?(\d+)?$/', $locationName, $matches)) {
            $components['aisle'] = $matches[1] ?? null;
            $components['rack'] = $matches[2] ?? null;
            $components['shelf'] = $matches[3] ?? null;
            $components['bin'] = $matches[4] ?? null;
        }

        return $components;
    }

    /**
     * Format location name from components
     */
    public function formatName(): string
    {
        $parts = [];

        if ($this->aisle) {
            $parts[] = "Aisle {$this->aisle}";
        }
        if ($this->rack) {
            $parts[] = "Rack {$this->rack}";
        }
        if ($this->shelf) {
            $parts[] = "Shelf {$this->shelf}";
        }
        if ($this->bin) {
            $parts[] = "Bin {$this->bin}";
        }

        return !empty($parts) ? implode(', ', $parts) : $this->name;
    }

    /**
     * Get total inventory quantity at this location (including children)
     */
    public function totalQuantity(): int
    {
        $total = $this->inventoryLocations()->sum('quantity');

        // Add quantities from child locations
        foreach ($this->children as $child) {
            $total += $child->totalQuantity();
        }

        return $total;
    }

    /**
     * Get total committed quantity at this location (including children)
     */
    public function totalCommittedQuantity(): int
    {
        $total = $this->inventoryLocations()->sum('quantity_committed');

        // Add committed quantities from child locations
        foreach ($this->children as $child) {
            $total += $child->totalCommittedQuantity();
        }

        return $total;
    }

    /**
     * Get total available quantity at this location (including children)
     */
    public function totalAvailableQuantity(): int
    {
        return $this->totalQuantity() - $this->totalCommittedQuantity();
    }

    /**
     * Get all root locations (no parent)
     */
    public static function roots(): Collection
    {
        return self::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Build a hierarchical tree structure
     */
    public static function tree(): Collection
    {
        return self::roots()->map(function ($location) {
            return $location->loadTreeChildren();
        });
    }

    /**
     * Load children recursively for tree building
     */
    public function loadTreeChildren(): self
    {
        $this->load([
            'children' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name');
            }
        ]);

        foreach ($this->children as $child) {
            $child->loadTreeChildren();
        }

        return $this;
    }

    /**
     * Scope: Only active locations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Root locations only
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Boot method to auto-generate slug
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($location) {
            if (!$location->slug || $location->isDirty(['aisle', 'rack', 'shelf', 'bin'])) {
                $location->slug = $location->generateSlug();
            }
        });
    }
}
