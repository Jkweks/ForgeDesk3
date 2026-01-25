# ForgeDesk2 Schema Documentation & Migration Plan to ForgeDesk3

## Table of Contents

1. [ForgeDesk2 Schema Overview](#1-forgedesk2-schema-overview)
2. [ForgeDesk2 Complete Table Reference](#2-forgedesk2-complete-table-reference)
3. [ForgeDesk3 Schema Summary](#3-forgedesk3-schema-summary)
4. [Schema Mapping: ForgeDesk2 to ForgeDesk3](#4-schema-mapping-forgedesk2-to-forgedesk3)
5. [Migration Plan](#5-migration-plan)
6. [Migration Scripts & Queries](#6-migration-scripts--queries)
7. [Post-Migration Validation](#7-post-migration-validation)

---

## 1. ForgeDesk2 Schema Overview

- **Database**: PostgreSQL
- **ORM Layer**: Django (admin_service)
- **Schema Sources**: `database/init.sql`, `database/migrations/` (17 migration files), `admin_service/inventory/models.py`
- **Total Tables/Views**: 32

### Domain Breakdown

| Domain | Tables | Description |
|--------|--------|-------------|
| **Inventory** | 7 | Core inventory items, metrics, transactions, daily usage |
| **Purchasing** | 5 | Purchase orders, lines, receipts, receipt lines, suppliers |
| **Warehouse** | 3 | Storage locations, item-location mapping, systems |
| **Job Reservations** | 2 | Reservation headers and line items |
| **Cycle Counting** | 2 | Count sessions and count lines |
| **Maintenance** | 6 | Machines, types, assets, tasks, records, asset-machine join |
| **Configurator** | 7 | Part profiles, use options, requirements, jobs, configurations, doors |

---

## 2. ForgeDesk2 Complete Table Reference

### 2.1 inventory_items

The core inventory table. Maps to `products` in ForgeDesk3.

| Column | Type | Constraints |
|--------|------|-------------|
| id | SERIAL | PRIMARY KEY |
| item | TEXT | NOT NULL |
| sku | TEXT | NOT NULL, UNIQUE |
| part_number | TEXT | NOT NULL DEFAULT '' |
| finish | TEXT | NULL |
| location | TEXT | NOT NULL |
| stock | INTEGER | NOT NULL DEFAULT 0 |
| committed_qty | INTEGER | NOT NULL DEFAULT 0 |
| on_order_qty | NUMERIC(18,6) | DEFAULT 0 |
| safety_stock | NUMERIC(18,6) | DEFAULT 0 |
| min_order_qty | NUMERIC(18,6) | DEFAULT 0 |
| order_multiple | NUMERIC(18,6) | DEFAULT 0 |
| pack_size | NUMERIC(18,6) | DEFAULT 0 |
| purchase_uom | TEXT | NULL |
| stock_uom | TEXT | NULL |
| status | TEXT | NOT NULL DEFAULT 'In Stock' |
| supplier | TEXT | NOT NULL DEFAULT 'Unknown Supplier' |
| supplier_id | BIGINT | FK -> suppliers(id) |
| supplier_contact | TEXT | NULL |
| supplier_sku | TEXT | NULL |
| reorder_point | INTEGER | NOT NULL DEFAULT 0 |
| lead_time_days | INTEGER | NOT NULL DEFAULT 0 |
| average_daily_use | NUMERIC(12,4) | NULL |

### 2.2 inventory_metrics

Dashboard KPI values. No direct ForgeDesk3 equivalent (computed dynamically).

| Column | Type | Constraints |
|--------|------|-------------|
| id | SERIAL | PRIMARY KEY |
| label | TEXT | NOT NULL, UNIQUE |
| value | TEXT | NOT NULL |
| delta | TEXT | NULL |
| timeframe | TEXT | NULL |
| accent | BOOLEAN | NOT NULL DEFAULT FALSE |
| sort_order | INTEGER | NOT NULL DEFAULT 100 |

### 2.3 job_reservations

| Column | Type | Constraints |
|--------|------|-------------|
| id | SERIAL | PRIMARY KEY |
| job_number | TEXT | NOT NULL |
| release_number | INTEGER | NOT NULL DEFAULT 1 |
| job_name | TEXT | NOT NULL |
| requested_by | TEXT | NOT NULL |
| needed_by | DATE | NULL |
| status | ENUM | NOT NULL DEFAULT 'draft' |
| notes | TEXT | NULL |
| created_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP |

**ENUM values**: `draft`, `committed`, `active`, `on_hold`, `in_progress`, `fulfilled`, `cancelled`
**Unique constraint**: `(job_number, release_number)`

### 2.4 job_reservation_items

| Column | Type | Constraints |
|--------|------|-------------|
| id | SERIAL | PRIMARY KEY |
| reservation_id | INTEGER | NOT NULL, FK -> job_reservations(id) ON DELETE CASCADE |
| inventory_item_id | INTEGER | NOT NULL, FK -> inventory_items(id) ON DELETE RESTRICT |
| requested_qty | INTEGER | NOT NULL DEFAULT 0 |
| committed_qty | INTEGER | NOT NULL DEFAULT 0 |
| consumed_qty | INTEGER | NOT NULL DEFAULT 0 |

**Unique constraint**: `(reservation_id, inventory_item_id)`

### 2.5 inventory_item_commitments (VIEW)

Aggregates committed quantities from active reservations per inventory item.

```sql
SELECT i.id AS inventory_item_id,
       COALESCE(SUM(CASE WHEN jr.status IN ('active','committed','in_progress','on_hold')
                         THEN jri.committed_qty ELSE 0 END), 0) AS committed_qty
FROM inventory_items i
LEFT JOIN job_reservation_items jri ON jri.inventory_item_id = i.id
LEFT JOIN job_reservations jr ON jr.id = jri.reservation_id
GROUP BY i.id;
```

### 2.6 cycle_count_sessions

| Column | Type | Constraints |
|--------|------|-------------|
| id | SERIAL | PRIMARY KEY |
| name | TEXT | NOT NULL |
| status | TEXT | NOT NULL DEFAULT 'in_progress' |
| started_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP |
| completed_at | TIMESTAMP | NULL |
| location_filter | TEXT | NULL |
| total_lines | INTEGER | NOT NULL DEFAULT 0 |
| completed_lines | INTEGER | NOT NULL DEFAULT 0 |

### 2.7 cycle_count_lines

| Column | Type | Constraints |
|--------|------|-------------|
| id | SERIAL | PRIMARY KEY |
| session_id | INTEGER | NOT NULL, FK -> cycle_count_sessions(id) ON DELETE CASCADE |
| inventory_item_id | INTEGER | NOT NULL, FK -> inventory_items(id) ON DELETE CASCADE |
| sequence | INTEGER | NOT NULL |
| expected_qty | INTEGER | NOT NULL DEFAULT 0 |
| counted_qty | INTEGER | NULL |
| variance | INTEGER | NULL |
| counted_at | TIMESTAMP | NULL |
| note | TEXT | NULL |
| is_skipped | BOOLEAN | NOT NULL DEFAULT FALSE |

**Unique constraint**: `(session_id, sequence)`

### 2.8 inventory_transactions

Transaction headers (audit log). ForgeDesk3 flattens this into a single table.

| Column | Type | Constraints |
|--------|------|-------------|
| id | SERIAL | PRIMARY KEY |
| reference | TEXT | NOT NULL |
| notes | TEXT | NULL |
| created_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP |

### 2.9 inventory_transaction_lines

Transaction detail lines. Merged into `inventory_transactions` in ForgeDesk3.

| Column | Type | Constraints |
|--------|------|-------------|
| id | SERIAL | PRIMARY KEY |
| transaction_id | INTEGER | NOT NULL, FK -> inventory_transactions(id) ON DELETE CASCADE |
| inventory_item_id | INTEGER | NOT NULL, FK -> inventory_items(id) ON DELETE RESTRICT |
| quantity_change | INTEGER | NOT NULL |
| note | TEXT | NULL |
| stock_before | INTEGER | NOT NULL DEFAULT 0 |
| stock_after | INTEGER | NOT NULL DEFAULT 0 |

### 2.10 inventory_daily_usage

| Column | Type | Constraints |
|--------|------|-------------|
| inventory_item_id | INTEGER | NOT NULL, FK -> inventory_items(id) ON DELETE CASCADE |
| usage_date | DATE | NOT NULL |
| quantity_used | INTEGER | NOT NULL DEFAULT 0 |

**Primary Key**: `(inventory_item_id, usage_date)`

### 2.11 suppliers

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| name | TEXT | NOT NULL |
| contact_name | TEXT | NULL |
| contact_email | TEXT | NULL |
| contact_phone | TEXT | NULL |
| default_lead_time_days | INTEGER | DEFAULT 0 |
| notes | TEXT | NULL |
| created_at | TIMESTAMP | NOT NULL DEFAULT NOW() |
| updated_at | TIMESTAMP | NOT NULL DEFAULT NOW() |

### 2.12 purchase_orders

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| order_number | TEXT | UNIQUE |
| supplier_id | BIGINT | FK -> suppliers(id) |
| status | TEXT | NOT NULL DEFAULT 'draft' |
| order_date | DATE | DEFAULT CURRENT_DATE |
| expected_date | DATE | NULL |
| total_cost | NUMERIC(18,6) | DEFAULT 0 |
| notes | TEXT | NULL |
| created_at | TIMESTAMP | NOT NULL DEFAULT NOW() |
| updated_at | TIMESTAMP | NOT NULL DEFAULT NOW() |

**Status values**: `draft`, `sent`, `partially_received`, `closed`, `cancelled`

### 2.13 purchase_order_lines

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| purchase_order_id | BIGINT | FK -> purchase_orders(id) ON DELETE CASCADE |
| inventory_item_id | BIGINT | FK -> inventory_items(id) |
| supplier_sku | TEXT | NULL |
| description | TEXT | NULL |
| quantity_ordered | NUMERIC(18,6) | NOT NULL DEFAULT 0 |
| quantity_received | NUMERIC(18,6) | NOT NULL DEFAULT 0 |
| quantity_cancelled | NUMERIC(18,6) | NOT NULL DEFAULT 0 |
| unit_cost | NUMERIC(18,6) | NOT NULL DEFAULT 0 |
| packs_ordered | NUMERIC(18,6) | DEFAULT 0 |
| pack_size | NUMERIC(18,6) | DEFAULT 0 |
| purchase_uom | TEXT | NULL |
| stock_uom | TEXT | NULL |
| expected_date | DATE | NULL |
| created_at | TIMESTAMP | NOT NULL DEFAULT NOW() |
| updated_at | TIMESTAMP | NOT NULL DEFAULT NOW() |

### 2.14 purchase_order_receipts

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| purchase_order_id | BIGINT | NOT NULL, FK -> purchase_orders(id) ON DELETE CASCADE |
| inventory_transaction_id | INTEGER | FK -> inventory_transactions(id) |
| reference | TEXT | NOT NULL |
| notes | TEXT | NULL |
| created_at | TIMESTAMP | NOT NULL DEFAULT NOW() |

### 2.15 purchase_order_receipt_lines

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| receipt_id | BIGINT | NOT NULL, FK -> purchase_order_receipts(id) ON DELETE CASCADE |
| purchase_order_line_id | BIGINT | NOT NULL, FK -> purchase_order_lines(id) ON DELETE CASCADE |
| quantity_received | NUMERIC(18,6) | NOT NULL DEFAULT 0 |
| quantity_cancelled | NUMERIC(18,6) | NOT NULL DEFAULT 0 |

### 2.16 storage_locations

| Column | Type | Constraints |
|--------|------|-------------|
| id | SERIAL | PRIMARY KEY |
| name | TEXT | NOT NULL |
| description | TEXT | NULL |
| is_active | BOOLEAN | NOT NULL DEFAULT TRUE |
| sort_order | INTEGER | NOT NULL DEFAULT 0 |
| aisle | TEXT | NULL |
| rack | TEXT | NULL |
| shelf | TEXT | NULL |
| bin | TEXT | NULL |
| created_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | NOT NULL DEFAULT CURRENT_TIMESTAMP |

**Unique Index**: `lower(name)`

### 2.17 inventory_item_locations

| Column | Type | Constraints |
|--------|------|-------------|
| id | SERIAL | PRIMARY KEY |
| inventory_item_id | INTEGER | NOT NULL, FK -> inventory_items(id) ON DELETE CASCADE |
| storage_location_id | INTEGER | NOT NULL, FK -> storage_locations(id) ON DELETE CASCADE |
| quantity | INTEGER | NOT NULL DEFAULT 0 |

**Unique constraint**: `(inventory_item_id, storage_location_id)`

### 2.18 inventory_systems

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| name | TEXT | NOT NULL, UNIQUE |
| manufacturer | TEXT | NOT NULL DEFAULT '' |
| system | TEXT | NOT NULL DEFAULT '' |
| default_glazing | NUMERIC(10,4) | NULL |
| default_frame_parts | JSONB | NOT NULL DEFAULT '[]' |
| default_door_parts | JSONB | NOT NULL DEFAULT '[]' |
| system_type | TEXT | DEFAULT 'framing' |
| created_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |

### 2.19 inventory_item_systems

| Column | Type | Constraints |
|--------|------|-------------|
| inventory_item_id | BIGINT | NOT NULL, FK -> inventory_items(id) ON DELETE CASCADE |
| system_id | BIGINT | NOT NULL, FK -> inventory_systems(id) ON DELETE CASCADE |

**Primary Key**: `(inventory_item_id, system_id)`

### 2.20 maintenance_machines

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| name | TEXT | NOT NULL |
| equipment_type | TEXT | NOT NULL |
| machine_type_id | BIGINT | FK -> maintenance_machine_types(id) |
| manufacturer | TEXT | NULL |
| model | TEXT | NULL |
| serial_number | TEXT | NULL |
| location | TEXT | NULL |
| documents | JSONB | NOT NULL DEFAULT '[]' |
| notes | TEXT | NULL |
| created_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |
| updated_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |

### 2.21 maintenance_machine_types

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| name | TEXT | NOT NULL, UNIQUE |
| created_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |
| updated_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |

### 2.22 maintenance_assets

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| name | TEXT | NOT NULL |
| description | TEXT | NULL |
| documents | JSONB | NOT NULL DEFAULT '[]' |
| notes | TEXT | NULL |
| created_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |
| updated_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |

### 2.23 maintenance_asset_machines

| Column | Type | Constraints |
|--------|------|-------------|
| asset_id | BIGINT | NOT NULL, FK -> maintenance_assets(id) ON DELETE CASCADE |
| machine_id | BIGINT | NOT NULL, FK -> maintenance_machines(id) ON DELETE CASCADE |

**Primary Key**: `(asset_id, machine_id)`

### 2.24 maintenance_tasks

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| machine_id | BIGINT | NOT NULL, FK -> maintenance_machines(id) ON DELETE CASCADE |
| title | TEXT | NOT NULL |
| description | TEXT | NULL |
| frequency | TEXT | NULL |
| assigned_to | TEXT | NULL |
| interval_count | INTEGER | CHECK (> 0) |
| interval_unit | TEXT | CHECK IN ('day','week','month','year') |
| start_date | DATE | NULL |
| last_completed_at | DATE | NULL |
| status | TEXT | NOT NULL DEFAULT 'active' |
| priority | TEXT | NOT NULL DEFAULT 'medium' |
| created_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |
| updated_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |

### 2.25 maintenance_records

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| machine_id | BIGINT | NOT NULL, FK -> maintenance_machines(id) ON DELETE CASCADE |
| task_id | BIGINT | FK -> maintenance_tasks(id) ON DELETE SET NULL |
| asset_id | BIGINT | FK -> maintenance_assets(id) ON DELETE SET NULL |
| performed_by | TEXT | NULL |
| performed_at | DATE | NULL |
| notes | TEXT | NULL |
| attachments | JSONB | NOT NULL DEFAULT '[]' |
| downtime_minutes | INTEGER | NULL |
| labor_hours | NUMERIC(10,2) | NULL |
| parts_used | JSONB | NOT NULL DEFAULT '[]' |
| created_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |

### 2.26 configurator_part_use_options

Self-referencing tree of part usage classifications.

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| name | TEXT | NOT NULL, UNIQUE |
| parent_id | BIGINT | FK -> configurator_part_use_options(id) ON DELETE SET NULL |

**Seeded hierarchy**: Door > (Interior Opening, Exterior Opening, Fire Rated, Pair Door, Single Door); Frame; Hardware > (Hardware Set, Door Hardware > Hinge > Butt Hinge > Heavy Duty); Accessory

### 2.27 configurator_part_profiles

| Column | Type | Constraints |
|--------|------|-------------|
| inventory_item_id | BIGINT | PRIMARY KEY, FK -> inventory_items(id) ON DELETE CASCADE |
| is_enabled | BOOLEAN | NOT NULL DEFAULT FALSE |
| part_type | TEXT | NULL, CHECK IN ('door','frame','hardware','accessory') |
| height_lz | NUMERIC(12,4) | NULL |
| depth_ly | NUMERIC(12,4) | NULL |
| created_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |

### 2.28 configurator_part_use_links

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | (retroactive) |
| inventory_item_id | BIGINT | NOT NULL, FK -> inventory_items(id) ON DELETE CASCADE |
| use_option_id | BIGINT | NOT NULL, FK -> configurator_part_use_options(id) ON DELETE CASCADE |

**Primary Key**: `(inventory_item_id, use_option_id)`

### 2.29 configurator_part_requirements

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | (retroactive) |
| inventory_item_id | BIGINT | NOT NULL, FK -> inventory_items(id) ON DELETE CASCADE |
| required_inventory_item_id | BIGINT | NOT NULL, FK -> inventory_items(id) ON DELETE CASCADE |
| quantity | INTEGER | NOT NULL DEFAULT 1, CHECK (> 0) |
| finish_policy | TEXT | DEFAULT 'fixed' |
| fixed_finish | TEXT | NULL |

**Primary Key**: `(inventory_item_id, required_inventory_item_id)`

### 2.30 configurator_jobs

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| job_number | TEXT | NOT NULL, UNIQUE |
| name | TEXT | NOT NULL |
| created_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |

### 2.31 configurator_configurations

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| name | TEXT | NOT NULL |
| job_id | BIGINT | FK -> configurator_jobs(id) ON DELETE SET NULL |
| job_scope | TEXT | NOT NULL DEFAULT 'door_and_frame' |
| quantity | INTEGER | NOT NULL DEFAULT 1 |
| status | TEXT | NOT NULL DEFAULT 'draft' |
| notes | TEXT | NULL |
| created_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |
| updated_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |

### 2.32 configurator_configuration_doors

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGSERIAL | PRIMARY KEY |
| configuration_id | BIGINT | NOT NULL, FK -> configurator_configurations(id) ON DELETE CASCADE |
| door_tag | TEXT | NOT NULL |
| created_at | TIMESTAMPTZ | NOT NULL DEFAULT NOW() |

**Unique constraint**: `(configuration_id, door_tag)`

### Relationship Diagram

```
inventory_items
  |-- 1:N --> job_reservation_items --> N:1 job_reservations
  |-- 1:N --> cycle_count_lines --> N:1 cycle_count_sessions
  |-- 1:N --> inventory_transaction_lines --> N:1 inventory_transactions
  |-- 1:N --> inventory_daily_usage
  |-- 1:N --> inventory_item_locations --> N:1 storage_locations
  |-- M:N --> inventory_systems (via inventory_item_systems)
  |-- N:1 --> suppliers (via supplier_id)
  |-- 1:N --> purchase_order_lines --> N:1 purchase_orders --> N:1 suppliers
  |-- 1:1 --> configurator_part_profiles
  |-- M:N --> configurator_part_use_options (via configurator_part_use_links)
  |-- 1:N --> configurator_part_requirements (as parent and required)

purchase_orders
  |-- 1:N --> purchase_order_lines
  |-- 1:N --> purchase_order_receipts --> N:1 inventory_transactions
  |              |-- 1:N --> purchase_order_receipt_lines --> N:1 purchase_order_lines

maintenance_machines
  |-- N:1 --> maintenance_machine_types
  |-- M:N --> maintenance_assets (via maintenance_asset_machines)
  |-- 1:N --> maintenance_tasks
  |-- 1:N --> maintenance_records

configurator_jobs --> 1:N --> configurator_configurations --> 1:N --> configurator_configuration_doors
configurator_part_use_options (self-referencing tree via parent_id)
```

---

## 3. ForgeDesk3 Schema Summary

- **Database**: SQLite (dev) / MySQL / PostgreSQL
- **Framework**: Laravel 11 (Eloquent ORM)
- **Total Tables**: ~30 (including Laravel system tables)

### Key Differences from ForgeDesk2

| Aspect | ForgeDesk2 | ForgeDesk3 |
|--------|-----------|-----------|
| **Core table** | `inventory_items` | `products` (expanded) |
| **Transactions** | Header/line pattern (2 tables) | Flat single table |
| **Configurator** | 7 dedicated tables | Fields merged into `products` + `required_parts` table |
| **Authentication** | None (Django admin) | Full user auth with sessions/tokens |
| **Soft deletes** | None | Widespread (`deleted_at`) |
| **Orders** | None | Full order management (`orders`, `order_items`) |
| **Tooling** | None | `machine_tooling` table + product tool fields |
| **PO Receipts** | Dedicated tables (receipts + receipt lines) | Handled via inventory transactions |
| **Daily Usage** | Dedicated table | Computed from `average_daily_use` on products |
| **Metrics** | Static `inventory_metrics` table | Computed dynamically |
| **Status enums** | Text with CHECK constraints | Laravel ENUM columns |

---

## 4. Schema Mapping: ForgeDesk2 to ForgeDesk3

### Direct Table Mappings

| ForgeDesk2 Table | ForgeDesk3 Table | Migration Complexity |
|-----------------|-----------------|---------------------|
| `inventory_items` | `products` | **Medium** - column renames + new fields |
| `suppliers` | `suppliers` | **Low** - add address fields |
| `job_reservations` | `job_reservations` | **Low** - status enum adjustment |
| `job_reservation_items` | `job_reservation_items` | **Low** - FK rename |
| `cycle_count_sessions` | `cycle_count_sessions` | **Medium** - restructured fields |
| `cycle_count_lines` | `cycle_count_items` | **Medium** - renamed + expanded |
| `purchase_orders` | `purchase_orders` | **Low** - field renames |
| `purchase_order_lines` | `purchase_order_items` | **Medium** - simplified fields |
| `storage_locations` | `storage_locations` | **Low** - field additions |
| `maintenance_machines` | `machines` | **Low** - renamed |
| `maintenance_machine_types` | `machine_types` | **Low** - renamed |
| `maintenance_assets` | `assets` | **Low** - renamed |
| `maintenance_asset_machines` | `asset_machine` | **Low** - renamed |
| `maintenance_tasks` | `maintenance_tasks` | **Low** - `assigned_to` text -> FK |
| `maintenance_records` | `maintenance_records` | **Low** - `performed_by` text -> FK |
| `configurator_part_requirements` | `required_parts` | **Medium** - column renames |
| `inventory_systems` | `categories` | **Medium** - structural change |
| `inventory_item_systems` | `category_product` | **Medium** - different pivot structure |

### Merged/Absorbed Tables

| ForgeDesk2 Table | Absorbed Into | Notes |
|-----------------|---------------|-------|
| `configurator_part_profiles` | `products` | `is_enabled` -> `configurator_available`, `part_type` -> `configurator_type`, `height_lz` -> `dimension_height`, `depth_ly` -> `dimension_depth` |
| `configurator_part_use_links` | `products` | `use_option_id` hierarchy -> `configurator_use_path` (VARCHAR) |
| `inventory_transaction_lines` | `inventory_transactions` | Flattened: each line becomes its own transaction row |
| `inventory_transactions` (headers) | `inventory_transactions` | `reference` -> `reference_number`, `notes` preserved |

### No Direct ForgeDesk3 Equivalent

| ForgeDesk2 Table | Disposition |
|-----------------|-------------|
| `inventory_metrics` | **Skip** - ForgeDesk3 computes KPIs dynamically |
| `inventory_daily_usage` | **Skip** - ForgeDesk3 uses `average_daily_use` on products (could import latest average) |
| `purchase_order_receipts` | **Flatten** - Create inventory_transactions of type 'receipt' |
| `purchase_order_receipt_lines` | **Flatten** - Use quantity data to set `quantity_received` on purchase_order_items |
| `configurator_part_use_options` | **Flatten** - Convert hierarchy to path strings in `products.configurator_use_path` |
| `configurator_jobs` | **Skip or custom** - No FD3 equivalent. Could store as a reference |
| `configurator_configurations` | **Skip or custom** - No FD3 equivalent |
| `configurator_configuration_doors` | **Skip or custom** - No FD3 equivalent |
| `inventory_item_commitments` (VIEW) | **Skip** - FD3 has its own `inventory_commitments` view |

### New in ForgeDesk3 (No ForgeDesk2 Source)

| Table | Notes |
|-------|-------|
| `users` | Must create a default admin user for migrated records |
| `orders`, `order_items` | Customer order system (new feature) |
| `committed_inventory` | Separate from job reservations (new feature) |
| `machine_tooling` | Tool lifecycle tracking (new feature) |
| `inventory_locations` | Replaces `inventory_item_locations` with expanded schema |

---

## 5. Migration Plan

### Prerequisites

1. **ForgeDesk3 database** must have all migrations run (`php artisan migrate`)
2. **ForgeDesk2 PostgreSQL** database must be accessible (read-only connection)
3. **Create a default admin user** in ForgeDesk3 for `user_id` foreign keys
4. **Backup both databases** before starting migration

### Phase 1: Foundation Tables (No Dependencies)

Import order within this phase does not matter. These tables have no foreign key dependencies on other migrated data.

#### Step 1.1: Create Default Admin User

ForgeDesk3 requires a `user_id` on many records. Create a system user for migrated data.

```php
User::create([
    'name' => 'System Migration',
    'email' => 'migration@forgedesk.local',
    'password' => bcrypt(Str::random(32)),
]);
```

#### Step 1.2: Import Suppliers

**Source**: `fd2.suppliers`
**Target**: `fd3.suppliers`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct (preserve IDs) |
| name | name | Direct |
| contact_name | contact_name | Direct |
| contact_email | contact_email | Direct |
| contact_phone | contact_phone | Direct |
| default_lead_time_days | default_lead_time_days | Direct |
| notes | notes | Direct |
| created_at | created_at | Direct |
| updated_at | updated_at | Direct |
| _(none)_ | code | Generate from name (e.g., first 6 chars uppercase) |
| _(none)_ | is_active | Default TRUE |
| _(none)_ | address, city, state, zip, country | Default NULL / 'USA' |

#### Step 1.3: Import Machine Types

**Source**: `fd2.maintenance_machine_types`
**Target**: `fd3.machine_types`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| name | name | Direct |
| created_at | created_at | Direct |
| updated_at | updated_at | Direct |

#### Step 1.4: Import Storage Locations

**Source**: `fd2.storage_locations`
**Target**: `fd3.storage_locations`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| name | name | Direct |
| description | description | Direct |
| is_active | is_active | Direct |
| sort_order | sort_order | Direct |
| aisle | aisle | Direct |
| rack | _(none)_ | Store in notes or map to bay |
| shelf | _(none)_ | Store in notes or map to level |
| bin | _(none)_ | Store in notes or map to position |
| created_at | created_at | Direct |
| updated_at | updated_at | Direct |
| _(none)_ | code | Generate from name |
| _(none)_ | type | Default 'bin' or infer from name |

**Note**: FD2 uses `rack/shelf/bin`, FD3 uses `bay/level/position`. Map `rack` -> `bay`, `shelf` -> `level`, `bin` -> `position`.

### Phase 2: Categories (From Systems)

#### Step 2.1: Import Inventory Systems as Categories

**Source**: `fd2.inventory_systems`
**Target**: `fd3.categories`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| name | name | Direct |
| manufacturer | _(store in description)_ | Concat into description |
| system | system | Direct |
| system_type | _(store in description)_ | Concat into description |
| _(none)_ | code | Generate from name |
| _(none)_ | is_active | Default TRUE |
| _(none)_ | sort_order | Default 0 |
| created_at | created_at | Direct |

**Note**: `default_glazing`, `default_frame_parts`, `default_door_parts` JSONB columns have no FD3 equivalent. Store in `description` as reference or create a separate reference document.

### Phase 3: Products (Core Data)

#### Step 3.1: Import Inventory Items as Products

**Source**: `fd2.inventory_items` LEFT JOIN `fd2.configurator_part_profiles`
**Target**: `fd3.products`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct (preserve IDs) |
| item | description | Direct |
| sku | sku | Direct |
| part_number | part_number | Direct |
| finish | finish | Direct |
| location | location | Direct |
| stock | quantity_on_hand | Direct |
| committed_qty | quantity_committed | Direct |
| on_order_qty | on_order_qty | Cast to INTEGER |
| safety_stock | safety_stock | Cast to INTEGER |
| min_order_qty | min_order_qty | Cast to INTEGER |
| order_multiple | order_multiple | Cast to INTEGER |
| pack_size | pack_size | Cast to INTEGER |
| purchase_uom | purchase_uom | Direct |
| stock_uom | stock_uom | Direct |
| status | status | Map: 'In Stock' -> 'in_stock', 'Low Stock' -> 'low_stock', 'Out of Stock' -> 'out_of_stock', 'Critical' -> 'critical' |
| supplier | _(legacy, skip)_ | Use supplier_id instead |
| supplier_id | supplier_id | Direct |
| supplier_contact | supplier_contact | Direct |
| supplier_sku | supplier_sku | Direct |
| reorder_point | reorder_point | Direct |
| lead_time_days | lead_time_days | Direct |
| average_daily_use | average_daily_use | Direct |
| _(none)_ | unit_cost | Default 0 |
| _(none)_ | unit_price | Default 0 |
| _(none)_ | unit_of_measure | Default 'EA' |
| _(none)_ | is_active | Default TRUE |
| _(none)_ | is_discontinued | Default FALSE |
| _(none)_ | minimum_quantity | Default 0 |

**Configurator fields** (from `configurator_part_profiles`):

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| cpp.is_enabled | configurator_available | Direct |
| cpp.part_type | configurator_type | Direct |
| cpp.height_lz | dimension_height | Direct |
| cpp.depth_ly | dimension_depth | Direct |

**Configurator use path** (from `configurator_part_use_links` + `configurator_part_use_options`):

Build the full path string by walking the `parent_id` chain for each item's linked use option. For example, if an item is linked to "Butt Hinge" which has parents "Hinge" -> "Door Hardware" -> "Hardware", the `configurator_use_path` would be `"Hardware > Door Hardware > Hinge > Butt Hinge"`.

#### Step 3.2: Import Category-Product Relationships

**Source**: `fd2.inventory_item_systems`
**Target**: `fd3.category_product`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| inventory_item_id | product_id | Direct (IDs preserved) |
| system_id | category_id | Direct (IDs preserved) |
| _(none)_ | is_primary | First system per product = TRUE, rest = FALSE |

### Phase 4: Inventory & Warehouse Data

#### Step 4.1: Import Inventory Item Locations

**Source**: `fd2.inventory_item_locations` JOIN `fd2.storage_locations`
**Target**: `fd3.inventory_locations`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| inventory_item_id | product_id | Direct |
| _(from storage_locations.name)_ | location | Lookup name from storage_locations |
| quantity | quantity | Direct |
| _(none)_ | quantity_committed | Default 0 |
| _(none)_ | is_primary | First location per product = TRUE |

#### Step 4.2: Import Inventory Transactions (Flattened)

**Source**: `fd2.inventory_transaction_lines` JOIN `fd2.inventory_transactions`
**Target**: `fd3.inventory_transactions`

Each FD2 transaction line becomes a separate FD3 transaction row.

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| itl.id | id | Direct |
| itl.inventory_item_id | product_id | Direct |
| _(infer from reference)_ | type | Parse from `it.reference` (e.g., 'PO-' -> 'receipt', 'CC-' -> 'cycle_count', 'ADJ-' -> 'adjustment') or default 'adjustment' |
| itl.quantity_change | quantity | Direct |
| itl.stock_before | quantity_before | Direct |
| itl.stock_after | quantity_after | Direct |
| it.reference | reference_number | Direct |
| itl.note OR it.notes | notes | Combine: `itl.note` + `it.notes` |
| _(migration user)_ | user_id | Default admin user ID |
| it.created_at | transaction_date | Direct |
| it.created_at | created_at | Direct |

### Phase 5: Job Reservations

#### Step 5.1: Import Job Reservations

**Source**: `fd2.job_reservations`
**Target**: `fd3.job_reservations`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| job_number | job_number | Direct |
| release_number | release_number | Direct |
| job_name | job_name | Direct |
| requested_by | requested_by | Direct |
| needed_by | needed_by | Direct |
| status | status | Map: 'committed' -> 'active' (FD3 drops 'committed' status) |
| notes | notes | Direct |
| created_at | created_at | Direct |
| updated_at | updated_at | Direct |

#### Step 5.2: Import Job Reservation Items

**Source**: `fd2.job_reservation_items`
**Target**: `fd3.job_reservation_items`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| reservation_id | reservation_id | Direct |
| inventory_item_id | product_id | Direct |
| requested_qty | requested_qty | Direct |
| committed_qty | committed_qty | Direct |
| consumed_qty | consumed_qty | Direct |

### Phase 6: Purchase Orders

#### Step 6.1: Import Purchase Orders

**Source**: `fd2.purchase_orders`
**Target**: `fd3.purchase_orders`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| order_number | po_number | Direct |
| supplier_id | supplier_id | Direct |
| status | status | Map: 'sent' -> 'submitted', 'closed' -> 'received' |
| order_date | order_date | Direct |
| expected_date | expected_date | Direct |
| total_cost | total_amount | Cast NUMERIC -> DECIMAL |
| notes | notes | Direct |
| _(migration user)_ | created_by | Default admin user ID |
| created_at | created_at | Direct |
| updated_at | updated_at | Direct |

#### Step 6.2: Import Purchase Order Lines

**Source**: `fd2.purchase_order_lines`
**Target**: `fd3.purchase_order_items`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| purchase_order_id | purchase_order_id | Direct |
| inventory_item_id | product_id | Direct |
| quantity_ordered | quantity_ordered | Cast to INTEGER |
| quantity_received | quantity_received | Cast to INTEGER |
| unit_cost | unit_cost | Cast NUMERIC -> DECIMAL |
| _(computed)_ | total_cost | `quantity_ordered * unit_cost` |
| _(none)_ | destination_location | Default NULL |
| description | notes | Direct |
| created_at | created_at | Direct |
| updated_at | updated_at | Direct |

**Note**: FD2 fields `quantity_cancelled`, `packs_ordered`, `pack_size`, `purchase_uom`, `stock_uom`, `supplier_sku`, `expected_date` have no FD3 equivalent on the line item. Capture in `notes` if valuable.

### Phase 7: Cycle Counts

#### Step 7.1: Import Cycle Count Sessions

**Source**: `fd2.cycle_count_sessions`
**Target**: `fd3.cycle_count_sessions`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| name | session_number | Direct |
| status | status | Map: 'in_progress' -> 'in_progress', 'completed' -> 'completed' |
| started_at | started_at | Direct |
| completed_at | completed_at | Direct |
| location_filter | location | Direct |
| _(none)_ | scheduled_date | Use `started_at::date` |
| _(migration user)_ | assigned_to | Default admin user ID |

#### Step 7.2: Import Cycle Count Lines

**Source**: `fd2.cycle_count_lines`
**Target**: `fd3.cycle_count_items`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| session_id | session_id | Direct |
| inventory_item_id | product_id | Direct |
| expected_qty | system_quantity | Direct |
| counted_qty | counted_quantity | Direct |
| variance | variance | Direct |
| counted_at | counted_at | Direct |
| note | count_notes | Direct |
| _(none)_ | variance_status | Compute: NULL counted_qty -> 'pending', variance=0 -> 'approved', else 'requires_review' |
| _(none)_ | adjustment_created | Default FALSE |
| is_skipped | _(none)_ | If TRUE, set `variance_status` = 'rejected' |
| _(migration user)_ | counted_by | Default admin user ID (if counted_qty not null) |

### Phase 8: Maintenance Domain

#### Step 8.1: Import Machines

**Source**: `fd2.maintenance_machines`
**Target**: `fd3.machines`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| name | name | Direct |
| equipment_type | equipment_type | Direct |
| machine_type_id | machine_type_id | Direct |
| manufacturer | manufacturer | Direct |
| model | model | Direct |
| serial_number | serial_number | Direct |
| location | location | Direct |
| documents | documents | Direct (JSONB -> JSON) |
| notes | notes | Direct |
| created_at | created_at | Direct |
| updated_at | updated_at | Direct |
| _(none)_ | total_downtime_minutes | Compute: `SUM(maintenance_records.downtime_minutes)` |
| _(none)_ | last_service_at | Compute: `MAX(maintenance_records.performed_at)` |

#### Step 8.2: Import Assets

**Source**: `fd2.maintenance_assets`
**Target**: `fd3.assets`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| name | name | Direct |
| description | description | Direct |
| documents | documents | Direct (JSONB -> JSON) |
| notes | notes | Direct |
| created_at | created_at | Direct |
| updated_at | updated_at | Direct |

#### Step 8.3: Import Asset-Machine Links

**Source**: `fd2.maintenance_asset_machines`
**Target**: `fd3.asset_machine`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| asset_id | asset_id | Direct |
| machine_id | machine_id | Direct |

#### Step 8.4: Import Maintenance Tasks

**Source**: `fd2.maintenance_tasks`
**Target**: `fd3.maintenance_tasks`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| machine_id | machine_id | Direct |
| title | title | Direct |
| description | description | Direct |
| frequency | frequency | Direct |
| assigned_to (TEXT) | assigned_to (FK) | Lookup user by name or default admin user |
| interval_count | interval_count | Direct |
| interval_unit | interval_unit | Direct |
| start_date | start_date | Direct |
| last_completed_at | _(none)_ | Compute from maintenance_records if needed |
| status | status | Direct |
| priority | priority | Direct |
| created_at | created_at | Direct |
| updated_at | updated_at | Direct |

#### Step 8.5: Import Maintenance Records

**Source**: `fd2.maintenance_records`
**Target**: `fd3.maintenance_records`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| machine_id | machine_id | Direct |
| task_id | task_id | Direct |
| asset_id | asset_id | Direct |
| performed_by (TEXT) | performed_by (FK) | Lookup user by name or default admin user |
| performed_at | performed_at | Direct |
| notes | notes | Direct |
| attachments | attachments | Direct (JSONB -> JSON) |
| downtime_minutes | downtime_minutes | Direct |
| labor_hours | labor_hours | Direct |
| parts_used | parts_used | Direct (JSONB -> JSON) |
| _(none)_ | tools_replaced | Default NULL |
| created_at | created_at | Direct |

### Phase 9: Required Parts

#### Step 9.1: Import Configurator Part Requirements

**Source**: `fd2.configurator_part_requirements`
**Target**: `fd3.required_parts`

| FD2 Column | FD3 Column | Transform |
|-----------|-----------|-----------|
| id | id | Direct |
| inventory_item_id | parent_product_id | Direct |
| required_inventory_item_id | required_product_id | Direct |
| quantity | quantity | Direct |
| finish_policy | finish_policy | Map: 'fixed' -> 'specific', keep others |
| fixed_finish | specific_finish | Direct |
| _(none)_ | sort_order | Default 0 |
| _(none)_ | is_optional | Default FALSE |

### Phase 10: Data Not Migrated (Archive)

The following ForgeDesk2 data does not have a direct target in ForgeDesk3. Export as CSV/JSON for archival reference:

1. **`inventory_metrics`** - Static dashboard KPIs (FD3 computes these)
2. **`inventory_daily_usage`** - Historical daily usage (latest average migrated to products)
3. **`purchase_order_receipts`** - Receipt headers (data captured in inventory transactions)
4. **`purchase_order_receipt_lines`** - Receipt details (quantities rolled into PO items)
5. **`configurator_part_use_options`** - Hierarchy (flattened into product path strings)
6. **`configurator_part_use_links`** - Links (flattened into product configurator_use_path)
7. **`configurator_jobs`** - Configurator job headers
8. **`configurator_configurations`** - Configuration records
9. **`configurator_configuration_doors`** - Door tag details

---

## 6. Migration Scripts & Queries

### Recommended Approach: Laravel Artisan Command

Create a migration command at `app/Console/Commands/MigrateFromForgeDesk2.php` that:

1. Connects to both databases (FD2 PostgreSQL source, FD3 target)
2. Runs each phase sequentially with transaction wrapping
3. Validates row counts after each phase
4. Logs all transformations and skipped records

### Database Connection Setup

Add a secondary database connection in `config/database.php`:

```php
'forgedesk2' => [
    'driver' => 'pgsql',
    'host' => env('FD2_DB_HOST', '127.0.0.1'),
    'port' => env('FD2_DB_PORT', '5432'),
    'database' => env('FD2_DB_DATABASE', 'forgedesk2'),
    'username' => env('FD2_DB_USERNAME', 'postgres'),
    'password' => env('FD2_DB_PASSWORD', ''),
],
```

### Example Migration Snippet: Products

```php
// Phase 3: Migrate inventory_items -> products
$fd2Items = DB::connection('forgedesk2')
    ->table('inventory_items')
    ->leftJoin('configurator_part_profiles', 'inventory_items.id', '=', 'configurator_part_profiles.inventory_item_id')
    ->select('inventory_items.*',
        'configurator_part_profiles.is_enabled',
        'configurator_part_profiles.part_type',
        'configurator_part_profiles.height_lz',
        'configurator_part_profiles.depth_ly')
    ->get();

foreach ($fd2Items->chunk(500) as $chunk) {
    $inserts = $chunk->map(function ($item) {
        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'part_number' => $item->part_number ?: null,
            'finish' => $item->finish,
            'description' => $item->item,
            'location' => $item->location,
            'quantity_on_hand' => $item->stock,
            'quantity_committed' => $item->committed_qty,
            'on_order_qty' => (int) $item->on_order_qty,
            'safety_stock' => (int) $item->safety_stock,
            'min_order_qty' => (int) $item->min_order_qty,
            'order_multiple' => (int) $item->order_multiple,
            'pack_size' => (int) $item->pack_size,
            'purchase_uom' => $item->purchase_uom ?: 'EA',
            'stock_uom' => $item->stock_uom ?: 'EA',
            'status' => $this->mapStatus($item->status),
            'supplier_id' => $item->supplier_id,
            'supplier_contact' => $item->supplier_contact,
            'supplier_sku' => $item->supplier_sku,
            'reorder_point' => $item->reorder_point,
            'lead_time_days' => $item->lead_time_days,
            'average_daily_use' => $item->average_daily_use,
            'configurator_available' => $item->is_enabled ?? false,
            'configurator_type' => $item->part_type,
            'dimension_height' => $item->height_lz,
            'dimension_depth' => $item->depth_ly,
            'unit_cost' => 0,
            'unit_price' => 0,
            'unit_of_measure' => 'EA',
            'is_active' => true,
            'is_discontinued' => false,
            'minimum_quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    })->toArray();

    DB::table('products')->insert($inserts);
}
```

### Status Mapping Helper

```php
private function mapStatus(string $fd2Status): string
{
    return match (strtolower(trim($fd2Status))) {
        'in stock' => 'in_stock',
        'low stock' => 'low_stock',
        'critical' => 'critical',
        'out of stock' => 'out_of_stock',
        default => 'in_stock',
    };
}

private function mapReservationStatus(string $fd2Status): string
{
    return match ($fd2Status) {
        'committed' => 'active',
        'draft', 'active', 'in_progress', 'fulfilled', 'on_hold', 'cancelled' => $fd2Status,
        default => 'active',
    };
}

private function mapPoStatus(string $fd2Status): string
{
    return match ($fd2Status) {
        'sent' => 'submitted',
        'closed' => 'received',
        'draft', 'partially_received', 'cancelled' => $fd2Status,
        default => 'draft',
    };
}
```

### Configurator Use Path Builder

```php
private function buildUsePaths(): array
{
    $options = DB::connection('forgedesk2')
        ->table('configurator_part_use_options')
        ->get()
        ->keyBy('id');

    $links = DB::connection('forgedesk2')
        ->table('configurator_part_use_links')
        ->get();

    $paths = [];
    foreach ($links as $link) {
        $chain = [];
        $current = $options->get($link->use_option_id);
        while ($current) {
            array_unshift($chain, $current->name);
            $current = $current->parent_id ? $options->get($current->parent_id) : null;
        }
        $path = implode(' > ', $chain);

        // Store highest-level path per item (or concatenate multiple)
        if (isset($paths[$link->inventory_item_id])) {
            $paths[$link->inventory_item_id] .= '; ' . $path;
        } else {
            $paths[$link->inventory_item_id] = $path;
        }
    }

    return $paths; // [inventory_item_id => "Hardware > Door Hardware > Hinge > Butt Hinge"]
}
```

---

## 7. Post-Migration Validation

### Row Count Verification

After migration, verify that all expected records were imported:

```php
$checks = [
    ['fd2' => 'suppliers', 'fd3' => 'suppliers'],
    ['fd2' => 'inventory_items', 'fd3' => 'products'],
    ['fd2' => 'maintenance_machine_types', 'fd3' => 'machine_types'],
    ['fd2' => 'storage_locations', 'fd3' => 'storage_locations'],
    ['fd2' => 'inventory_systems', 'fd3' => 'categories'],
    ['fd2' => 'inventory_item_systems', 'fd3' => 'category_product'],
    ['fd2' => 'inventory_item_locations', 'fd3' => 'inventory_locations'],
    ['fd2' => 'job_reservations', 'fd3' => 'job_reservations'],
    ['fd2' => 'job_reservation_items', 'fd3' => 'job_reservation_items'],
    ['fd2' => 'purchase_orders', 'fd3' => 'purchase_orders'],
    ['fd2' => 'purchase_order_lines', 'fd3' => 'purchase_order_items'],
    ['fd2' => 'cycle_count_sessions', 'fd3' => 'cycle_count_sessions'],
    ['fd2' => 'cycle_count_lines', 'fd3' => 'cycle_count_items'],
    ['fd2' => 'maintenance_machines', 'fd3' => 'machines'],
    ['fd2' => 'maintenance_assets', 'fd3' => 'assets'],
    ['fd2' => 'maintenance_asset_machines', 'fd3' => 'asset_machine'],
    ['fd2' => 'maintenance_tasks', 'fd3' => 'maintenance_tasks'],
    ['fd2' => 'maintenance_records', 'fd3' => 'maintenance_records'],
    ['fd2' => 'configurator_part_requirements', 'fd3' => 'required_parts'],
];

foreach ($checks as $check) {
    $fd2Count = DB::connection('forgedesk2')->table($check['fd2'])->count();
    $fd3Count = DB::table($check['fd3'])->count();
    $status = $fd2Count === $fd3Count ? 'PASS' : 'FAIL';
    Log::info("{$status}: {$check['fd2']} ({$fd2Count}) -> {$check['fd3']} ({$fd3Count})");
}
```

### Transaction Count (Special Case)

FD2 transaction lines become FD3 transaction rows (1:1 on lines, not headers):

```php
$fd2LineCount = DB::connection('forgedesk2')->table('inventory_transaction_lines')->count();
$fd3TxnCount = DB::table('inventory_transactions')->where('reference_number', 'LIKE', 'FD2-%')->count();
```

### Data Integrity Checks

1. **Stock consistency**: Verify `products.quantity_on_hand` matches FD2 `inventory_items.stock`
2. **Committed qty**: Verify `products.quantity_committed` matches aggregated active reservation commitments
3. **Supplier links**: Verify all `products.supplier_id` values reference valid `suppliers.id`
4. **Category links**: Verify all `category_product` entries reference valid product and category IDs
5. **Reservation totals**: Verify `SUM(job_reservation_items.committed_qty)` grouped by product matches FD2 totals
6. **PO totals**: Verify `purchase_orders.total_amount` matches `SUM(purchase_order_items.total_cost)` per PO

### Functional Smoke Tests

After migration, verify these key workflows:

1. **Product listing** - All products appear with correct SKU, description, stock levels
2. **Supplier drill-down** - Clicking a supplier shows its associated products
3. **Job reservation view** - Reservations show correct items and quantities
4. **Purchase order view** - POs show correct line items and totals
5. **Cycle count history** - Past sessions show correct counts and variances
6. **Machine maintenance** - Machines show correct tasks and maintenance history
7. **Dashboard KPIs** - Dynamically computed metrics match expected values
8. **Configurator** - Products with `configurator_available=true` show correct type and dimensions
