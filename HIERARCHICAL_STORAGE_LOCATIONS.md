# Hierarchical Storage Locations - Implementation Guide

## Overview

This document describes the implementation of hierarchical storage locations in ForgeDesk3, matching the functionality from ForgeDesk2. The system supports a hierarchical location structure: **Aisle → Rack → Shelf → Bin**.

## What Was Implemented

### 1. Database Schema

#### New Table: `storage_locations`
```sql
- id (primary key)
- parent_id (self-referencing foreign key for hierarchy)
- name (location name, e.g., "Aisle 1", "Rack A")
- aisle, rack, shelf, bin (components for hierarchical organization)
- slug (path identifier, e.g., "1.2.3.4")
- description (optional notes)
- is_active (boolean)
- sort_order (integer for custom ordering)
- timestamps, soft deletes
```

#### Modified Table: `inventory_locations`
```sql
- Added: storage_location_id (foreign key to storage_locations)
- Modified: location (now nullable, kept for backward compatibility)
```

### 2. Models

#### `StorageLocation` Model
Location: `/laravel/app/Models/StorageLocation.php`

**Key Features:**
- Self-referencing parent-child relationships
- Hierarchical tree traversal methods
- Automatic slug generation
- Location name parsing
- Quantity aggregation (including children)
- Tree building capabilities

**Important Methods:**
- `parent()` - Get parent location
- `children()` - Get child locations
- `descendants()` - Get all descendants recursively
- `ancestors()` - Get all ancestors up to root
- `pathArray()` - Get hierarchical path as array
- `formatName()` - Format location name from components
- `parseLocationName()` - Extract components from name string
- `tree()` - Build complete hierarchical tree
- `totalQuantity()` - Sum quantity at location + children
- `totalCommittedQuantity()` - Sum committed quantity
- `totalAvailableQuantity()` - Calculate available quantity

#### `InventoryLocation` Model (Updated)
Location: `/laravel/app/Models/InventoryLocation.php`

**Added:**
- `storageLocation()` relationship to `StorageLocation`
- Support for `storage_location_id` in fillable attributes

### 3. API Controllers

#### `StorageLocationController`
Location: `/laravel/app/Http/Controllers/Api/StorageLocationController.php`

**Endpoints:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/storage-locations` | List all locations (flat) |
| GET | `/api/v1/storage-locations-tree` | Get hierarchical tree |
| GET | `/api/v1/storage-locations/{id}` | Get specific location |
| POST | `/api/v1/storage-locations` | Create new location |
| PUT | `/api/v1/storage-locations/{id}` | Update location |
| DELETE | `/api/v1/storage-locations/{id}` | Delete location |
| GET | `/api/v1/storage-locations/{id}/ancestors` | Get breadcrumb path |
| GET | `/api/v1/storage-locations/{id}/descendants` | Get all children |
| GET | `/api/v1/storage-locations/{id}/statistics` | Get location stats |
| POST | `/api/v1/storage-locations/{id}/move` | Move to new parent |
| POST | `/api/v1/storage-locations-parse` | Parse location name |
| POST | `/api/v1/storage-locations-search` | Search locations |
| POST | `/api/v1/storage-locations-bulk-create` | Create hierarchy from path |

#### `StorageLocationReconciliationController`
Location: `/laravel/app/Http/Controllers/Api/StorageLocationReconciliationController.php`

**Reconciliation Endpoints:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/storage-locations-reconciliation/status` | Check for issues |
| GET | `/api/v1/storage-locations-reconciliation/report` | Detailed report |
| POST | `/api/v1/storage-locations-reconciliation/migrate` | Migrate string locations |
| POST | `/api/v1/storage-locations-reconciliation/fix-orphaned` | Fix broken references |
| POST | `/api/v1/storage-locations-reconciliation/cleanup-empty` | Remove empty locations |
| POST | `/api/v1/storage-locations-reconciliation/fix-duplicates` | Fix duplicate slugs |
| POST | `/api/v1/storage-locations-reconciliation/sync-quantities` | Recalculate totals |

