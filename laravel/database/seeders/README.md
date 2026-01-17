# Database Seeders

## ComprehensiveSeeder

The `ComprehensiveSeeder` creates a complete set of test data for ForgeDesk3, including the new hierarchical storage location system.

### ⚠️ WARNING
**This seeder PURGES ALL EXISTING DATA before creating new seed data!**

### What Gets Created

- **Users** (3 total)
  - admin@forgedesk.com / password
  - john@forgedesk.com / password
  - sarah@forgedesk.com / password

- **Categories** (5)
  - Raw Materials
  - Hardware
  - Finishing
  - Electrical
  - Assembly Components

- **Suppliers** (5)
  - Tubelite Inc.
  - Kawneer Company
  - YKK AP America
  - Precision Hardware Inc.
  - Acme Glass Supply

- **Hierarchical Storage Locations** (~60+ locations)
  - Aisle 1 → Rack A → Shelves 1-5 → Bins 1-8
  - Aisle 2 → Racks A-B
  - Aisle 3 → Rack A
  - Aisle 4 → Rack A
  - Receiving Area
  - Returns Area

- **Products** (15)
  - Aluminum extrusions with various finishes
  - Hardware (hinges, locks, closers, panic devices)
  - Glass (tempered, various thicknesses)
  - Finishing materials (powder coating)
  - Electrical components (card readers)
  - Assembly components (screws, gaskets, weatherstripping)

- **Inventory Locations** (~35+)
  - Each product assigned to 1-3 storage locations
  - Quantities distributed across locations
  - Primary location flagged

- **Machines** (5)
  - CNC Router
  - Powder Coating Booth
  - Hydraulic Press
  - Glass Cutting Table
  - Fork Lift

- **Maintenance Tasks & Records**
  - Daily safety inspections
  - Weekly lubrication
  - Historical maintenance records (3-5 per machine)

- **Job Reservations** (3)
  - Downtown Office Building
  - Hospital Expansion
  - Retail Plaza
  - Each with 3-6 reserved items

- **Purchase Orders** (5)
  - Various statuses (draft, submitted, approved, received)
  - 2-5 line items each
  - Linked to suppliers

- **Inventory Transactions** (20-30)
  - Various types: receipt, adjustment, consumption, cycle count, transfer, return
  - Historical data spanning 60 days

- **Cycle Count Sessions** (3)
  - Completed session with count lines
  - In-progress session
  - Scheduled future session

## Usage

### Fresh Install with Seed Data

```bash
cd /home/user/ForgeDesk3/laravel

# Run migrations
php artisan migrate:fresh

# Seed the database
php artisan db:seed

# Or combine both
php artisan migrate:fresh --seed
```

### Run Only the Comprehensive Seeder

```bash
php artisan db:seed --class=ComprehensiveSeeder
```

### Test Login

After seeding, you can log in with:
- **Email:** admin@forgedesk.com
- **Password:** password

## Viewing Hierarchical Locations

After seeding, you can view the hierarchical storage locations by:

1. **Via API:**
   ```bash
   curl -X GET http://localhost:8000/api/v1/storage-locations-tree \
     -H "Authorization: Bearer YOUR_TOKEN"
   ```

2. **Via Frontend:**
   Navigate to `/storage-locations` (requires web route setup)

3. **Via Database:**
   ```sql
   SELECT * FROM storage_locations ORDER BY aisle, rack, shelf, bin;
   ```

## Location Hierarchy Example

```
Aisle 1 (id: 1)
├─ Rack A (id: 2, slug: "1.A")
   ├─ Shelf 1 (id: 3, slug: "1.A.1")
   │  ├─ Bin 1 (id: 4, slug: "1.A.1.1")
   │  ├─ Bin 2 (id: 5, slug: "1.A.1.2")
   │  └─ ... (Bins 3-8)
   ├─ Shelf 2 (id: 12)
   ├─ Shelf 3 (id: 13)
   │  ├─ Bin 1 (id: 14, slug: "1.A.3.1")
   │  └─ ... (Bins 2-8)
   └─ ... (Shelves 4-5)
```

## Customizing Seed Data

To modify the seed data:

1. Edit `ComprehensiveSeeder.php`
2. Adjust quantities, products, or locations in the respective methods
3. Re-run: `php artisan migrate:fresh --seed`

## Troubleshooting

### Foreign Key Constraint Errors
If you get foreign key errors, make sure to run migrations first:
```bash
php artisan migrate:fresh
php artisan db:seed
```

### Memory Issues
If you run out of memory with large datasets:
```bash
php -d memory_limit=512M artisan db:seed
```

### Reset Everything
To completely reset and reseed:
```bash
php artisan migrate:fresh --seed
```

## Testing the Hierarchical Locations

After seeding, test the new hierarchical features:

```bash
# Get location tree
GET /api/v1/storage-locations-tree

# Get specific location with stats
GET /api/v1/storage-locations/1/statistics

# Search locations
POST /api/v1/storage-locations-search
{"query": "Aisle 1"}

# Bulk create from path
POST /api/v1/storage-locations-bulk-create
{"path": "5.A.1.10"}

# Check reconciliation status
GET /api/v1/storage-locations-reconciliation/status
```

## Legacy Seeders

The original seeders are still available but commented out in `DatabaseSeeder.php`:
- `AdminSeeder`
- `ProductSeeder`
- `InventoryLocationSeeder`
- `JobReservationSeeder`

To use the legacy seeders instead:
1. Comment out the `ComprehensiveSeeder` line
2. Uncomment the legacy seeder calls
3. Run `php artisan db:seed`

---

**Created:** 2026-01-13
**Version:** 1.0
**Phase:** 1.1 - Hierarchical Storage Locations
