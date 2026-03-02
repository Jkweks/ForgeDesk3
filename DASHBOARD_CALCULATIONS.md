# Dashboard Calculation Reference

## Data Source

All dashboard data is served from `GET /dashboard` → `DashboardController@index`.

The response contains:
- `stats` — KPI summary metrics
- `inventory` — Paginated product list with calculated quantities
- `low_stock` / `critical_stock` — Filtered subsets
- `committed_parts` — Products with active commitments

---

## KPI Cards

### SKUs Tracked
```
COUNT(products) WHERE is_active = true
```
Filtered further by `category_id` or `search` if provided.

---

### Units On Hand
Calculated via `calculateUnitsAsPacks()`:
```
For each product:
  if pack_size > 1:  floor(quantity_on_hand / pack_size)  ← full packs only
  else:              quantity_on_hand                     ← raw eaches

KPI = SUM of all products
```

---

### Units Committed
Calculated via `calculateCommittedAsPacks()`:
```
Source: job_reservation_items.committed_qty
Only from reservations with status: active | in_progress | on_hold
(Excludes: draft, fulfilled, cancelled)

For each product:
  if pack_size > 1:  ceil(committed_qty / pack_size)  ← packs NEEDED to cover
  else:              committed_qty                     ← raw eaches

KPI = SUM of all products
```
> **Note:** Uses `ceil` (not `floor`) because you need complete packs to fulfill a commitment.

---

### Units Available
```
max(0, Units On Hand - Units Committed)
```
Never goes negative.

---

### Low Stock Alerts
```
COUNT(products) WHERE status IN ('low_stock', 'critical')
```

### Critical Count
```
COUNT(products) WHERE status = 'critical'
```

### Pending Orders
```
COUNT(orders) WHERE status IN ('pending', 'processing')
```
No category filter applied — counts all orders.

---

## Inventory Table — Per-Row Calculations

Each product row is enriched with:

| Column | Formula |
|--------|---------|
| `quantity_committed` | SUM of `committed_qty` from active reservation items (raw eaches) |
| `quantity_committed_packs` | `ceil(committed_qty / pack_size)` |
| `quantity_available` | `quantity_on_hand - quantity_committed` (eaches) |
| `quantity_available_packs` | `max(0, on_hand_packs - committed_packs)` |
| `quantity_on_hand_packs` | `floor(quantity_on_hand / pack_size)` |

---

## Status Badges

### Status Determination (`Product::updateStatus()`)
Calculated against **available quantity** (eaches):

| Status | Condition |
|--------|-----------|
| `out_of_stock` | `available ≤ 0` |
| `critical` | `available ≤ (minimum_quantity × 0.5)` |
| `low_stock` | `available ≤ minimum_quantity` |
| `in_stock` | `available > minimum_quantity` |

Status is written to `products.status` and recalculated whenever inventory or reservation items change.

### On Order Badge
Shown **in addition to** the status badge:
```
if on_order_qty > 0 → show "On Order" badge
```

---

## Pack Size Logic

Two different rounding rules depending on context:

| Context | Method | Rounding | Reason |
|---------|--------|----------|--------|
| On Hand | `eachesToFullPacks()` | `floor` | Only count complete packs you actually have |
| Committed | `eachesToPacksNeeded()` | `ceil` | Need full packs to cover the requirement |

**Example:**
```
pack_size = 50, on_hand = 137, committed = 67

On-hand packs:    floor(137 / 50) = 2 packs
Committed packs:  ceil(67 / 50)   = 2 packs needed
Available packs:  max(0, 2 - 2)   = 0 packs
Available eaches: 137 - 67        = 70 eaches (but 0 complete packs)
```

---

## Committed Quantity — Data Source Detail

Committed quantities come **directly from `job_reservation_items`** at query time, not from the cached `products.quantity_committed` column.

```sql
SELECT SUM(ri.committed_qty)
FROM job_reservation_items ri
JOIN job_reservations r ON ri.reservation_id = r.id
WHERE r.status IN ('active', 'in_progress', 'on_hold')
  AND r.deleted_at IS NULL
  AND p.is_active = true
```

The `products.quantity_committed` field is a **synced cache** — it's updated automatically via model observers whenever:
- A `JobReservationItem` is saved or deleted
- A `JobReservation` status changes

---

## Filtering & Sorting

**Filters:**
- Category → via `category_product` pivot
- Search → case-insensitive match on `sku`, `description`, `part_number`
- Status tab → All / Low Stock / Critical

**Sorting:**
- DB fields (`sku`, `description`, `quantity_on_hand`, `status`) → direct `ORDER BY`
- Calculated fields (`quantity_committed`, `quantity_available`) → fetch all → sort in PHP → manual paginate

**Pagination:** 50 per page (default), configurable via `per_page` param.
