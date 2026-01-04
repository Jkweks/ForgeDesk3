<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku', 'description', 'long_description', 'category', 'location',
        'unit_cost', 'unit_price', 'quantity_on_hand', 'quantity_committed',
        'minimum_quantity', 'maximum_quantity', 'unit_of_measure',
        'supplier', 'supplier_sku', 'lead_time_days', 'is_active', 'status',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'is_active' => 'boolean',
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