# ForgeDesk2 to ForgeDesk3 - Feature Parity Implementation Plan

## Executive Summary

This document outlines the comprehensive plan to migrate missing features from ForgeDesk2 (PHP/PostgreSQL) to ForgeDesk3 (Laravel 11). The plan is organized into 5 phases with estimated effort of 12-18 weeks for full implementation.

**Current Status:** ✅ Phase 1.1 Complete (Hierarchical Storage Locations)

---

## Technology Stack Comparison

| Aspect | ForgeDesk2 | ForgeDesk3 |
|--------|------------|------------|
| Backend | PHP 8.x (Procedural) | Laravel 11 |
| Database | PostgreSQL | SQLite/MySQL |
| Frontend | HTML/CSS/JS (Bootstrap) | Blade + Tailwind CSS + Vite |
| Admin | Django REST Framework | Laravel Native |
| API | Procedural PHP | RESTful API (Sanctum) |
| Version | v0.4.2 | v1.0+ |

---

## Missing Features Overview

### ⭐ Major Features (High Priority)
1. **Door Configurator (CPQ System)** - 3,231 lines of code
2. **EZ Estimate Integration** - Excel-based quote system
3. **Material Replenishment Planner** - Supplier-grouped ordering
4. **Hierarchical Storage Locations** - ✅ COMPLETE
5. **Inventory Systems / Framing Systems**

### 📊 Medium Priority
6. Daily Usage Tracking
7. PO PDF Generation
8. Job Reservation PDF Export
9. Database Health Dashboard

### 🔧 Low Priority
10. Metrics Editor
11. Inventory Reconciliation Tools
12. Import Utilities

---

## Phase 1: Foundation & Data Models (HIGH PRIORITY)

### Phase 1.1: Hierarchical Storage Locations ✅ COMPLETE

**Status:** ✅ Implemented on 2026-01-13

**What Was Built:**
- `storage_locations` table with parent_id hierarchy
- Aisle → Rack → Shelf → Bin structure
- `StorageLocation` model with tree traversal
- 18+ API endpoints for CRUD and management
- Frontend management page with tree view
- Reconciliation tools for data migration
- Automatic location name parsing

**Files Created:**
- Migration: `2026_01_13_000001_create_storage_locations_table.php`
- Migration: `2026_01_13_000002_add_storage_location_to_inventory_locations.php`
- Model: `StorageLocation.php`
- Controllers: `StorageLocationController.php`, `StorageLocationReconciliationController.php`
- View: `storage-locations.blade.php`
- Documentation: `HIERARCHICAL_STORAGE_LOCATIONS.md`

**Key Features:**
- Hierarchical location organization (4 levels)
- Automatic slug generation (e.g., "1.2.3.4")
- Bulk creation from path notation
- Location name parsing and formatting
- Quantity aggregation including children
- Circular reference prevention
- Search and statistics

**Testing:**
```bash
cd /home/user/ForgeDesk3/laravel
php artisan migrate
# Access: /storage-locations
```

---

### Phase 1.2: Inventory Systems / Framing Systems

**Effort:** Medium (2 weeks) | **Impact:** High | **Dependencies:** None

**Business Value:**
- Track which framing systems products belong to
- Default part configurations per system
- System-specific glazing and part defaults

**Database Changes:**

**Table: `inventory_systems`**
```sql
- id (primary key)
- name (string, e.g., "Tubelite 400")
- manufacturer (string, e.g., "Tubelite")
- system (string, system code)
- default_glazing (string, e.g., "1/4 inch")
- default_frame_parts (JSON, default part configuration)
- default_door_parts (JSON, default door configuration)
- system_type (enum: framing, door, hardware)
- created_at, updated_at
- soft_deletes
```

**Table: `inventory_item_systems` (Pivot)**
```sql
- id (primary key)
- product_id (foreign key to products)
- system_id (foreign key to inventory_systems)
- created_at, updated_at
```

**Implementation Steps:**

1. **Create Migrations** (1 day)
   ```bash
   php artisan make:migration create_inventory_systems_table
   php artisan make:migration create_inventory_item_systems_table
   ```

