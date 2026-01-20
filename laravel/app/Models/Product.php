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
        'category_id', 'location',
        'unit_cost', 'unit_price', 'quantity_on_hand', 'quantity_committed',
        'minimum_quantity', 'reorder_point', 'safety_stock', 'average_daily_use',
        'on_order_qty', 'maximum_quantity', 'unit_of_measure',
        'pack_size', 'purchase_uom', 'stock_uom', 'min_order_qty', 'order_multiple',
        'supplier_id', 'supplier_sku', 'supplier_contact', 'lead_time_days',
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

    protected $appends = ['quantity_available', 'suggested_order_qty', 'days_until_stockout'];

    // Finish codes configuration
    public static $finishCodes = [
        'BL' => 'Black',
        'WH' => 'White',
        'AL' => 'Aluminum',
        'SS' => 'Stainless Steel',
        'BR' => 'Bronze',
        'CH' => 'Chrome',
        'NI' => 'Nickel',
        'BR' => 'Brass',
        'C2' => 'Clear Anodized',
        'DB' => 'Dark Bronze',
        '0R' => 'Oil Rubbed',
        'PW' => 'Powder Coat White',
        'PB' => 'Powder Coat Black',
        'RAW' => 'Raw/Unfinished',
    ];

    // UOM configuration
    public static $unitOfMeasures = [
        'EA' => 'Each',
        'BOX' => 'Box',
        'CASE' => 'Case',
        'GAL' => 'Gallon',
        'LB' => 'Pound',
        'FT' => 'Foot',
        'ROLL' => 'Roll',
        'SET' => 'Set',
        'PCS' => 'Pieces',
        'PKG' => 'Package',
    ];

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

    /**
     * @deprecated Use categories() instead. Single category relationship kept for backward compatibility.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Many-to-many relationship with categories
     * Products can belong to multiple categories/systems
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get the primary category for this product
     */
    public function primaryCategory()
    {
        return $this->categories()->wherePivot('is_primary', true)->first();
    }

    /**
     * Get all category names as a comma-separated string
     */
    public function getCategoryNamesAttribute()
    {
        return $this->categories->pluck('name')->join(', ');
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

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function cycleCountItems()
    {
        return $this->hasMany(CycleCountItem::class);
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

    /**
     * Generate SKU from part number and finish
     */
    public static function generateSku($partNumber, $finish = null)
    {
        if ($finish) {
            return strtoupper($partNumber . '-' . $finish);
        }
        return strtoupper($partNumber);
    }

    /**
     * Auto-generate and set SKU if part_number is set
     */
    public function setSkuFromPartNumber()
    {
        if ($this->part_number) {
            $this->sku = self::generateSku($this->part_number, $this->finish);
        }
    }

    /**
     * Calculate suggested order quantity based on reorder point logic
     */
    public function getSuggestedOrderQtyAttribute()
    {
        // If below reorder point, suggest ordering up to maximum quantity (or 2x reorder point if no max)
        if ($this->reorder_point && $this->quantity_available <= $this->reorder_point) {
            $targetQty = $this->maximum_quantity ?: ($this->reorder_point * 2);
            $suggestedQty = $targetQty - $this->quantity_on_hand - $this->on_order_qty;

            // Round up to order multiple if specified
            if ($this->order_multiple && $this->order_multiple > 1) {
                $suggestedQty = ceil($suggestedQty / $this->order_multiple) * $this->order_multiple;
            }

            // Ensure at least minimum order quantity
            if ($this->min_order_qty && $suggestedQty < $this->min_order_qty) {
                $suggestedQty = $this->min_order_qty;
            }

            return max(0, $suggestedQty);
        }

        return 0;
    }

    /**
     * Calculate days until stockout based on average daily use
     */
    public function getDaysUntilStockoutAttribute()
    {
        if ($this->average_daily_use && $this->average_daily_use > 0) {
            return round($this->quantity_available / $this->average_daily_use, 1);
        }
        return null;
    }

    /**
     * Calculate reorder point using safety stock and lead time
     * Reorder Point = (Average Daily Use × Lead Time) + Safety Stock
     */
    public function calculateReorderPoint()
    {
        if ($this->average_daily_use && $this->lead_time_days) {
            $calculatedReorderPoint = ($this->average_daily_use * $this->lead_time_days);

            if ($this->safety_stock) {
                $calculatedReorderPoint += $this->safety_stock;
            }

            return round($calculatedReorderPoint);
        }

        return $this->safety_stock ?: 0;
    }

    /**
     * Auto-update reorder point
     */
    public function updateReorderPoint()
    {
        $this->reorder_point = $this->calculateReorderPoint();
        $this->save();
    }

    /**
     * Convert quantity between UOMs (Purchase UOM to Stock UOM)
     */
    public function convertPurchaseToStock($purchaseQty)
    {
        if ($this->pack_size && $this->pack_size > 0) {
            return $purchaseQty * $this->pack_size;
        }
        return $purchaseQty;
    }

    /**
     * Convert quantity between UOMs (Stock UOM to Purchase UOM)
     */
    public function convertStockToPurchase($stockQty)
    {
        if ($this->pack_size && $this->pack_size > 0) {
            return $stockQty / $this->pack_size;
        }
        return $stockQty;
    }

    /**
     * Check if product needs reordering
     */
    public function needsReorder()
    {
        if (!$this->reorder_point) {
            return false;
        }

        $availableWithOnOrder = $this->quantity_available + $this->on_order_qty;
        return $availableWithOnOrder <= $this->reorder_point;
    }

    /**
     * Get finish name from code
     */
    public function getFinishNameAttribute()
    {
        if ($this->finish && isset(self::$finishCodes[$this->finish])) {
            return self::$finishCodes[$this->finish];
        }
        return $this->finish;
    }

    /**
     * Get UOM name
     */
    public function getUomNameAttribute()
    {
        if ($this->unit_of_measure && isset(self::$unitOfMeasures[$this->unit_of_measure])) {
            return self::$unitOfMeasures[$this->unit_of_measure];
        }
        return $this->unit_of_measure;
    }

    /**
     * Get purchase UOM name
     */
    public function getPurchaseUomNameAttribute()
    {
        if ($this->purchase_uom && isset(self::$unitOfMeasures[$this->purchase_uom])) {
            return self::$unitOfMeasures[$this->purchase_uom];
        }
        return $this->purchase_uom;
    }

    /**
     * Get stock UOM name
     */
    public function getStockUomNameAttribute()
    {
        if ($this->stock_uom && isset(self::$unitOfMeasures[$this->stock_uom])) {
            return self::$unitOfMeasures[$this->stock_uom];
        }
        return $this->stock_uom;
    }
}