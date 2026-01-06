<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku', 'part_number', 'finish', 'description', 'long_description',
        'category', 'category_id', 'location',
        'unit_cost', 'unit_price', 'quantity_on_hand', 'quantity_committed',
        'minimum_quantity', 'reorder_point', 'safety_stock', 'average_daily_use',
        'on_order_qty', 'maximum_quantity', 'unit_of_measure',
        'pack_size', 'purchase_uom', 'stock_uom', 'min_order_qty', 'order_multiple',
        'supplier', 'supplier_id', 'supplier_sku', 'supplier_contact', 'lead_time_days',
        'is_active', 'is_discontinued', 'status',
        'configurator_available', 'configurator_type', 'configurator_use_path',
        'dimension_height', 'dimension_depth',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'average_daily_use' => 'decimal:2',
        'dimension_height' => 'decimal:2',
        'dimension_depth' => 'decimal:2',
        'is_active' => 'boolean',
        'is_discontinued' => 'boolean',
        'configurator_available' => 'boolean',
    ];

    protected $appends = ['quantity_available'];

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function committedInventory()
    {
        return $this->hasMany(CommittedInventory::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function inventoryLocations()
    {
        return $this->hasMany(InventoryLocation::class);
    }

    public function jobReservations()
    {
        return $this->hasMany(JobReservation::class);
    }

    public function activeReservations()
    {
        return $this->jobReservations()->where('status', 'active');
    }

    public function requiredParts()
    {
        return $this->hasMany(RequiredPart::class, 'parent_product_id');
    }

    public function usedInProducts()
    {
        return $this->hasMany(RequiredPart::class, 'required_product_id');
    }

    public function getQuantityAvailableAttribute()
    {
        return $this->quantity_on_hand - $this->quantity_committed;
    }

    public function updateStatus()
    {
        $available = $this->quantity_available;
        
        if ($available <= 0) {
            $this->status = 'out_of_stock';
        } elseif ($available <= ($this->minimum_quantity * 0.5)) {
            $this->status = 'critical';
        } elseif ($available <= $this->minimum_quantity) {
            $this->status = 'low_stock';
        } else {
            $this->status = 'in_stock';
        }
        
        $this->save();
    }

    public function adjustQuantity($quantity, $type, $reference = null, $notes = null)
    {
        $quantityBefore = $this->quantity_on_hand;
        $this->quantity_on_hand += $quantity;
        $this->save();

        InventoryTransaction::create([
            'product_id' => $this->id,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $this->quantity_on_hand,
            'reference_number' => $reference,
            'notes' => $notes,
            'user_id' => auth()->id(),
            'transaction_date' => now(),
        ]);

        $this->updateStatus();
    }
}