2. **Create Model** (1 day)
   - File: `app/Models/InventorySystem.php`
   - Relationships: belongsToMany(Product)
   - JSON casts for default_frame_parts, default_door_parts
   - Scopes: byManufacturer(), byType()

3. **Update Product Model** (0.5 days)
   - Add: `public function systems() { return $this->belongsToMany(InventorySystem::class, 'inventory_item_systems'); }`

4. **Create Controller** (2 days)
   - File: `app/Http/Controllers/Api/InventorySystemController.php`
   - Endpoints:
     - `GET /v1/inventory-systems` - List all systems
     - `GET /v1/inventory-systems/{id}` - Get specific system
     - `POST /v1/inventory-systems` - Create system
     - `PUT /v1/inventory-systems/{id}` - Update system
     - `DELETE /v1/inventory-systems/{id}` - Delete system
     - `GET /v1/inventory-systems/{id}/products` - Get products in system
     - `POST /v1/products/{product}/systems` - Link product to systems
     - `DELETE /v1/products/{product}/systems/{system}` - Unlink

5. **Create Frontend UI** (3 days)
   - File: `resources/views/inventory-systems.blade.php`
   - Features:
     - List view with manufacturer grouping
     - Add/Edit system modal
     - JSON editor for default parts
     - Product assignment interface
   - Add to navigation

6. **Seed Data** (0.5 days)
   - Create sample systems:
     - Tubelite 400 Series
     - Kawneer 350 Series
     - YKK AP
   - Link to existing products

7. **Testing** (1 day)
   - Create systems
   - Link products to multiple systems
   - Test JSON configurations
   - Verify relationships

**API Examples:**

```bash
# Create system
POST /api/v1/inventory-systems
{
  "name": "Tubelite 400 Series",
  "manufacturer": "Tubelite",
  "system": "T400",
  "default_glazing": "1/4 inch",
  "system_type": "framing",
  "default_frame_parts": {
    "top_rail": "T400-TR",
    "bottom_rail": "T400-BR"
  }
}

# Link product to system
POST /api/v1/products/123/systems
{
  "system_ids": [1, 2, 3]
}
```

---

### Phase 1.3: Daily Usage Tracking

**Effort:** Low (1 week) | **Impact:** Medium | **Dependencies:** None

**Business Value:**
- Track daily consumption patterns
- Improve velocity calculations
- Better forecasting for replenishment

**Database Changes:**

**Table: `inventory_daily_usage`**
```sql
- id (primary key)
- product_id (foreign key to products)
- usage_date (date)
- quantity_used (decimal)
- created_at, updated_at
- UNIQUE constraint: (product_id, usage_date)
- Index on: product_id, usage_date
```

**Implementation Steps:**

1. **Create Migration** (0.5 days)
   ```bash
   php artisan make:migration create_inventory_daily_usage_table
   ```

2. **Create Model** (0.5 days)
   - File: `app/Models/InventoryDailyUsage.php`
   - Relationship: belongsTo(Product)
   - Scopes: forDate(), forDateRange(), forProduct()

3. **Create Observer** (1 day)
   - File: `app/Observers/ProductTransactionObserver.php`
   - Listen to InventoryTransaction events
   - Automatically log daily usage on consumption transactions
   - Aggregate transactions by date

4. **Update Product Model** (0.5 days)
   - Add method: `calculateAverageDailyUsage($days = 30)`
   - Add method: `getUsageHistory($days = 90)`
   - Use daily usage table instead of calculating from transactions

5. **Update Velocity Calculations** (1 day)
   - Modify: `app/Services/VelocityService.php` (if exists)
   - Use daily_usage data for more accurate calculations
   - Add trend analysis

6. **Create API Endpoints** (1 day)
   - `GET /v1/products/{product}/daily-usage` - Get usage history
   - `GET /v1/products/{product}/usage-trends` - Get trends
   - `POST /v1/products/{product}/log-usage` - Manual usage logging

7. **Update Reports** (1 day)
   - Add daily usage data to velocity reports
   - Create usage trend charts
   - Add to product detail page

