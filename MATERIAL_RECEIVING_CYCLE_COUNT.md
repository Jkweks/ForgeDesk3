# Material Receiving & Cycle Counting Implementation

## Overview
This document outlines the complete implementation of Material Receiving (Purchase Orders) and Cycle Counting workflows for ForgeDesk3.

## Backend Implementation ✅ COMPLETED

### Database Migrations
- **`2026_01_11_000001_create_purchase_orders_table.php`**
  - `purchase_orders` table with fields: po_number, supplier_id, status, order_date, expected_date, received_date, total_amount, notes, approvals
  - `purchase_order_items` table with fields: purchase_order_id, product_id, quantity_ordered, quantity_received, unit_cost, total_cost, destination_location

- **`2026_01_11_000002_create_cycle_count_sessions_table.php`**
  - `cycle_count_sessions` table with fields: session_number, location, category_id, status, scheduled_date, assigned_to, reviewed_by
  - `cycle_count_items` table with fields: session_id, product_id, location_id, system_quantity, counted_quantity, variance, variance_status, adjustment_created

### Models
- **PurchaseOrder** - Manages PO lifecycle with computed properties (is_fully_received, receive_progress, total_received)
- **PurchaseOrderItem** - Individual line items with receiving tracking
- **CycleCountSession** - Count sessions with accuracy calculations
- **CycleCountItem** - Individual count records with variance approval workflow

### Controllers
- **PurchaseOrderController** (`/api/v1/purchase-orders`)
  - CRUD operations
  - `POST /purchase-orders/{id}/submit` - Submit for approval
  - `POST /purchase-orders/{id}/approve` - Approve PO
  - `POST /purchase-orders/{id}/receive` - Receive materials (creates inventory transactions)
  - `POST /purchase-orders/{id}/cancel` - Cancel PO
  - `GET /purchase-orders-open` - Get receivable POs
  - `GET /purchase-orders-statistics` - PO statistics

- **CycleCountController** (`/api/v1/cycle-counts`)
  - CRUD operations
  - `POST /cycle-counts/{id}/start` - Start counting session
  - `POST /cycle-counts/{id}/record-count` - Record physical count
  - `POST /cycle-counts/{id}/approve-variances` - Approve variances and create adjustments
  - `POST /cycle-counts/{id}/complete` - Complete session
  - `GET /cycle-counts/{id}/variance-report` - Variance analysis
  - `GET /cycle-counts-active` - Active count sessions

### API Routes ✅ ADDED
All routes registered in `routes/api.php` under `/api/v1/` prefix with auth middleware.

### Model Relationships ✅ UPDATED
- Product → purchaseOrderItems()
- Product → cycleCountItems()
- Supplier → purchaseOrders()

## Frontend Implementation 🔧 TO BE COMPLETED

### Required Views

#### 1. Purchase Orders View (`purchase-orders.blade.php`)
**Features Needed:**
- PO list table with filters (status, supplier, date range)
- Create PO modal with multi-line item entry
- PO details modal showing:
  - Header info (supplier, dates, totals)
  - Line items with receive progress bars
  - Receive button opening receiving modal
- Receiving modal:
  - Select items to receive
  - Enter quantities (validation: not > remaining)
  - Assign location per item
  - Batch receive multiple items
- Status workflow buttons (Submit → Approve → Receive)
- Statistics cards (open POs, pending value, etc.)

**Key JavaScript Functions:**
```javascript
loadPurchaseOrders()          // Load PO list
showCreatePOModal()           // Open create modal
createPurchaseOrder(data)     // POST new PO
viewPODetails(poId)           // Show PO details
showReceiveModal(poId)        // Open receive modal
receiveMaterials(poId, items) // POST receive transaction
submitPO(poId)                // Submit for approval
approvePO(poId)               // Approve PO
cancelPO(poId)                // Cancel PO
```

#### 2. Cycle Counting View (`cycle-counting.blade.php`)
**Features Needed:**
- Session list with filters (status, location, date)
- Create session modal:
  - Select location or category
  - Choose specific products or all in scope
  - Assign counter
- Count entry interface:
  - Product list with system quantity
  - Input fields for counted quantity
  - Real-time variance calculation
  - Color-coding (green = match, yellow = small variance, red = large variance)
- Variance review modal:
  - List items with variances
  - Approve/reject variances
  - Bulk approve within tolerance
- Session statistics:
  - Progress percentage
  - Accuracy percentage
  - Total variance
- Complete session workflow

**Key JavaScript Functions:**
```javascript
loadCycleCounts()                // Load session list
showCreateSessionModal()         // Open create modal
createCycleCount(data)          // POST new session
viewSession(sessionId)          // Show count interface
startSession(sessionId)         // POST start
recordCount(itemId, quantity)   // POST count
showVarianceReview(sessionId)   // Variance modal
approveVariances(sessionId, itemIds) // POST approve
completeSession(sessionId)      // POST complete
```

### Navigation Updates
Add to Inventory dropdown:
- "Purchase Orders" → /purchase-orders
- "Cycle Counting" → /cycle-counting

Or create separate "Operations" menu:
- Material Receiving
- Cycle Counting
- Replenishment (link to Reports → Reorder Recommendations)

## Integration Points

### Material Receiving → Inventory
- Creates `InventoryTransaction` with type='receipt'
- Updates `Product.quantity_on_hand`
- Reduces `Product.on_order_qty`
- Updates `InventoryLocation.quantity` if location specified
- Transaction references PO number

### Cycle Counting → Inventory
- Creates `InventoryTransaction` with type='cycle_count'
- Updates `Product.quantity_on_hand` to counted value
- Updates `InventoryLocation.quantity` if location-specific
- Transaction references cycle count session number
- Full audit trail of variances

### Reorder Recommendations → Purchase Orders
- Reorder recommendations report shows what to buy
- "Create PO" button on recommendations
- Pre-fills PO with recommended items
- Links to supplier information

## Workflow Examples

### Purchase Order Lifecycle
1. Create PO (Draft) → Add items → Submit
2. Manager Approves PO
3. Warehouse receives materials → Updates inventory
4. PO status: Partially Received → Received (when complete)

### Cycle Count Lifecycle
1. Create Session → Select location/category/products
2. Assign to counter → Start session
3. Counter enters physical counts
4. System calculates variances
5. Supervisor reviews variances → Approve adjustments
6. Complete session → Adjustments applied to inventory

## Testing Checklist
- [ ] Create PO with multiple items
- [ ] Submit and approve PO
- [ ] Partially receive PO
- [ ] Fully receive PO
- [ ] Cancel PO (releases on_order_qty)
- [ ] Create cycle count session
- [ ] Record counts with variances
- [ ] Approve variances
- [ ] Verify inventory transactions created
- [ ] Complete cycle count session
- [ ] Export reports

## Next Steps
1. Create `purchase-orders.blade.php` view (est. ~800 lines)
2. Create `cycle-counting.blade.php` view (est. ~700 lines)
3. Add web routes for `/purchase-orders` and `/cycle-counting`
4. Update navigation menu
5. Test complete workflows
6. Document for users

## Benefits
- **Full Purchase Order Management** - From creation through receiving
- **Location-Based Receiving** - Direct to bin/shelf
- **Variance Control** - Identify and resolve inventory discrepancies
- **Audit Trail** - Complete transaction history
- **Supplier Performance** - Track lead times and accuracy
- **Inventory Accuracy** - Regular cycle counting improves data quality
- **Automated Adjustments** - Cycle count variances auto-adjust inventory