### 4. Frontend UI

#### Storage Locations Management Page
Location: `/laravel/resources/views/storage-locations.blade.php`

**Features:**
- Hierarchical tree view with expand/collapse
- Visual hierarchy (indentation, icons)
- Search functionality
- Quick create from path (e.g., "1.2.3.4")
- Add/Edit/Delete locations
- View location statistics
- Parent selection dropdown
- Component-based input (aisle, rack, shelf, bin)

**Access:** Navigate to `/storage-locations` (route needs to be added to web.php)

### 5. Migration Files

1. **`2026_01_13_000001_create_storage_locations_table.php`**
   - Creates the `storage_locations` table
   - Defines hierarchy and component columns

2. **`2026_01_13_000002_add_storage_location_to_inventory_locations.php`**
   - Adds `storage_location_id` to `inventory_locations`
   - Migrates existing string-based locations automatically
   - Parses location names to extract components

## How to Use

### Running Migrations

```bash
cd /home/user/ForgeDesk3/laravel
php artisan migrate
```

The migration will automatically:
1. Create the `storage_locations` table
2. Parse existing location strings from `inventory_locations`
3. Create corresponding `StorageLocation` records
4. Link `inventory_locations` to the new `storage_locations`

### Creating Locations

#### Via API

**Create Single Location:**
```bash
POST /api/v1/storage-locations
{
  "name": "Aisle 1",
  "aisle": "1",
  "is_active": true,
  "sort_order": 0
}
```

**Create Hierarchy from Path:**
```bash
POST /api/v1/storage-locations-bulk-create
{
  "path": "1.2.3.4"
}
```
This creates: Aisle 1 → Rack 2 → Shelf 3 → Bin 4

#### Via Frontend

1. Navigate to Storage Locations page
2. Click "Add Location" button
3. Fill in the form:
   - Name (required)
   - Aisle, Rack, Shelf, Bin (optional)
   - Parent location (optional)
   - Description (optional)
4. Click "Save Location"

**Quick Create:**
- Enter a path like "1.2.3.4" in the Quick Create field
- Click "Create Hierarchy"
- System creates nested locations automatically

### Searching Locations

**Search by name, component, or slug:**
```bash
POST /api/v1/storage-locations-search
{
  "query": "Aisle 1"
}
```

### Getting Location Tree

**Get full hierarchy:**
```bash
GET /api/v1/storage-locations-tree
```

Returns nested JSON structure:
```json
[
  {
    "id": 1,
    "name": "Aisle 1",
    "aisle": "1",
    "slug": "1",
    "children": [
      {
        "id": 2,
        "name": "Rack A",
        "rack": "A",
        "slug": "1.A",
        "children": [...]
      }
    ]
  }
]
```

### Reconciliation Tools

After migration or if data issues occur:

**Check Status:**
```bash
GET /api/v1/storage-locations-reconciliation/status
```

**Fix Issues:**
```bash
# Migrate unmigrated locations
POST /api/v1/storage-locations-reconciliation/migrate

# Fix orphaned references
POST /api/v1/storage-locations-reconciliation/fix-orphaned

# Cleanup empty locations (dry run)
POST /api/v1/storage-locations-reconciliation/cleanup-empty
{"dry_run": true}

# Cleanup empty locations (actual)
POST /api/v1/storage-locations-reconciliation/cleanup-empty
{"dry_run": false}

# Fix duplicate slugs
POST /api/v1/storage-locations-reconciliation/fix-duplicates

# Sync product quantities
POST /api/v1/storage-locations-reconciliation/sync-quantities
```

## Location Naming Conventions

### Automatic Parsing

The system can parse various location name formats:

```
"Aisle 1" → {aisle: "1"}
"Aisle 1, Rack A" → {aisle: "1", rack: "A"}
"Aisle 1, Rack A, Shelf 3" → {aisle: "1", rack: "A", shelf: "3"}
"1.2.3.4" → {aisle: "1", rack: "2", shelf: "3", bin: "4"}
```

### Slug Generation

Slugs are auto-generated from components:
- Aisle 1 → "1"
- Rack A (child of Aisle 1) → "1.A"
- Shelf 3 (child of Rack A) → "1.A.3"
- Bin 12 (child of Shelf 3) → "1.A.3.12"

## Hierarchy Rules

1. **Levels:** Aisle → Rack → Shelf → Bin (but flexible)
2. **Parent-Child:** Any location can have child locations
3. **Circular Prevention:** System prevents circular references
4. **Deletion:** Cannot delete locations with:
   - Child locations (must delete/move children first)
   - Inventory (must move/remove inventory first)

## Integration with Inventory

### Linking Products to Locations

When creating/updating `InventoryLocation`:

```bash
POST /api/v1/products/{product_id}/locations
{
  "storage_location_id": 123,
  "quantity": 100,
  "is_primary": true
}
```

The system now supports BOTH:
- `storage_location_id` (new hierarchical system)
- `location` (legacy string-based system)

During transition, you can use both fields.

### Querying Inventory by Location

**Get all products at a location:**
```php
$location = StorageLocation::find(1);
$products = $location->products; // Through inventory_locations
```

**Get total quantity at location (including children):**
```php
$totalQty = $location->totalQuantity();
$totalAvailable = $location->totalAvailableQuantity();
```

## Web Routes (To Add)

Add to `/laravel/routes/web.php`:

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/storage-locations', function () {
        return view('storage-locations');
    });
});
```

## Testing

### Manual Testing Steps

1. **Create Root Location:**
   - Create "Aisle 1" with aisle="1"
   - Verify slug is "1"

2. **Create Child Location:**
   - Create "Rack A" with parent=Aisle 1, rack="A"
   - Verify slug is "1.A"
   - Verify hierarchy in tree view

3. **Bulk Create:**
   - Use path "2.B.5.10"
   - Verify creates: Aisle 2 → Rack B → Shelf 5 → Bin 10

4. **Search:**
   - Search for "Rack"
   - Verify finds all rack locations

5. **Statistics:**
   - Add inventory to a location
   - Check statistics endpoint
   - Verify quantity totals

6. **Move Location:**
   - Move a rack to different aisle
   - Verify parent_id updated
   - Verify slug regenerated

7. **Delete Protection:**
   - Try deleting location with inventory
   - Verify error message
   - Try deleting location with children
   - Verify error message

### Reconciliation Testing

1. **Create string-based location:**
   ```sql
   INSERT INTO inventory_locations (product_id, location, quantity)
   VALUES (1, 'Warehouse A, Bin 5', 50);
   ```

2. **Run migration:**
   ```bash
   POST /api/v1/storage-locations-reconciliation/migrate
   ```

3. **Verify:**
   - New storage_location created
   - inventory_location.storage_location_id populated
   - Components parsed correctly

## Known Limitations

1. **Migration Parsing:** Complex location names may not parse perfectly. Review migrated locations and manually correct if needed.

2. **Slug Uniqueness:** If you manually set components, ensure slugs remain unique.

3. **Performance:** Large hierarchies (1000+ locations) may need optimization for tree rendering.

## Next Steps

After Phase 1.1 completion, proceed with:
- **Phase 1.2:** Inventory Systems / Framing Systems
- **Phase 1.3:** Daily Usage Tracking
- **Phase 2:** Material Replenishment Module

## Support

For issues or questions:
1. Check reconciliation status endpoint
2. Review migration logs
3. Use reconciliation tools to fix data issues
4. Refer to ForgeDesk2 implementation at `/tmp/ForgeDesk2`

---

**Implementation Date:** 2026-01-13
**Version:** ForgeDesk3 v1.0
**Status:** Phase 1.1 Complete ✅