8. **Testing** (0.5 days)
   - Create transactions
   - Verify daily usage logged
   - Test average calculations
   - Verify unique constraint

**Usage Example:**

```php
// In observer or transaction handler
public function created(InventoryTransaction $transaction)
{
    if ($transaction->type === 'consumption') {
        foreach ($transaction->lines as $line) {
            InventoryDailyUsage::updateOrCreate(
                [
                    'product_id' => $line->product_id,
                    'usage_date' => now()->toDateString(),
                ],
                [
                    'quantity_used' => DB::raw("quantity_used + {$line->quantity}")
                ]
            );
        }
    }
}

// Calculate average
$product->calculateAverageDailyUsage(30); // Last 30 days
```

---

## Phase 2: Material Replenishment Module (HIGH PRIORITY)

### Phase 2.1: Enhanced Replenishment Calculator

**Effort:** High (2 weeks) | **Impact:** High | **Dependencies:** 1.3 (Daily Usage)

**Business Value:**
- Automated reorder recommendations
- Supplier-grouped purchasing workflow
- Lead time and safety stock calculations

**Implementation:**

**Create Service Class** (2 days)
- File: `app/Services/ReplenishmentService.php`
- Methods:
  ```php
  public function calculateReplenishment($productId)
  {
      $product = Product::with('supplier', 'dailyUsage')->find($productId);

      // Lead time demand = average_daily_use × lead_time_days
      $avgDailyUse = $product->calculateAverageDailyUsage(30);
      $leadTimeDemand = $avgDailyUse * $product->lead_time_days;

      // Target level = lead time demand + safety_stock
      $targetLevel = $leadTimeDemand + $product->safety_stock;

      // Projected available = on_hand - committed + on_order
      $projectedAvailable = $product->quantity_on_hand
                          - $product->quantity_committed
                          + $product->quantity_on_order;

      // Recommended quantity
      $recommendedQty = max($targetLevel - $projectedAvailable, 0);

      // Days of supply
      $daysOfSupply = $avgDailyUse > 0
          ? $projectedAvailable / $avgDailyUse
          : 999;

      return [
          'product_id' => $productId,
          'on_hand' => $product->quantity_on_hand,
          'committed' => $product->quantity_committed,
          'on_order' => $product->quantity_on_order,
          'available_now' => $product->quantity_available,
          'projected_available' => $projectedAvailable,
          'average_daily_use' => $avgDailyUse,
          'lead_time_demand' => $leadTimeDemand,
          'safety_stock' => $product->safety_stock,
          'target_level' => $targetLevel,
          'recommended_quantity' => $recommendedQty,
          'days_of_supply' => round($daysOfSupply, 1),
          'reorder_point' => $product->reorder_point,
          'supplier' => $product->supplier,
      ];
  }

  public function groupBySupplier()
  {
      // Get all products needing replenishment
      $products = Product::with('supplier')
          ->where('quantity_available', '<=', DB::raw('reorder_point'))
          ->get();

      $grouped = [];
      foreach ($products as $product) {
          $calc = $this->calculateReplenishment($product->id);
          $supplierId = $product->supplier_id ?? 'no_supplier';
          $grouped[$supplierId][] = $calc;
      }

      return $grouped;
  }
  ```

**Create Controller** (2 days)
- File: `app/Http/Controllers/Api/ReplenishmentController.php`
- Endpoints:
  - `GET /v1/replenishment/calculate` - Calculate for all products
  - `GET /v1/replenishment/by-supplier` - Grouped by supplier
  - `GET /v1/replenishment/product/{id}` - Calculate for one product
  - `POST /v1/replenishment/create-po` - Create PO from recommendations

**Create Frontend Page** (4 days)
- File: `resources/views/replenishment.blade.php`
- Features:
  - **Supplier Tabs** - One tab per supplier
  - **Columns**:
    - SKU, Description
    - On Hand, Committed, On Order
    - Available Now, Projected Available
    - Avg Daily Use, Days of Supply
    - Reorder Point, Target Level
    - Recommended Qty
    - Order Qty (editable input)
    - Include (checkbox)
  - **Actions per Supplier**:
    - "Generate PO PDF" button
    - "Generate EZ Estimate" button (for Tubelite)
    - "Create Purchase Order" button
  - **Filters**:
    - Show only items needing reorder
    - Show all items
    - Minimum days of supply threshold

