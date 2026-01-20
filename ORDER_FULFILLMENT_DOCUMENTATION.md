# ForgeDesk2 Order Fulfillment Process Documentation

**Repository:** Jkweks/ForgeDesk2
**Analysis Date:** January 20, 2026
**Purpose:** Documentation of EZ Estimate and Job Reservations workflow for implementation planning

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [System Architecture](#system-architecture)
3. [EZ Estimate Workflow](#ez-estimate-workflow)
4. [Job Reservations Workflow](#job-reservations-workflow)
5. [Database Schema](#database-schema)
6. [End-to-End Process Flow](#end-to-end-process-flow)
7. [Current Strengths](#current-strengths)
8. [Identified Issues & Limitations](#identified-issues--limitations)
9. [Recommended Improvements](#recommended-improvements)
10. [Implementation Priorities](#implementation-priorities)

---

## Executive Summary

ForgeDesk2 implements a comprehensive order fulfillment system that manages the lifecycle from job estimation through inventory consumption. The system consists of two primary components:

1. **EZ Estimate System**: Converts Excel-based bills of materials into analyzable data
2. **Job Reservations System**: Manages inventory commitments and consumption tracking

**Key Metrics:**
- **Database Tables:** 10+ core tables
- **Service Files:** 929 lines (reservation_service.php alone)
- **UI Controllers:** 952 lines (job-reservations.php)
- **Status States:** 6 distinct reservation statuses

**Technology Stack:**
- Backend: PHP 7.4+ (strict types)
- Database: PostgreSQL with custom views and triggers
- Frontend: Blade templates with vanilla JavaScript
- File Processing: PhpSpreadsheet for Excel parsing

---

## System Architecture

### Core Components

```
┌─────────────────────────────────────────────────────────────┐
│                    User Interface Layer                     │
├─────────────────────────────────────────────────────────────┤
│  estimate-check.php  │  job-reservations.php               │
│  estimate-upload.php │  ez-estimate-template.php           │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                    Service Layer                            │
├─────────────────────────────────────────────────────────────┤
│  estimate_check.php       │  reservation_service.php        │
│  estimate_report.php      │  ez_estimate_templates.php      │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                    Data Layer                               │
├─────────────────────────────────────────────────────────────┤
│  inventory.php (queries)  │  estimate_uploads.php (files)   │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                    Database Layer                           │
├─────────────────────────────────────────────────────────────┤
│  PostgreSQL: inventory_items, job_reservations,             │
│  job_reservation_items, inventory_item_commitments (view)   │
└─────────────────────────────────────────────────────────────┘
```

### File Structure

```
ForgeDesk2/
├── app/
│   ├── services/
│   │   ├── reservation_service.php      (929 lines - core business logic)
│   │   ├── estimate_check.php           (Excel parsing & analysis)
│   │   ├── estimate_report.php          (HTML/PDF generation)
│   │   └── ez_estimate_templates.php    (Template management)
│   ├── data/
│   │   └── inventory.php                (Inventory queries & utilities)
│   └── helpers/
│       ├── estimate_uploads.php         (Chunked file upload)
│       └── xlsx.php                     (PhpSpreadsheet wrapper)
├── public/admin/
│   ├── estimate-check.php               (EZ Estimate UI)
│   ├── estimate-upload.php              (Upload endpoint)
│   ├── job-reservations.php             (Reservations UI - 952 lines)
│   └── ez-estimate-template.php         (Template uploader)
└── database/
    ├── init.sql                         (Schema definition - 276 lines)
    └── migrations/
        └── 202409010001_create_job_reservations.sql
```

---

## EZ Estimate Workflow

### Purpose

Convert Excel-based job estimates (bill of materials) into validated inventory requirements and create job reservations.

### Excel Template Structure

**Default Template:** `/app/helpers/EZ_Estimate.xlsm`

**Expected Sheets:**
1. **Accessories** - Hardware, connectors, mounting components
2. **Stock Lengths** - Standard length materials (pipes, channels, extrusions)
3. **Special Length** - Custom-cut materials
4. **MULTIPLIERS** (optional) - Global quantity multipliers (rows 4-12, columns C-D)

**Sheet Header Detection:**
- Scans first 60 rows to locate header row
- Required columns: `qty` or `quantity` + `part` or `part_number`
- Optional columns: `finish`, `color` (treated as finish), `description`
- Case-insensitive, flexible matching (e.g., "QTY", "Qty.", "Part #")

### Upload Process

**Location:** `/public/admin/estimate-check.php`

1. **File Upload** (`estimate-upload.php`)
   - Supports chunked uploads for large files (>10MB)
   - Stores in temp directory: `/storage/estimate-uploads/temp/{upload_id}/`
   - Validates file extensions: `.xlsm`, `.xlsx`
   - Tracks upload progress via metadata JSON

2. **Analysis** (`analyzeEstimateRequirements()`)
   ```php
   function analyzeEstimateRequirements(\PDO $db, string $filePath): array
   ```

   **Process:**
   - Load workbook using PhpSpreadsheet
   - Identify data sheets (Accessories, Stock Lengths, Special Length)
   - For each sheet:
     - Find header row (first 60 rows)
     - Extract rows with `qty > 0`
     - Normalize part numbers (uppercase, trim)
     - Stop after 6 consecutive empty rows
   - Join against `inventory_items` table:
     - Match: `part_number` + `finish` (if provided)
     - Case-insensitive comparison
   - Categorize each item:
     - **Available:** `stock >= required_qty AND (stock - committed_qty) >= required_qty`
     - **Short:** In inventory but insufficient available stock
     - **Missing:** Not found in database

   **Returns:**
   ```php
   [
     'items' => [
       [
         'sheet' => 'Accessories',
         'row' => 15,
         'qty' => 24,
         'part' => 'ABC-123',
         'finish' => 'BL',
         'description' => '...',
         'inventory_match' => [...],  // NULL if missing
         'status' => 'available|short|missing',
         'available_stock' => 100,
         'committed_stock' => 20,
         'shortage' => 0
       ],
       ...
     ],
     'messages' => [
       ['type' => 'warning', 'text' => '5 items not found in inventory'],
       ...
     ],
     'counts' => [
       'total' => 47,
       'available' => 38,
       'short' => 4,
       'missing' => 5
     ],
     'log' => [
       ['at' => 0.152, 'message' => 'Loaded 3 sheets'],
       ...
     ]
   ]
   ```

3. **User Review**
   - Items displayed in color-coded groups:
     - 🟢 Green: Available
     - 🟡 Yellow: Short
     - 🔴 Red: Missing
   - User selects items to include in reservation
   - Can adjust requested/committed quantities
   - Missing items can be manually mapped to inventory

### Template Management

**Location:** `/app/services/ez_estimate_templates.php`

**Functions:**
1. `ezEstimateActiveTemplatePath()` - Returns current template path
2. `ezEstimateLoadMultipliers()` - Reads MULTIPLIERS sheet (rows 4-12)
3. `ezEstimateStoreTemplateUpload()` - Saves user-uploaded template
4. `ezEstimateUpdateMultipliers()` - Updates template using ZipArchive (Excel = ZIP of XML files)

**Multipliers:**
- Stored in MULTIPLIERS sheet
- Row range: 4-12 (9 multipliers max)
- Column C: Label (e.g., "Safety Factor")
- Column D: Value (numeric, e.g., 1.15)
- Applied globally to all quantity calculations

---

## Job Reservations Workflow

### Purpose

Manage inventory commitments from job start through completion, preventing overselling and providing audit trails.

### Status State Machine

```
┌─────────┐
│  DRAFT  │  (Initial creation, not yet committed)
└────┬────┘
     │
     ↓
┌──────────┐
│  ACTIVE  │  (Inventory committed, ready to pull)
└────┬─────┘
     │
     ├─────→ ┌──────────┐
     │       │ ON HOLD  │  (Temporarily paused)
     │       └─────┬────┘
     │             │
     │             ↓
     ↓       (can resume)
┌──────────────┐
│ IN PROGRESS  │  (Work started, consuming inventory)
└──────┬───────┘
       │
       ↓
┌────────────┐
│ FULFILLED  │  (Complete, inventory reconciled)
└────────────┘

       OR

       ↓
┌───────────┐
│ CANCELLED │  (Abandoned, inventory released)
└───────────┘
```

**Status Definitions:**

| Status | Order | Description | Inventory Committed? |
|--------|-------|-------------|---------------------|
| draft | 0 | Details being gathered | ❌ No |
| active | 1 | Ready for fulfillment | ✅ Yes |
| on_hold | 1 | Temporarily paused | ✅ Yes |
| in_progress | 2 | Actively consuming | ✅ Yes |
| fulfilled | 3 | Complete | ❌ No (consumed) |
| cancelled | 99 | Abandoned | ❌ No |

### Core Operations

**Location:** `/app/services/reservation_service.php`

#### 1. Create Reservation (`reservationCommitItems()`)

**Function Signature:**
```php
function reservationCommitItems(
    \PDO $db,
    string $jobNumber,
    int $releaseNumber,
    array $jobMetadata,
    array $linesToCommit
): array
```

**Parameters:**
- `$jobNumber`: Unique job identifier (e.g., "JOB-2024-001")
- `$releaseNumber`: Version number (default: 1)
- `$jobMetadata`:
  ```php
  [
    'job_name' => 'Customer Name - Project Description',
    'requested_by' => 'John Smith',
    'needed_by' => '2026-02-15',  // Optional, YYYY-MM-DD
    'notes' => 'Special instructions...'
  ]
  ```
- `$linesToCommit`:
  ```php
  [
    [
      'inventory_item_id' => 123,
      'requested_qty' => 50,
      'committed_qty' => 48  // May differ if allowing partial
    ],
    ...
  ]
  ```

**Process:**
1. Validate metadata (job_name, requested_by required)
2. Check for duplicate job_number + release_number
3. Begin transaction with row-level locking:
   ```sql
   SELECT * FROM inventory_items WHERE id IN (...) FOR UPDATE
   ```
4. For each line item:
   - Calculate `available_before = stock - committed_qty`
   - Validate `committed_qty <= available_before`
   - Insert into `job_reservation_items`
5. Create `job_reservations` header with status='active'
6. Commit transaction

**Returns:**
```php
[
  'reservation_id' => 42,
  'job_number' => 'JOB-2024-001',
  'release_number' => 1,
  'committed_items' => [
    [
      'inventory_item_id' => 123,
      'item_name' => 'Aluminum Channel 1x1x.125',
      'part_number' => 'AC-101',
      'requested_qty' => 50,
      'committed_qty' => 48,
      'available_before' => 100,
      'available_after' => 52
    ],
    ...
  ]
]
```

#### 2. Update Reservation (`reservationUpdateItems()`)

**Function Signature:**
```php
function reservationUpdateItems(
    \PDO $db,
    int $reservationId,
    array $jobMetadata,
    array $existingLines,
    array $newLines
): array
```

**Capabilities:**
- Update job metadata (job_name, requested_by, needed_by, notes)
- Modify quantities on existing lines
- Add new inventory items to reservation
- **Cannot reduce committed_qty below consumed_qty**

**Validations:**
1. Reservation must exist and not be fulfilled/cancelled
2. Cannot set `committed_qty < consumed_qty`
3. Cannot exceed available inventory when increasing commitments
4. Uses row-level locking to prevent race conditions

**Returns:**
```php
[
  'reservation_id' => 42,
  'job_number' => 'JOB-2024-001',
  'release_number' => 1,
  'updated' => 3,      // Lines modified
  'added' => 2,        // Lines added
  'committed' => 45,   // Net qty committed
  'released' => 10     // Net qty released
]
```

#### 3. Change Status (`reservationUpdateStatus()`)

**Function Signature:**
```php
function reservationUpdateStatus(
    \PDO $db,
    int $reservationId,
    string $newStatus
): array
```

**Supported Transitions:**
- `active` → `in_progress`
- `on_hold` → `in_progress`
- Any → `cancelled` (releases all inventory)

**Validation (active → in_progress):**
```php
// Warns if insufficient stock, but allows transition
if ($item['stock'] < $item['committed_qty']) {
    $warnings[] = sprintf(
        'Item %s has committed %d but only %d on hand',
        $item['part_number'],
        $item['committed_qty'],
        $item['stock']
    );
}
```

**Returns:**
```php
[
  'reservation_id' => 42,
  'old_status' => 'active',
  'new_status' => 'in_progress',
  'warnings' => [...]  // If inventory issues detected
]
```

#### 4. Complete Reservation (`reservationComplete()`)

**Function Signature:**
```php
function reservationComplete(
    \PDO $db,
    int $reservationId,
    array $actualQuantities
): array
```

**Parameters:**
- `$actualQuantities`: Map of inventory_item_id → actual consumed quantity
  ```php
  [
    123 => 45,  // Actually consumed 45 (committed was 48)
    124 => 30,  // Actually consumed 30 (committed was 30)
    ...
  ]
  ```

**Process:**
1. Verify reservation status is 'in_progress'
2. Begin transaction
3. For each line item:
   - Calculate `consumed_delta = actual_qty - previously_consumed_qty`
   - Calculate `released = committed_qty - actual_qty`
   - Create `inventory_transaction_line` for audit
   - Update `inventory_items.stock -= consumed_delta`
   - Update `job_reservation_items.consumed_qty = actual_qty`
4. Update `job_reservations.status = 'fulfilled'`
5. Reset all `job_reservation_items.committed_qty = 0`
6. Create parent `inventory_transactions` record
7. Commit transaction

**Inventory Transaction Created:**
```sql
INSERT INTO inventory_transactions
(transaction_type, reference_type, reference_id, notes, created_at)
VALUES
('fulfillment', 'job_reservation', 42, 'Job JOB-2024-001 (Release 1)', NOW())
```

**Returns:**
```php
[
  'reservation_id' => 42,
  'transaction_id' => 89,
  'total_consumed' => 145,
  'total_released' => 8,
  'lines_processed' => 12,
  'items' => [
    [
      'inventory_item_id' => 123,
      'consumed' => 45,
      'released' => 3
    ],
    ...
  ]
]
```

#### 5. List Reservations (`reservationList()`)

**Function Signature:**
```php
function reservationList(\PDO $db, ?array $filters = null): array
```

**Filters:**
```php
[
  'status' => 'active',           // Filter by status
  'job_number' => 'JOB-2024-',    // Partial match
  'requested_by' => 'John Smith'  // Exact match
]
```

**Returns:**
```php
[
  [
    'id' => 42,
    'job_number' => 'JOB-2024-001',
    'release_number' => 1,
    'job_name' => 'ACME Corp - Widget Assembly',
    'requested_by' => 'John Smith',
    'needed_by' => '2026-02-15',
    'status' => 'in_progress',
    'created_at' => '2026-01-15 10:30:00',
    'updated_at' => '2026-01-18 14:22:00',
    'line_count' => 12,
    'requested_qty_total' => 500,
    'committed_qty_total' => 485,
    'consumed_qty_total' => 120
  ],
  ...
]
```

**Sort Order:**
1. Unfulfilled reservations first (status != 'fulfilled')
2. By creation date (newest first)

#### 6. Get Reservation Detail (`reservationFetch()`)

**Function Signature:**
```php
function reservationFetch(\PDO $db, int $reservationId): ?array
```

**Returns:**
```php
[
  'header' => [
    'id' => 42,
    'job_number' => 'JOB-2024-001',
    'release_number' => 1,
    'job_name' => 'ACME Corp - Widget Assembly',
    'requested_by' => 'John Smith',
    'needed_by' => '2026-02-15',
    'status' => 'in_progress',
    'notes' => 'Rush order - prioritize',
    'created_at' => '2026-01-15 10:30:00',
    'updated_at' => '2026-01-18 14:22:00'
  ],
  'lines' => [
    [
      'id' => 567,  // reservation_item_id
      'inventory_item_id' => 123,
      'item' => 'Aluminum Channel 1x1x.125',
      'sku' => 'AC-101-BL',
      'part_number' => 'AC-101',
      'finish' => 'BL',
      'location' => 'A-12-3',
      'requested_qty' => 50,
      'committed_qty' => 48,
      'consumed_qty' => 12,
      'inventory_stock' => 100,
      'inventory_committed_total' => 78,  // Across ALL reservations
      'inventory_available' => 22         // stock - committed_total
    ],
    ...
  ]
]
```

---

## Database Schema

### Core Tables

#### 1. `inventory_items`

Primary inventory master table.

```sql
CREATE TABLE inventory_items (
    id SERIAL PRIMARY KEY,
    item TEXT NOT NULL,                      -- Display name
    sku TEXT NOT NULL UNIQUE,                -- Stock Keeping Unit
    part_number TEXT NOT NULL DEFAULT '',   -- Manufacturer part number
    finish TEXT NULL,                        -- Color/finish code (BL, C2, DB, OR)
    location TEXT NOT NULL,                  -- Warehouse location (e.g., "A-12-3")
    stock INTEGER NOT NULL DEFAULT 0,        -- On-hand quantity
    committed_qty INTEGER NOT NULL DEFAULT 0,-- Reserved for jobs
    status TEXT NOT NULL DEFAULT 'In Stock', -- 'In Stock', 'Low Stock', 'Out of Stock'
    supplier TEXT NOT NULL DEFAULT 'Unknown Supplier',
    supplier_contact TEXT NULL,
    reorder_point INTEGER NOT NULL DEFAULT 0,
    lead_time_days INTEGER NOT NULL DEFAULT 0,
    average_daily_use NUMERIC(12,4) NULL    -- Calculated from consumption history
);

CREATE INDEX idx_inventory_items_part_number ON inventory_items(part_number);
CREATE INDEX idx_inventory_items_sku ON inventory_items(sku);
```

**Key Calculation:**
```
available_qty = stock + on_order - committed_qty
```

#### 2. `job_reservations`

Reservation header records.

```sql
CREATE TYPE job_reservation_status AS ENUM (
    'draft', 'committed', 'active', 'on_hold',
    'in_progress', 'fulfilled', 'cancelled'
);

CREATE TABLE job_reservations (
    id SERIAL PRIMARY KEY,
    job_number TEXT NOT NULL,
    release_number INTEGER NOT NULL DEFAULT 1,
    job_name TEXT NOT NULL,
    requested_by TEXT NOT NULL,
    needed_by DATE NULL,
    status job_reservation_status NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (job_number, release_number)
);

CREATE INDEX idx_job_reservations_status ON job_reservations(status);
CREATE INDEX idx_job_reservations_job_number ON job_reservations(job_number);
```

**Trigger:**
```sql
CREATE TRIGGER trg_job_reservations_updated_at
    BEFORE UPDATE ON job_reservations
    FOR EACH ROW
    EXECUTE FUNCTION set_updated_at_timestamp();
```

#### 3. `job_reservation_items`

Line items within a reservation.

```sql
CREATE TABLE job_reservation_items (
    id SERIAL PRIMARY KEY,
    reservation_id INTEGER NOT NULL
        REFERENCES job_reservations(id) ON DELETE CASCADE,
    inventory_item_id INTEGER NOT NULL
        REFERENCES inventory_items(id) ON DELETE RESTRICT,
    requested_qty INTEGER NOT NULL DEFAULT 0,   -- Initial request
    committed_qty INTEGER NOT NULL DEFAULT 0,   -- Reserved amount
    consumed_qty INTEGER NOT NULL DEFAULT 0,    -- Actually used
    UNIQUE (reservation_id, inventory_item_id)
);

CREATE INDEX idx_job_reservation_items_reservation
    ON job_reservation_items(reservation_id);
CREATE INDEX idx_job_reservation_items_inventory
    ON job_reservation_items(inventory_item_id);
```

**Business Rules:**
- `consumed_qty <= committed_qty`
- `committed_qty <= requested_qty` (generally, but can be adjusted)
- One line per inventory item per reservation

#### 4. `inventory_item_commitments` (View)

Aggregates committed quantities across active reservations.

```sql
CREATE OR REPLACE VIEW inventory_item_commitments AS
SELECT
    i.id AS inventory_item_id,
    COALESCE(
        SUM(
            CASE
                WHEN jr.status IN ('active', 'committed', 'in_progress', 'on_hold')
                    THEN jri.committed_qty
                ELSE 0
            END
        ),
        0
    ) AS committed_qty
FROM inventory_items i
LEFT JOIN job_reservation_items jri
    ON jri.inventory_item_id = i.id
LEFT JOIN job_reservations jr
    ON jr.id = jri.reservation_id
GROUP BY i.id;
```

**Usage:**
```php
$commitments = inventoryCommittedTotals($db, [123, 124, 125]);
// Returns: [123 => 48, 124 => 30, 125 => 0]
```

#### 5. `inventory_transactions`

Audit trail for inventory movements.

```sql
CREATE TABLE inventory_transactions (
    id SERIAL PRIMARY KEY,
    transaction_type TEXT NOT NULL,  -- 'fulfillment', 'adjustment', 'receiving'
    reference_type TEXT NULL,        -- 'job_reservation', 'purchase_order'
    reference_id INTEGER NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by TEXT NULL
);

CREATE TABLE inventory_transaction_lines (
    id SERIAL PRIMARY KEY,
    transaction_id INTEGER NOT NULL
        REFERENCES inventory_transactions(id) ON DELETE CASCADE,
    inventory_item_id INTEGER NOT NULL
        REFERENCES inventory_items(id) ON DELETE RESTRICT,
    quantity_change INTEGER NOT NULL,  -- Positive = increase, negative = decrease
    unit_cost NUMERIC(12,2) NULL,
    notes TEXT NULL
);
```

**Example Transaction (Fulfillment):**
```
inventory_transactions:
  id: 89
  transaction_type: 'fulfillment'
  reference_type: 'job_reservation'
  reference_id: 42
  notes: 'Job JOB-2024-001 (Release 1) completed'

inventory_transaction_lines:
  transaction_id: 89, inventory_item_id: 123, quantity_change: -45
  transaction_id: 89, inventory_item_id: 124, quantity_change: -30
  transaction_id: 89, inventory_item_id: 125, quantity_change: -18
```

### Schema Relationships

```
inventory_items (1) ←──── (∞) job_reservation_items
                                      │
                                      │ (∞)
                                      ↓
                                      (1) job_reservations

inventory_items (1) ←──── (∞) inventory_transaction_lines
                                      │
                                      │ (∞)
                                      ↓
                                      (1) inventory_transactions
```

---

## End-to-End Process Flow

### Complete Workflow Example

**Scenario:** Customer orders 50 units of custom product requiring 12 unique parts

#### Phase 1: EZ Estimate Upload (5-10 minutes)

1. **Estimator creates Excel BOM**
   - Fills out Accessories sheet with hardware
   - Fills out Stock Lengths sheet with standard materials
   - Includes quantities, part numbers, finishes

2. **Upload to ForgeDesk2**
   - Navigate to `/admin/estimate-check.php`
   - Drag & drop Excel file or click to browse
   - Chunked upload if file >10MB (handles progress)

3. **System analyzes requirements**
   ```
   Processing: EZ_Estimate_Project_ABC.xlsm
   ├── Reading sheet: Accessories (28 items)
   ├── Reading sheet: Stock Lengths (15 items)
   ├── Reading sheet: Special Length (4 items)
   ├── Matching against inventory database...
   └── Analysis complete (2.3 seconds)
   ```

4. **Review analysis results**
   ```
   Total Items: 47
   ✅ Available: 38 items (sufficient stock)
   ⚠️  Short: 4 items (insufficient stock)
   ❌ Missing: 5 items (not in database)
   ```

5. **User actions:**
   - Review short items (decide: partial commit, wait for restock, or substitute)
   - Contact purchasing for missing items
   - Adjust committed quantities if needed
   - Select items to include in reservation

#### Phase 2: Create Job Reservation (2-5 minutes)

1. **Fill metadata form:**
   ```
   Job Number: JOB-2024-001
   Release Number: 1
   Job Name: ACME Corp - Custom Widget Assembly
   Requested By: John Smith
   Needed By: 2026-02-15
   Notes: Rush order - customer needs by Feb 15
   ```

2. **Select line items:**
   - Check boxes for all available items (38)
   - Check boxes for short items with partial quantities (4)
   - Uncheck missing items (5)
   - Total committed: 42 items

3. **Submit reservation:**
   - Click "Create Reservation"
   - System validates inventory availability
   - Creates job_reservations record (status='active')
   - Creates 42 job_reservation_items records
   - Updates inventory_items.committed_qty

4. **Confirmation:**
   ```
   ✅ Reservation JOB-2024-001 (Release 1) created successfully

   Committed: 42 items totaling 487 units
   Released due to shortage: 5 items totaling 28 units

   Reservation ID: 42
   Status: Active (ready to pull)
   ```

#### Phase 3: Inventory Pull (1-2 days)

1. **Warehouse team reviews reservation**
   - Navigate to `/admin/job-reservations.php`
   - Filter by status='active'
   - Click on JOB-2024-001

2. **View pull list:**
   ```
   Items to Pull (42):

   1. Aluminum Channel 1x1x.125 (AC-101, Finish: BL)
      Location: A-12-3
      Committed: 48 units
      Current Stock: 100 units

   2. Corner Bracket (CB-200, Finish: C2)
      Location: B-05-7
      Committed: 96 units
      Current Stock: 250 units

   [... 40 more items ...]
   ```

3. **Physical pull:**
   - Warehouse team physically picks items
   - Moves to staging area labeled "JOB-2024-001"
   - Verifies quantities

4. **Update status:**
   - Click "Start Work" button
   - Status changes: active → in_progress
   - System validates sufficient stock (warns if issues)

#### Phase 4: Production (1-3 weeks)

1. **Work team consumes inventory**
   - Assembles products using staged materials
   - May use less than committed (e.g., efficiency gains)
   - May need to request more (creates updated reservation)

2. **Track progress:**
   - Reservation remains status='in_progress'
   - Inventory committed_qty stays reserved
   - Other jobs cannot access these units

#### Phase 5: Completion & Reconciliation (30 minutes)

1. **Work complete, count actual usage**
   ```
   Actual Consumption:
   - Aluminum Channel: 45 units (committed 48, saved 3)
   - Corner Bracket: 96 units (committed 96, exact)
   - Steel Plate: 18 units (committed 20, saved 2)
   [... 39 more items ...]

   Total committed: 487 units
   Total consumed: 472 units
   Total saved: 15 units (returned to stock)
   ```

2. **Navigate to completion page:**
   - `/admin/job-reservations.php?complete=42`
   - Form pre-filled with committed quantities
   - Adjust to actual consumed quantities

3. **Submit completion:**
   - Click "Complete Reservation"
   - System creates inventory transaction:
     ```
     Transaction #89: Fulfillment
     Reference: Job Reservation #42 (JOB-2024-001, Release 1)
     Date: 2026-02-08 16:45:00

     Lines:
     - Aluminum Channel (AC-101): -45 units
     - Corner Bracket (CB-200): -96 units
     - Steel Plate (SP-300): -18 units
     [... 39 more lines ...]

     Total inventory decreased: 472 units
     ```
   - Updates inventory_items.stock for each item
   - Sets job_reservation_items.consumed_qty
   - Resets job_reservation_items.committed_qty to 0
   - Changes status: in_progress → fulfilled

4. **Confirmation:**
   ```
   ✅ Reservation JOB-2024-001 completed successfully

   Transaction ID: 89
   Total consumed: 472 units
   Total released back: 15 units
   Inventory updated: 42 items
   ```

#### Phase 6: Reporting & Analysis (ongoing)

1. **Inventory accuracy:**
   - Real-time stock levels updated
   - Committed quantities released
   - Available inventory increased by 15 units

2. **Job history:**
   - Reservation record preserved (status='fulfilled')
   - Full audit trail via inventory_transactions
   - Can view exactly what was consumed

3. **Analytics:**
   - Average consumption per job type
   - Accuracy of estimates (committed vs. consumed)
   - Lead times from reservation to completion

---

## Current Strengths

### 1. **Robust Inventory Tracking**
- Real-time calculation of available inventory
- Prevents overselling through committed quantity tracking
- View-based aggregation for performance

### 2. **Comprehensive Audit Trail**
- All inventory movements logged in transactions table
- Transaction lines provide item-level detail
- Reference back to originating reservations

### 3. **Flexible Excel Integration**
- Supports multiple Excel formats (.xlsm, .xlsx)
- Flexible sheet naming (Accessories, Accessories (2), etc.)
- Dynamic header detection (first 60 rows)
- Handles large files via chunked upload

### 4. **Status-Based Workflow**
- Clear state transitions (active → in_progress → fulfilled)
- Prevents editing of completed reservations
- On-hold status for temporary pauses

### 5. **Multi-Release Support**
- Same job number can have multiple releases
- Allows iterative refinement of estimates
- Unique constraint on (job_number, release_number)

### 6. **Quantity Reconciliation**
- Tracks requested vs. committed vs. consumed
- Returns unused inventory to stock
- Identifies estimation accuracy

### 7. **Database Integrity**
- Foreign key constraints prevent orphaned records
- Unique constraints prevent duplicates
- Row-level locking prevents race conditions
- Cascading deletes for cleanup

### 8. **Type Safety**
- PHP strict types throughout
- PostgreSQL ENUM for status
- Comprehensive phpDoc annotations

---

## Identified Issues & Limitations

### 1. **Limited Status Transitions**

**Issue:** Only `active → in_progress` transition is explicitly coded

**File:** `reservation_service.php:reservationUpdateStatus()`

**Impact:**
- Cannot move back from in_progress to active (if work paused)
- Cannot transition to on_hold programmatically
- Manual database updates required for some workflows

**Example Missing Flows:**
```
❌ in_progress → on_hold (customer delay)
❌ on_hold → active (ready to resume)
❌ active → draft (need to revise)
```

### 2. **No Partial Fulfillment**

**Issue:** Must complete entire reservation at once

**Impact:**
- Cannot mark individual line items as complete
- All-or-nothing completion creates delays
- Staging area ties up inventory longer

**Example Scenario:**
```
Reservation has 50 items:
- 45 items assembled and ready to ship
- 5 items delayed (waiting on paint)

Current: Must wait for all 50 before completing
Desired: Ship 45 now, complete remainder later
```

### 3. **Inventory Shortage Handling**

**Issue:** Warnings generated but transition allowed

**File:** `reservation_service.php:572-589`

```php
// Warns if insufficient stock, but allows transition
if ($item['stock'] < $item['committed_qty']) {
    $warnings[] = '...';
}
// Status change still proceeds
```

**Impact:**
- Can start work without sufficient materials
- Leads to production delays
- Committed quantity may exceed physical stock

**Recommendation:**
- Add configuration option: strict vs. lenient mode
- Strict mode: block transition if shortages exist
- Lenient mode: allow with warnings (current behavior)

### 4. **No Substitution Support**

**Issue:** Cannot swap alternative parts mid-reservation

**Example Scenario:**
```
Original: Committed 50x Steel Plate (SP-300)
Shortage: Only 30 available

Alternative: Steel Plate (SP-301) has 200 available
Current: Must wait for SP-300 or cancel reservation
Desired: Swap to SP-301 and continue
```

**Workaround:**
- Edit reservation, remove SP-300 line
- Add new line with SP-301
- Cumbersome and error-prone

### 5. **Limited Concurrency Control**

**Issue:** Row-level locking only during transaction execution

**File:** `reservation_service.php:277-282`

```php
$stmt = $db->prepare(
    'SELECT * FROM inventory_items WHERE id = ANY(:ids) FOR UPDATE'
);
```

**Gap:**
- Lock released immediately after transaction commits
- Another user can modify inventory before page refreshes
- Potential race condition in high-concurrency environments

**Recommendation:**
- Implement optimistic locking (version column)
- Add last_modified timestamp check
- Return conflict error if inventory changed during user's edit

### 6. **Missing Notifications**

**Issue:** No email/alert system for key events

**Missing Notifications:**
- Reservation created (notify warehouse)
- Status changed to in_progress (notify production manager)
- Inventory shortage detected (notify purchasing)
- Reservation completed (notify accounting)

**Current Workaround:**
- Manual communication via email/chat
- Users must check system periodically

### 7. **No Inventory Reservation Prioritization**

**Issue:** First-come, first-served commitment

**Scenario:**
```
Stock available: 50 units of Part X

Reservation A: Rush order (needed tomorrow) - requests 30
Reservation B: Standard order (needed next week) - requests 40

If B created first, B gets 40, A gets only 10
```

**Desired:**
- Priority field (1-5, where 1 = highest)
- Based on: needed_by date, customer tier, job type
- Purchasing prioritizes replenishment accordingly

### 8. **Excel Template Multipliers Not Applied**

**Issue:** Multipliers read but not applied to quantities

**File:** `ez_estimate_templates.php:ezEstimateLoadMultipliers()`

**Current Behavior:**
- Reads multipliers from MULTIPLIERS sheet
- Returns array to UI
- User must manually apply

**Expected Behavior:**
- Automatically apply multipliers to quantities
- E.g., Safety Factor 1.15 → 50 units becomes 58 units
- Option to view pre/post multiplier quantities

### 9. **Limited Reporting**

**Current Reports:**
- EZ Estimate comparison (HTML/PDF)
- Single reservation detail view

**Missing Reports:**
- Inventory consumption by job type
- Estimation accuracy (committed vs. consumed)
- Average lead times by job
- Inventory turnover rates
- Top 10 most consumed items
- Shortages forecast (based on active reservations)

### 10. **No Cycle Count Integration with Reservations**

**Issue:** Cycle counts and reservations are separate systems

**File:** Schema includes `cycle_count_sessions` and `cycle_count_lines`

**Gap:**
- Cycle count adjustments don't check active reservations
- Could adjust inventory below committed levels
- No warning if counted variance affects reservations

**Example:**
```
Item: Steel Plate (SP-300)
Stock: 100 units
Committed: 80 units (active reservations)

Cycle count finds actual: 60 units
Variance: -40 units

Adjustment reduces stock to 60, but committed=80
Result: Negative available inventory (-20)
```

### 11. **Release Number Management**

**Issue:** Release numbers are manual entry, no automation

**Potential Problems:**
- User forgets previous release number
- Duplicate release numbers if two users work simultaneously
- No link between releases (can't see what changed)

**Desired:**
- Auto-increment release number per job
- "Revise Reservation" button (creates new release as copy)
- Side-by-side comparison view of releases

### 12. **No Unit of Measure (UOM) Handling**

**Issue:** All quantities assumed to be in same unit

**Scenarios Not Handled:**
```
- Steel plate: measured in sheets
- Steel rod: measured in feet
- Bolts: measured in pieces
- Paint: measured in gallons

EZ Estimate might say "5 sheets"
but inventory tracks "60 square feet"
```

**Workaround:**
- Manual conversion required
- Error-prone

### 13. **Missing Inventory Locations Hierarchy**

**Issue:** Locations are flat text fields (e.g., "A-12-3")

**File:** `inventory_items.location` is TEXT

**Limitations:**
- Cannot filter by aisle (all "A-*")
- Cannot group by zone (all "A-**-*")
- Sorting is alphabetical, not physical layout

**Desired:**
```
Warehouse: Main
Zone: A
Aisle: 12
Shelf: 3
Bin: 2
```

### 14. **No Vendor Integration**

**Issue:** Supplier info stored but not actionable

**Fields Present:**
- `inventory_items.supplier`
- `inventory_items.supplier_contact`
- `inventory_items.reorder_point`
- `inventory_items.lead_time_days`

**Missing:**
- Purchase order generation
- Auto-reorder when below reorder_point
- Vendor catalogs / part number mapping
- Price history

### 15. **Frontend UX Issues**

**Observations:**

1. **Large Forms:**
   - Reservation editor can have 50+ line items
   - No pagination or virtual scrolling
   - Browser struggles with large DOMs

2. **No Bulk Actions:**
   - Cannot "commit all available" with one click
   - Cannot "release all" when canceling
   - Each item must be individually adjusted

3. **Limited Search/Filter:**
   - Job reservations list has no search
   - Cannot filter by date range
   - Cannot filter by requested_by

4. **No Keyboard Shortcuts:**
   - Tab navigation works but no shortcuts
   - E.g., Alt+C to commit, Alt+S to save

---

## Recommended Improvements

### Priority 1: Critical Functionality

#### 1.1 Implement Partial Fulfillment

**Goal:** Allow line-by-line completion instead of all-or-nothing

**Changes Required:**

**Database:**
```sql
ALTER TABLE job_reservation_items
ADD COLUMN fulfilled_at TIMESTAMP NULL,
ADD COLUMN fulfillment_notes TEXT NULL;

-- Add partial status
ALTER TYPE job_reservation_status ADD VALUE 'partial';
```

**Service Function:**
```php
function reservationCompleteLines(
    \PDO $db,
    int $reservationId,
    array $lineCompletions
): array {
    // $lineCompletions = [
    //   ['reservation_item_id' => 567, 'actual_qty' => 45],
    //   ['reservation_item_id' => 568, 'actual_qty' => 30],
    // ]

    // 1. Mark specified lines as consumed
    // 2. Create transaction for consumed items
    // 3. Update reservation status:
    //    - All lines fulfilled → 'fulfilled'
    //    - Some lines fulfilled → 'partial'
    //    - No lines fulfilled → 'in_progress'
}
```

**UI:**
- Checkboxes next to each line item
- "Complete Selected Items" button
- Reservation shows progress: "8 of 12 items fulfilled (67%)"

**Benefits:**
- Faster inventory turnover
- Ship partial orders sooner
- Reduce staging area congestion

---

#### 1.2 Enhance Status Transitions

**Goal:** Support full state machine with all transitions

**New Transitions:**
```php
'draft' → 'active'       // Finalize reservation
'active' → 'on_hold'     // Customer delay
'on_hold' → 'active'     // Ready to resume
'in_progress' → 'on_hold' // Work paused
'on_hold' → 'in_progress' // Work resumed
'active' → 'cancelled'   // Before work starts
'in_progress' → 'cancelled' // Mid-work cancellation (releases remaining)
```

**Validation Rules:**
```php
$transitions = [
    'draft' => ['active', 'cancelled'],
    'active' => ['in_progress', 'on_hold', 'cancelled'],
    'on_hold' => ['active', 'in_progress', 'cancelled'],
    'in_progress' => ['fulfilled', 'partial', 'on_hold', 'cancelled'],
    'partial' => ['fulfilled', 'cancelled'],
    'fulfilled' => [],  // Terminal
    'cancelled' => []   // Terminal
];
```

**Audit Log:**
```sql
CREATE TABLE job_reservation_status_log (
    id SERIAL PRIMARY KEY,
    reservation_id INTEGER NOT NULL REFERENCES job_reservations(id),
    old_status job_reservation_status NOT NULL,
    new_status job_reservation_status NOT NULL,
    reason TEXT NULL,
    changed_by TEXT NOT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

---

#### 1.3 Strict Inventory Validation Mode

**Goal:** Prevent starting work without sufficient stock

**Configuration:**
```php
// config/inventory.php
return [
    'reservation_strict_mode' => env('RESERVATION_STRICT_MODE', true),
    'allow_negative_available' => env('ALLOW_NEGATIVE_AVAILABLE', false),
];
```

**Validation in `reservationUpdateStatus()`:**
```php
if ($config['reservation_strict_mode'] && $newStatus === 'in_progress') {
    foreach ($items as $item) {
        if ($item['stock'] < $item['committed_qty']) {
            throw new InsufficientInventoryException(
                sprintf(
                    'Cannot start work: Item %s has only %d in stock but %d committed',
                    $item['part_number'],
                    $item['stock'],
                    $item['committed_qty']
                )
            );
        }
    }
}
```

**UI:**
- Configuration toggle in settings page
- Warning banner when strict mode disabled
- Pre-flight check before starting work

---

#### 1.4 Reservation Priority System

**Goal:** Prioritize critical jobs for inventory allocation

**Database:**
```sql
ALTER TABLE job_reservations
ADD COLUMN priority INTEGER NOT NULL DEFAULT 3;  -- 1=Highest, 5=Lowest

CREATE INDEX idx_job_reservations_priority ON job_reservations(priority, needed_by);

COMMENT ON COLUMN job_reservations.priority IS
'1=Critical/Rush, 2=High, 3=Normal, 4=Low, 5=Backlog';
```

**Inventory Allocation Logic:**
```php
function checkInventoryAvailabilityWithPriority(
    \PDO $db,
    int $inventoryItemId,
    int $requestedQty,
    int $priority = 3
): array {
    // 1. Get total stock
    // 2. Get committed qty from HIGHER priority reservations only
    // 3. Return available = stock - committed_higher_priority
}
```

**UI Enhancements:**
- Priority dropdown on reservation create/edit
- Filter reservations by priority
- Dashboard widget: "High Priority Reservations"
- Color-coded badges (red=1, orange=2, blue=3, gray=4/5)

---

### Priority 2: User Experience

#### 2.1 Bulk Actions

**New Features:**

1. **Commit All Available:**
   - Button on estimate-check results
   - Sets committed_qty = MIN(requested_qty, available_qty) for all items

2. **Release All:**
   - Button when canceling reservation
   - Sets committed_qty = 0 for all lines

3. **Adjust by Percentage:**
   - Input: "Reduce all commitments by 15%"
   - Useful for revising estimates downward

**UI Components:**
```html
<div class="bulk-actions">
  <button onclick="commitAllAvailable()">Commit All Available</button>
  <button onclick="commitSelected()">Commit Selected</button>
  <button onclick="adjustByPercent()">Adjust %</button>
</div>
```

---

#### 2.2 Advanced Search & Filtering

**Reservation List Filters:**

```html
<form method="GET" action="job-reservations.php">
  <input name="search" placeholder="Job number, name, or requester" />
  <select name="status">
    <option value="">All Statuses</option>
    <option value="active">Active</option>
    <option value="in_progress">In Progress</option>
    <option value="fulfilled">Fulfilled</option>
  </select>
  <input type="date" name="created_after" placeholder="Created After" />
  <input type="date" name="created_before" placeholder="Created Before" />
  <input type="date" name="needed_by_after" placeholder="Needed After" />
  <input type="date" name="needed_by_before" placeholder="Needed Before" />
  <select name="priority">
    <option value="">All Priorities</option>
    <option value="1">Critical</option>
    <option value="2">High</option>
    <option value="3">Normal</option>
  </select>
  <button type="submit">Filter</button>
</form>
```

**SQL Enhancement:**
```php
$where = ['1=1'];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "(job_number ILIKE :search OR job_name ILIKE :search OR requested_by ILIKE :search)";
    $params['search'] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['status'])) {
    $where[] = "status = :status";
    $params['status'] = $_GET['status'];
}

// ... more filters
```

---

#### 2.3 Pagination & Virtual Scrolling

**For Large Reservations (>50 items):**

**Backend:**
```php
function reservationFetchLines(
    \PDO $db,
    int $reservationId,
    int $limit = 50,
    int $offset = 0
): array {
    $stmt = $db->prepare("
        SELECT jri.*, ii.*
        FROM job_reservation_items jri
        JOIN inventory_items ii ON ii.id = jri.inventory_item_id
        WHERE jri.reservation_id = :id
        ORDER BY jri.id
        LIMIT :limit OFFSET :offset
    ");
    // ...
}
```

**Frontend:**
```javascript
// Use Intersection Observer for infinite scroll
const observer = new IntersectionObserver(entries => {
  if (entries[0].isIntersecting && !loading) {
    loadMoreLines();
  }
});
```

---

#### 2.4 Keyboard Shortcuts

**Global Shortcuts:**
- `Alt+N`: New Reservation
- `Alt+S`: Save/Submit Current Form
- `Alt+C`: Cancel/Close Modal
- `/`: Focus search box
- `Esc`: Close modal/drawer

**Reservation Edit:**
- `Tab`: Move between quantity fields
- `Ctrl+Enter`: Save and close
- `Ctrl+D`: Duplicate line item

**Implementation:**
```javascript
document.addEventListener('keydown', (e) => {
  if (e.altKey && e.key === 'n') {
    window.location = '/admin/job-reservations.php?new=1';
  }
  // ... more shortcuts
});
```

---

### Priority 3: Advanced Features

#### 3.1 Part Substitution System

**Goal:** Allow swapping alternative parts mid-reservation

**Database:**
```sql
CREATE TABLE inventory_item_substitutes (
    id SERIAL PRIMARY KEY,
    primary_item_id INTEGER NOT NULL REFERENCES inventory_items(id),
    substitute_item_id INTEGER NOT NULL REFERENCES inventory_items(id),
    substitution_ratio NUMERIC(8,4) NOT NULL DEFAULT 1.0,
    notes TEXT NULL,
    UNIQUE(primary_item_id, substitute_item_id)
);

-- Example: 1 unit of primary = 1.05 units of substitute (slightly less efficient)
```

**Service Function:**
```php
function reservationSubstituteLine(
    \PDO $db,
    int $reservationItemId,
    int $newInventoryItemId,
    float $ratio = 1.0
): array {
    // 1. Verify substitute is valid (in substitutes table)
    // 2. Release original item's committed_qty
    // 3. Commit new item with adjusted qty (original * ratio)
    // 4. Log substitution in reservation notes
}
```

**UI:**
- "Find Substitute" button next to short items
- Shows compatible alternatives with availability
- Warns if ratio != 1.0

---

#### 3.2 Email Notifications

**Events to Notify:**

| Event | Recipients | Template |
|-------|-----------|----------|
| Reservation Created | Warehouse Manager | `reservation_created.html` |
| Status → In Progress | Production Manager | `work_started.html` |
| Status → On Hold | Requester, PM | `work_paused.html` |
| Reservation Fulfilled | Requester, Accounting | `job_complete.html` |
| Inventory Shortage | Purchasing Agent | `shortage_alert.html` |
| Needed By Date Approaching | Requester, PM | `deadline_reminder.html` |

**Implementation:**

**Database:**
```sql
CREATE TABLE notification_queue (
    id SERIAL PRIMARY KEY,
    event_type TEXT NOT NULL,
    recipient_email TEXT NOT NULL,
    subject TEXT NOT NULL,
    body_html TEXT NOT NULL,
    reservation_id INTEGER NULL REFERENCES job_reservations(id),
    sent_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

**Service:**
```php
function queueNotification(
    \PDO $db,
    string $eventType,
    string $recipientEmail,
    array $data
): void {
    $template = loadTemplate($eventType);
    $subject = renderTemplate($template['subject'], $data);
    $body = renderTemplate($template['body'], $data);

    $db->prepare("
        INSERT INTO notification_queue
        (event_type, recipient_email, subject, body_html, reservation_id)
        VALUES (:event, :email, :subject, :body, :res_id)
    ")->execute([...]);
}
```

**Background Worker:**
```php
// cron: */5 * * * * php /path/to/send_notifications.php

while ($notification = fetchNextPending($db)) {
    try {
        sendEmail($notification['recipient_email'], $notification['subject'], $notification['body_html']);
        markSent($db, $notification['id']);
    } catch (\Throwable $e) {
        markFailed($db, $notification['id'], $e->getMessage());
    }
}
```

---

#### 3.3 Comprehensive Reporting

**New Reports:**

1. **Inventory Consumption by Job Type:**
   ```sql
   SELECT
       SUBSTRING(jr.job_number FROM '^[A-Z]+-[0-9]{4}') AS job_type,
       COUNT(DISTINCT jr.id) AS reservation_count,
       SUM(jri.consumed_qty) AS total_consumed,
       AVG(jri.consumed_qty) AS avg_consumed_per_job
   FROM job_reservations jr
   JOIN job_reservation_items jri ON jri.reservation_id = jr.id
   WHERE jr.status = 'fulfilled'
     AND jr.created_at >= NOW() - INTERVAL '90 days'
   GROUP BY job_type
   ORDER BY total_consumed DESC;
   ```

2. **Estimation Accuracy:**
   ```sql
   SELECT
       ii.part_number,
       ii.item,
       AVG(jri.committed_qty) AS avg_committed,
       AVG(jri.consumed_qty) AS avg_consumed,
       AVG(jri.committed_qty - jri.consumed_qty) AS avg_waste,
       ROUND(100.0 * AVG(jri.consumed_qty) / NULLIF(AVG(jri.committed_qty), 0), 2) AS accuracy_pct
   FROM job_reservation_items jri
   JOIN inventory_items ii ON ii.id = jri.inventory_item_id
   JOIN job_reservations jr ON jr.id = jri.reservation_id
   WHERE jr.status = 'fulfilled'
   GROUP BY ii.id
   HAVING COUNT(*) >= 3  -- At least 3 reservations
   ORDER BY accuracy_pct;
   ```

3. **Lead Time Analysis:**
   ```sql
   SELECT
       jr.job_number,
       jr.status,
       jr.created_at,
       MIN(CASE WHEN jrsl.new_status = 'in_progress' THEN jrsl.changed_at END) AS work_started,
       MIN(CASE WHEN jrsl.new_status = 'fulfilled' THEN jrsl.changed_at END) AS work_completed,
       EXTRACT(EPOCH FROM (
           MIN(CASE WHEN jrsl.new_status = 'fulfilled' THEN jrsl.changed_at END) -
           MIN(CASE WHEN jrsl.new_status = 'in_progress' THEN jrsl.changed_at END)
       )) / 86400 AS days_in_progress
   FROM job_reservations jr
   LEFT JOIN job_reservation_status_log jrsl ON jrsl.reservation_id = jr.id
   GROUP BY jr.id;
   ```

4. **Shortage Forecast:**
   ```sql
   WITH active_commitments AS (
       SELECT
           jri.inventory_item_id,
           SUM(jri.committed_qty - jri.consumed_qty) AS remaining_committed
       FROM job_reservation_items jri
       JOIN job_reservations jr ON jr.id = jri.reservation_id
       WHERE jr.status IN ('active', 'in_progress', 'on_hold')
       GROUP BY jri.inventory_item_id
   )
   SELECT
       ii.part_number,
       ii.item,
       ii.stock,
       ac.remaining_committed,
       (ii.stock - ac.remaining_committed) AS available,
       ii.reorder_point,
       CASE
           WHEN (ii.stock - ac.remaining_committed) < 0 THEN 'CRITICAL'
           WHEN (ii.stock - ac.remaining_committed) < ii.reorder_point THEN 'LOW'
           ELSE 'OK'
       END AS status
   FROM inventory_items ii
   LEFT JOIN active_commitments ac ON ac.inventory_item_id = ii.id
   WHERE (ii.stock - COALESCE(ac.remaining_committed, 0)) < ii.reorder_point * 1.5
   ORDER BY (ii.stock - COALESCE(ac.remaining_committed, 0)) / NULLIF(ii.reorder_point, 0);
   ```

**Export Formats:**
- HTML (browser view)
- PDF (download)
- CSV (Excel import)
- JSON (API integration)

---

#### 3.4 Cycle Count Integration

**Goal:** Protect reservations during cycle count adjustments

**Enhanced Adjustment Logic:**

**Before Adjustment:**
```php
function validateCycleCountAdjustment(
    \PDO $db,
    int $inventoryItemId,
    int $newStock
): array {
    $item = loadInventory($db, [$inventoryItemId])[0];
    $committed = $item['committed_qty'];

    $warnings = [];

    if ($newStock < $committed) {
        $warnings[] = sprintf(
            'WARNING: Adjusted stock (%d) is less than committed qty (%d). ' .
            'This will result in %d units of negative available inventory.',
            $newStock,
            $committed,
            $committed - $newStock
        );

        // List affected reservations
        $affected = $db->query("
            SELECT jr.job_number, jr.job_name, jri.committed_qty
            FROM job_reservation_items jri
            JOIN job_reservations jr ON jr.id = jri.reservation_id
            WHERE jri.inventory_item_id = $inventoryItemId
              AND jr.status IN ('active', 'in_progress', 'on_hold')
            ORDER BY jr.priority, jr.needed_by
        ")->fetchAll();

        $warnings[] = 'Affected Reservations: ' .
            implode(', ', array_column($affected, 'job_number'));
    }

    return ['allowed' => true, 'warnings' => $warnings];
}
```

**UI Flow:**
1. User completes cycle count (counted_qty entered)
2. System calculates variance
3. Before applying adjustment:
   - Check active reservations
   - Show warning dialog if conflict
   - User must acknowledge or cancel
4. Apply adjustment + log warning in notes

---

#### 3.5 Release Number Automation

**Goal:** Auto-increment releases and track changes

**Enhanced UI:**

1. **Create New Release:**
   - Button: "Revise Reservation" (creates copy as new release)
   - Auto-increments release_number
   - Pre-fills all metadata and line items from previous release
   - User modifies as needed

2. **Release Comparison:**
   ```html
   <div class="release-comparison">
     <h3>JOB-2024-001: Release Comparison</h3>
     <table>
       <thead>
         <tr>
           <th>Line Item</th>
           <th>Release 1 (Committed)</th>
           <th>Release 2 (Committed)</th>
           <th>Change</th>
         </tr>
       </thead>
       <tbody>
         <tr>
           <td>Aluminum Channel AC-101</td>
           <td>50</td>
           <td>48</td>
           <td class="decreased">-2 (-4%)</td>
         </tr>
         <tr>
           <td>Corner Bracket CB-200</td>
           <td>96</td>
           <td>96</td>
           <td class="unchanged">—</td>
         </tr>
         <tr>
           <td>Steel Plate SP-300</td>
           <td>—</td>
           <td>20</td>
           <td class="added">+20 (new)</td>
         </tr>
       </tbody>
     </table>
   </div>
   ```

3. **Release History:**
   - Timeline view of all releases for a job
   - Shows what changed between versions
   - Links to view/compare each release

---

#### 3.6 Unit of Measure (UOM) System

**Goal:** Support multiple units with conversions

**Database:**
```sql
CREATE TABLE units_of_measure (
    id SERIAL PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,  -- 'EA', 'FT', 'SQ_FT', 'GAL', etc.
    name TEXT NOT NULL,          -- 'Each', 'Feet', 'Square Feet', 'Gallons'
    uom_type TEXT NOT NULL       -- 'quantity', 'length', 'area', 'volume', 'weight'
);

CREATE TABLE uom_conversions (
    id SERIAL PRIMARY KEY,
    from_uom_id INTEGER NOT NULL REFERENCES units_of_measure(id),
    to_uom_id INTEGER NOT NULL REFERENCES units_of_measure(id),
    factor NUMERIC(12,6) NOT NULL,  -- from_qty * factor = to_qty
    UNIQUE(from_uom_id, to_uom_id)
);

-- Example: 1 FT = 12 IN
INSERT INTO uom_conversions (from_uom_id, to_uom_id, factor)
VALUES (
    (SELECT id FROM units_of_measure WHERE code = 'FT'),
    (SELECT id FROM units_of_measure WHERE code = 'IN'),
    12.0
);

ALTER TABLE inventory_items
ADD COLUMN uom_id INTEGER REFERENCES units_of_measure(id);
```

**Conversion Helper:**
```php
function convertQuantity(
    \PDO $db,
    float $qty,
    int $fromUomId,
    int $toUomId
): float {
    if ($fromUomId === $toUomId) {
        return $qty;
    }

    $factor = $db->prepare("
        SELECT factor FROM uom_conversions
        WHERE from_uom_id = :from AND to_uom_id = :to
    ")->execute([...])->fetchColumn();

    if ($factor === false) {
        throw new \Exception('No conversion available');
    }

    return $qty * $factor;
}
```

**UI:**
- Display: "50 FT (600 IN)"
- Input: Dropdown to select UOM
- EZ Estimate: Auto-convert from estimate UOM to inventory UOM

---

### Priority 4: Integration & APIs

#### 4.1 REST API

**Endpoints:**

```
GET    /api/reservations                  - List all reservations
POST   /api/reservations                  - Create new reservation
GET    /api/reservations/{id}             - Get reservation detail
PATCH  /api/reservations/{id}             - Update reservation
DELETE /api/reservations/{id}             - Cancel reservation
POST   /api/reservations/{id}/complete    - Complete reservation

GET    /api/inventory                     - List inventory items
GET    /api/inventory/{id}                - Get item detail
POST   /api/inventory/{id}/adjust         - Adjust stock level

POST   /api/estimates/analyze             - Upload & analyze EZ Estimate
POST   /api/estimates/commit              - Create reservation from estimate
```

**Authentication:**
```php
// JWT or API Key based
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

**Example Request:**
```bash
curl -X POST https://forgedesk.example.com/api/reservations \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "job_number": "JOB-2024-001",
    "release_number": 1,
    "job_name": "ACME Corp - Widget Assembly",
    "requested_by": "John Smith",
    "needed_by": "2026-02-15",
    "priority": 2,
    "lines": [
      {"inventory_item_id": 123, "requested_qty": 50, "committed_qty": 48},
      {"inventory_item_id": 124, "requested_qty": 96, "committed_qty": 96}
    ]
  }'
```

---

#### 4.2 Webhook System

**Goal:** Notify external systems of events

**Configuration:**
```sql
CREATE TABLE webhook_subscriptions (
    id SERIAL PRIMARY KEY,
    url TEXT NOT NULL,
    event_type TEXT NOT NULL,  -- 'reservation.created', 'reservation.fulfilled', etc.
    secret TEXT NOT NULL,       -- For HMAC signature
    active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

**Trigger Webhook:**
```php
function triggerWebhook(
    \PDO $db,
    string $eventType,
    array $payload
): void {
    $subscriptions = $db->query("
        SELECT * FROM webhook_subscriptions
        WHERE event_type = '$eventType' AND active = true
    ")->fetchAll();

    foreach ($subscriptions as $sub) {
        $signature = hash_hmac('sha256', json_encode($payload), $sub['secret']);

        $ch = curl_init($sub['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Webhook-Signature: ' . $signature,
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}
```

**Usage:**
- Integrate with ERP systems
- Notify shipping when reservation fulfilled
- Update project management tools

---

## Implementation Priorities

### Phase 1: Critical Fixes (1-2 weeks)

1. ✅ Partial fulfillment support
2. ✅ Enhanced status transitions
3. ✅ Strict inventory validation mode
4. ✅ Reservation priority system

**Estimated Effort:** 40-60 hours

---

### Phase 2: UX Improvements (2-3 weeks)

1. ✅ Bulk actions (commit all, release all)
2. ✅ Advanced search and filtering
3. ✅ Pagination for large reservations
4. ✅ Keyboard shortcuts

**Estimated Effort:** 60-80 hours

---

### Phase 3: Advanced Features (3-4 weeks)

1. ✅ Part substitution system
2. ✅ Email notifications
3. ✅ Comprehensive reporting
4. ✅ Cycle count integration
5. ✅ Release number automation

**Estimated Effort:** 100-120 hours

---

### Phase 4: Enterprise Features (4-6 weeks)

1. ✅ Unit of measure (UOM) system
2. ✅ REST API
3. ✅ Webhook system
4. ✅ Vendor integration
5. ✅ Mobile-responsive UI

**Estimated Effort:** 120-160 hours

---

## Appendix: Key Formulas

### Available Inventory Calculation

```
available_qty = stock_on_hand + on_order - committed_qty

where committed_qty = SUM(
    job_reservation_items.committed_qty
    WHERE reservation.status IN ('active', 'committed', 'in_progress', 'on_hold')
)
```

### Estimation Accuracy

```
accuracy_pct = (consumed_qty / committed_qty) * 100

Example:
Committed: 50 units
Consumed: 48 units
Accuracy: (48/50) * 100 = 96%

Interpretation:
- 100%: Perfect estimate
- >100%: Under-estimated (needed more)
- <100%: Over-estimated (waste)
```

### Lead Time Calculation

```
lead_time_days = date_fulfilled - date_created

work_duration_days = date_fulfilled - date_started_progress
```

### Inventory Turnover Rate

```
turnover_rate = total_consumed_qty_period / avg_stock_level

Example (90 days):
Consumed: 1,200 units
Avg Stock: 300 units
Turnover: 1200/300 = 4.0 (inventory turns 4x per quarter)
```

---

## Conclusion

ForgeDesk2's order fulfillment system provides a solid foundation for inventory management and job reservations. The EZ Estimate integration streamlines the quoting-to-production workflow, while the reservation system prevents overselling and maintains audit trails.

**Current State:**
- ✅ Core functionality stable and production-ready
- ✅ Strong database integrity with proper constraints
- ✅ Flexible Excel integration for BOMs
- ⚠️ Limited status transitions and partial fulfillment
- ⚠️ Basic UX with room for efficiency gains

**Recommended Path Forward:**
1. Implement **Priority 1** fixes for immediate operational impact
2. Roll out **Priority 2** UX improvements for user adoption
3. Evaluate **Priority 3** based on business needs and user feedback
4. Consider **Priority 4** for enterprise scaling and integrations

This documentation serves as a blueprint for iterating on the fulfillment system while preserving the robust architecture already in place.

---

**Document Version:** 1.0
**Last Updated:** 2026-01-20
**Author:** Claude (AI Assistant)
**Repository:** Jkweks/ForgeDesk2