**Integration with PO System** (2 days)
- Create PO from selected items
- Pre-fill quantities from recommendations
- Link to supplier

**Testing** (1 day)

---

### Phase 2.2: PO PDF Generation

**Effort:** Medium (1 week) | **Impact:** Medium | **Dependencies:** 2.1

**Implementation:**

1. **Install PDF Package** (0.5 days)
   ```bash
   composer require barryvdh/laravel-dompdf
   ```

2. **Create PDF Service** (2 days)
   - File: `app/Services/PurchaseOrderPdfService.php`
   - Methods: `generate($purchaseOrderId)`, `download($purchaseOrderId)`
   - Template: Company letterhead, line items, totals

3. **Create Blade Template** (2 days)
   - File: `resources/views/pdf/purchase-order.blade.php`
   - Design:
     - Header: Company info, PO number, date
     - Supplier info block
     - Line items table
     - Totals: Subtotal, Tax, Total
     - Footer: Terms, signature lines

4. **Add Controller Method** (0.5 days)
   - In: `PurchaseOrderController.php`
   - Method: `public function downloadPdf($id)`
   - Route: `GET /v1/purchase-orders/{id}/pdf`

5. **Frontend Integration** (1 day)
   - Add "Download PDF" button to PO detail view
   - Add "Print" button
   - Add email functionality (optional)

6. **Testing** (1 day)

---

## Phase 3: Door Configurator (CPQ) System (MEDIUM-HIGH PRIORITY)

### Phase 3.1: Configurator Data Models

**Effort:** High (2-3 weeks) | **Impact:** Very High | **Dependencies:** 1.2 (Systems)

**Business Value:**
- Configure-Price-Quote for door assemblies
- Automated BOM generation
- System compatibility checking
- Part requirement resolution

**Database Changes:**

**Table: `configurator_part_use_options`** (Hierarchical Taxonomy)
```sql
- id (primary key)
- name (string, e.g., "Top Rail", "Stile", "Hinge")
- parent_id (foreign key, self-reference)
- sort_order (integer)
- created_at, updated_at
```

**Table: `configurator_part_profiles`** (Extends Products)
```sql
- id (primary key)
- product_id (foreign key to products, unique)
- is_enabled (boolean, is this part available in configurator)
- part_type (enum: door, frame, hardware, accessory)
- height_lz (decimal, nullable, length in Z dimension)
- depth_ly (decimal, nullable, length in Y dimension)
- created_at, updated_at
```

**Table: `configurator_part_use_links`** (Many-to-Many)
```sql
- id (primary key)
- product_id (foreign key to products)
- use_option_id (foreign key to configurator_part_use_options)
- created_at, updated_at
- UNIQUE constraint: (product_id, use_option_id)
```

**Table: `configurator_part_requirements`** (BOM Dependencies)
```sql
- id (primary key)
- product_id (foreign key to products, the parent part)
- required_product_id (foreign key to products, the required part)
- quantity (decimal, how many are required)
- finish_policy (enum: fixed, inherited, any)
- fixed_finish (string, nullable, used if policy=fixed)
- sort_order (integer)
- created_at, updated_at
```

**Table: `door_configurations`**
```sql
- id (primary key)
- job_id (foreign key to jobs, nullable)
- user_id (foreign key to users)
- configuration_name (string)
- scope (enum: door_and_frame, frame_only, door_only)
- status (enum: draft, in_progress, released, cancelled)
- quantity (integer)
- system_id (foreign key to inventory_systems)
- frame_config (JSON, frame configuration)
- active_door_config (JSON, active door leaf config)
- inactive_door_config (JSON, nullable, inactive door leaf for pairs)
- door_math_params (JSON, gaps and dimensions)
- notes (text, nullable)
- created_at, updated_at, soft_deletes
```

**Implementation Steps:**

1. **Create Migrations** (2 days)
2. **Create Models** (3 days)
   - ConfiguratorPartUseOption (hierarchical)
   - ConfiguratorPartProfile
   - ConfiguratorPartUseLink
   - ConfiguratorPartRequirement
   - DoorConfiguration
3. **Create Enums** (1 day)
   - PartType, FinishPolicy, ConfigurationScope, ConfigurationStatus
4. **Seed Part Use Options** (1 day)
   - Frame parts: Top Rail, Bottom Rail, Stiles, Muntins
   - Door parts: Stiles, Rails, Glass, Hardware
   - Hardware: Hinges, Locks, Closers, Panic Devices
5. **Testing** (1 day)

---

### Phase 3.2: Configurator Business Logic

**Effort:** Very High (3-4 weeks) | **Impact:** Very High | **Dependencies:** 3.1

**Create Service Class** (2 weeks)
- File: `app/Services/ConfiguratorService.php`
- Core Methods:
  ```php
  public function getAvailableFrameParts($systemId, $openingType, $glazingThickness)
  public function getAvailableDoorParts($systemId, $glazingThickness, $hingingType)
  public function validateConfiguration($configData)
  public function resolvePartRequirements($partId, $finish = null)
  public function explodeBOM($configId)
  public function checkAvailability($configId, $quantity = 1)
  public function calculateDimensions($configData)
  public function applyFinishPolicy($requirement, $inheritedFinish)
  ```

**Create Validation Service** (1 week)
- File: `app/Services/ConfiguratorValidationService.php`
- Validate:
  - Part compatibility
  - System compatibility
  - Required part selections
  - Dimension constraints
  - Finish availability

**Door Math Implementation** (3 days)
- Calculate:
  - Top gap (0.125")
  - Bottom gap (0.6875")
  - Hinge gap (0.0625")
  - Lock gap (0.125")
  - Door dimensions from frame dimensions
  - Frame dimensions from opening dimensions

**Testing** (3 days)

---

### Phase 3.3: Configurator Frontend UI

**Effort:** Very High (3 weeks) | **Impact:** Very High | **Dependencies:** 3.2

**Main Configurator Page** (2 weeks)
- File: `resources/views/configurator.blade.php`
- Sections:
  1. **Configuration Header**
     - Configuration name input
     - Scope selector (radio buttons)
     - Job linking dropdown
     - Quantity input
     - Status display

  2. **System Selection**
     - System dropdown (Tubelite, Kawneer, etc.)
     - Glazing thickness selector

  3. **Frame Configuration** (if scope includes frame)
     - Opening type (single, pair)
     - Frame part selectors (hierarchical dropdowns)
     - Transom options (yes/no)
     - Transom height input
     - Preview dimensions

  4. **Door Configuration** (if scope includes door)
     - **Active Leaf Tab**
       - Door system dropdown
       - Glazing thickness
       - Stile selection
       - Hand (LH, RH)
       - Hinging type (continuous, standard)
       - Part selections
     - **Inactive Leaf Tab** (for pairs)
       - Same options as active

  5. **BOM Preview**
     - Exploded parts list
     - Quantities
     - Availability indicators
     - Total cost estimate

  6. **Actions**
     - Save Draft
     - Validate
     - Submit/Release
     - Export BOM

**Interactive Features** (1 week)
- Real-time validation
- Dynamic part filtering based on selections
- Availability checking
- Dimension calculations
- Visual preview (optional, SVG-based)

**Testing** (2 days)

---

## Phase 4: EZ Estimate Integration (MEDIUM PRIORITY)

### Phase 4.1: Excel Parsing Library

**Effort:** Medium (1 week) | **Impact:** Medium | **Dependencies:** None

1. **Install PhpSpreadsheet** (0.5 days)
   ```bash
   composer require phpoffice/phpspreadsheet
   ```

2. **Create Parser Service** (2 days)
   - File: `app/Services/ExcelParserService.php`
   - Methods: `readWorkbook()`, `listSheets()`, `getSheetData()`, `getCellValue()`

3. **Create EZ Estimate Parser** (2 days)
   - File: `app/Services/EzEstimateParserService.php`
   - Parse specific sheets:
     - Accessories sheet
     - Stock Lengths sheet
     - Special Length sheet
   - Extract SKUs and quantities

4. **Testing** (1 day)

---

### Phase 4.2: EZ Estimate Template Management

**Effort:** Medium (1 week) | **Impact:** Medium | **Dependencies:** 4.1

**Database:**

**Table: `ez_estimate_templates`**
```sql
- id
- filename (string)
- file_path (string)
- sheet_mappings (JSON, column mappings)
- is_active (boolean)
- uploaded_at (timestamp)
- created_at, updated_at
```

**Implementation:**
1. Migration + Model (1 day)
2. Upload controller (1 day)
3. Template configuration page (2 days)
4. Sheet mapping editor (1 day)
5. Testing (1 day)

---

### Phase 4.3: Estimate Check & Validation

**Effort:** High (1.5 weeks) | **Impact:** High | **Dependencies:** 4.1, 4.2

**Create Validation Service** (1 week)
- File: `app/Services/EzEstimateValidationService.php`
- Features:
  - Parse uploaded estimate file
  - Map SKUs to products
  - Check inventory availability
  - Generate shortage report
  - Flag unmapped SKUs
  - Calculate total cost

**Create UI** (2 days)
- File: `resources/views/admin/estimate-check.blade.php`
- Upload form
- Validation results table
- Download report button

**Testing** (1 day)

---

### Phase 4.4: EZ Estimate Export from Replenishment

**Effort:** High (1.5 weeks) | **Impact:** Medium | **Dependencies:** 2.1, 4.1, 4.2

**Create Export Service** (1 week)
- File: `app/Services/EzEstimateExportService.php`
- Load template
- Populate sheets with order data
- Apply Excel formulas
- Generate downloadable file

**Integration** (2 days)
- Add "Generate EZ Estimate" button to Tubelite tab in replenishment page
- Download endpoint
- Progress indicator

**Testing** (1 day)

---

## Phase 5: Admin Utilities (LOW-MEDIUM PRIORITY)

### Phase 5.1: Database Health Dashboard

**Effort:** Low (2-3 days) | **Impact:** Low

- Check DB connection
- Table counts
- Orphaned records
- Foreign key validation
- Index usage statistics

---

### Phase 5.2: Metrics Editor

**Effort:** Low (2-3 days) | **Impact:** Low

**Table: `dashboard_metrics`**
```sql
- id, label, value, delta, timeframe
- accent (color), sort_order, is_active
```

- CRUD interface
- Custom metric definitions
- Dashboard integration

---

### Phase 5.3: Inventory Reconciliation Tool

**Effort:** Medium (1 week) | **Impact:** Medium | **Dependencies:** 1.1

- Compare inventory_locations vs actual
- Find discrepancies
- Bulk update capabilities
- Variance reports

---

### Phase 5.4: Import Utilities

**Effort:** Medium (1 week) | **Impact:** Medium

- Generic import service
- Support: Products, Categories, Suppliers, Locations, POs
- Validation and error reporting
- Import history tracking
- Wizard UI

---

### Phase 5.5: Job Reservation PDF Export

**Effort:** Low (2-3 days) | **Impact:** Low | **Dependencies:** 2.2

- PDF template for reservations
- Pick list format
- Print functionality

---

## Implementation Timeline

### Sprint 1-2 (Weeks 1-2): Foundation
- ✅ Phase 1.1: Hierarchical Storage Locations (COMPLETE)
- Phase 1.2: Inventory Systems
- Phase 1.3: Daily Usage Tracking

### Sprint 3-4 (Weeks 3-4): Replenishment
- Phase 2.1: Replenishment Calculator
- Phase 2.2: PO PDF Generation

### Sprint 5-8 (Weeks 5-8): Door Configurator
- Phase 3.1: Data Models (Weeks 5-6)
- Phase 3.2: Business Logic (Weeks 6-7)
- Phase 3.3: Frontend UI (Weeks 7-8)

### Sprint 9-12 (Weeks 9-12): EZ Estimate
- Phase 4.1: Excel Parsing (Week 9)
- Phase 4.2: Template Management (Week 9-10)
- Phase 4.3: Validation (Week 10-11)
- Phase 4.4: Export (Week 11-12)

### Sprint 13-14 (Weeks 13-14): Admin Tools
- Phase 5.1-5.5: All admin utilities

---

## Critical Success Factors

1. **Data Integrity**: Ensure migrations preserve existing data
2. **Backward Compatibility**: Support legacy string-based locations during transition
3. **Testing**: Comprehensive testing at each phase
4. **Documentation**: Keep docs updated
5. **User Training**: Document new features

---

## Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| Data loss during migration | Backup before migrations, test in dev first |
| Complex configurator logic | Break into smaller services, extensive testing |
| Excel parsing errors | Validate file formats, error handling |
| Performance with large datasets | Indexing, caching, pagination |
| User adoption | Training docs, gradual rollout |

---

## Testing Strategy

**For Each Phase:**
1. Unit tests for models and services
2. Feature tests for API endpoints
3. Browser tests for UI (Laravel Dusk optional)
4. Manual testing checklist
5. User acceptance testing

---

## Deployment Strategy

1. **Development**: Implement on feature branch
2. **Staging**: Test with production-like data
3. **Production**: Deploy during low-traffic window
4. **Rollback Plan**: Database backups, git revert ready

---

## Maintenance and Support

**Post-Implementation:**
- Monitor error logs
- Gather user feedback
- Performance optimization
- Bug fixes
- Feature enhancements

---

## Appendix A: API Endpoint Summary

### Hierarchical Storage Locations (✅ Complete)
```
GET    /api/v1/storage-locations
POST   /api/v1/storage-locations
GET    /api/v1/storage-locations/{id}
PUT    /api/v1/storage-locations/{id}
DELETE /api/v1/storage-locations/{id}
GET    /api/v1/storage-locations-tree
GET    /api/v1/storage-locations/{id}/ancestors
GET    /api/v1/storage-locations/{id}/descendants
GET    /api/v1/storage-locations/{id}/statistics
POST   /api/v1/storage-locations/{id}/move
POST   /api/v1/storage-locations-parse
POST   /api/v1/storage-locations-search
POST   /api/v1/storage-locations-bulk-create
GET    /api/v1/storage-locations-reconciliation/status
GET    /api/v1/storage-locations-reconciliation/report
POST   /api/v1/storage-locations-reconciliation/migrate
POST   /api/v1/storage-locations-reconciliation/fix-orphaned
POST   /api/v1/storage-locations-reconciliation/cleanup-empty
POST   /api/v1/storage-locations-reconciliation/fix-duplicates
POST   /api/v1/storage-locations-reconciliation/sync-quantities
```

### Inventory Systems (Phase 1.2)
```
GET    /api/v1/inventory-systems
POST   /api/v1/inventory-systems
GET    /api/v1/inventory-systems/{id}
PUT    /api/v1/inventory-systems/{id}
DELETE /api/v1/inventory-systems/{id}
GET    /api/v1/inventory-systems/{id}/products
POST   /api/v1/products/{product}/systems
DELETE /api/v1/products/{product}/systems/{system}
```

### Daily Usage (Phase 1.3)
```
GET    /api/v1/products/{product}/daily-usage
GET    /api/v1/products/{product}/usage-trends
POST   /api/v1/products/{product}/log-usage
```

### Replenishment (Phase 2.1)
```
GET    /api/v1/replenishment/calculate
GET    /api/v1/replenishment/by-supplier
GET    /api/v1/replenishment/product/{id}
POST   /api/v1/replenishment/create-po
```

### Configurator (Phase 3)
```
GET    /api/v1/configurations
POST   /api/v1/configurations
GET    /api/v1/configurations/{id}
PUT    /api/v1/configurations/{id}
DELETE /api/v1/configurations/{id}
POST   /api/v1/configurations/{id}/validate
POST   /api/v1/configurations/{id}/explode-bom
GET    /api/v1/configurations/{id}/check-availability
GET    /api/v1/configurator/part-use-options
GET    /api/v1/configurator/available-parts
POST   /api/v1/configurator/calculate-dimensions
```

### EZ Estimate (Phase 4)
```
POST   /api/v1/ez-estimate/upload-template
GET    /api/v1/ez-estimate/templates
POST   /api/v1/ez-estimate/validate
GET    /api/v1/ez-estimate/validation-report
POST   /api/v1/ez-estimate/export
```

---

## Appendix B: Database Schema Summary

### Phase 1 Tables
- ✅ `storage_locations` - Hierarchical locations
- `inventory_systems` - Framing systems
- `inventory_item_systems` - Product-system pivot
- `inventory_daily_usage` - Daily consumption tracking

### Phase 3 Tables
- `configurator_part_use_options` - Part taxonomy
- `configurator_part_profiles` - Part metadata
- `configurator_part_use_links` - Part-use pivot
- `configurator_part_requirements` - BOM dependencies
- `door_configurations` - Saved configurations

### Phase 4 Tables
- `ez_estimate_templates` - Template definitions

### Phase 5 Tables
- `dashboard_metrics` - Custom metrics

---

## Appendix C: File Structure

```
ForgeDesk3/
├── laravel/
│   ├── app/
│   │   ├── Models/
│   │   │   ├── StorageLocation.php ✅
│   │   │   ├── InventorySystem.php
│   │   │   ├── InventoryDailyUsage.php
│   │   │   ├── ConfiguratorPartUseOption.php
│   │   │   ├── ConfiguratorPartProfile.php
│   │   │   ├── ConfiguratorPartRequirement.php
│   │   │   ├── DoorConfiguration.php
│   │   │   └── EzEstimateTemplate.php
│   │   ├── Http/Controllers/Api/
│   │   │   ├── StorageLocationController.php ✅
│   │   │   ├── StorageLocationReconciliationController.php ✅
│   │   │   ├── InventorySystemController.php
│   │   │   ├── ReplenishmentController.php
│   │   │   ├── ConfiguratorController.php
│   │   │   └── EzEstimateController.php
│   │   ├── Services/
│   │   │   ├── ReplenishmentService.php
│   │   │   ├── PurchaseOrderPdfService.php
│   │   │   ├── ConfiguratorService.php
│   │   │   ├── ConfiguratorValidationService.php
│   │   │   ├── ExcelParserService.php
│   │   │   ├── EzEstimateParserService.php
│   │   │   ├── EzEstimateValidationService.php
│   │   │   └── EzEstimateExportService.php
│   │   ├── Observers/
│   │   │   └── ProductTransactionObserver.php
│   │   └── Enums/
│   │       ├── PartType.php
│   │       ├── FinishPolicy.php
│   │       ├── ConfigurationScope.php
│   │       └── ConfigurationStatus.php
│   ├── database/
│   │   ├── migrations/
│   │   │   ├── 2026_01_13_000001_create_storage_locations_table.php ✅
│   │   │   ├── 2026_01_13_000002_add_storage_location_to_inventory_locations.php ✅
│   │   │   └── [15+ new migrations for phases 1-5]
│   │   └── seeders/
│   │       ├── DatabaseSeeder.php
│   │       ├── StorageLocationSeeder.php ✅
│   │       ├── InventorySystemSeeder.php
│   │       └── ConfiguratorSeeder.php
│   └── resources/
│       ├── views/
│       │   ├── storage-locations.blade.php ✅
│       │   ├── inventory-systems.blade.php
│       │   ├── replenishment.blade.php
│       │   ├── configurator.blade.php
│       │   ├── admin/
│       │   │   ├── estimate-check.blade.php
│       │   │   └── estimate-template.blade.php
│       │   └── pdf/
│       │       ├── purchase-order.blade.php
│       │       └── job-reservation.blade.php
│       └── js/
│           ├── configurator.js
│           └── replenishment.js
├── HIERARCHICAL_STORAGE_LOCATIONS.md ✅
└── FORGEDESK2_TO_FORGEDESK3_IMPLEMENTATION_PLAN.md (this file)
```

---

**Document Version:** 1.0
**Last Updated:** 2026-01-13
**Status:** Phase 1.1 Complete ✅
**Next Phase:** Phase 1.2 - Inventory Systems
