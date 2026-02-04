# ForgeDesk3 Performance Issues

**Branch:** `claude/fix-performance-modals-pdf-n449l`
**Date:** 2026-02-04

## Overview

This document catalogs performance bottlenecks causing:
- **Modal lag:** Modals take ~1 second to open
- **Slow PDF generation:** Reports take significant time to prepare

---

## 1. Modal Performance Issues

### 1.1 Hard-coded Delays

**File:** `laravel/public/tooling.js:180`

```javascript
setTimeout(() => {
  const productSelect = document.getElementById('installToolProduct');
  if (productSelect) {
    productSelect.value = productId;
  }
}, 500);  // ⚠️ Unnecessary 500ms delay on every install modal open
```

**Impact:** Every time the install tool modal opens, there's an artificial half-second delay
**Fix:** Remove setTimeout and set value directly

---

### 1.2 Multiple Synchronous API Calls on Modal Open

**File:** `laravel/public/tooling.js` - `openAddToolingProductModal()`

```javascript
await Promise.all([
  loadCategoriesForTooling(),    // GET /categories?per_page=all
  loadSuppliersForTooling(),     // GET /suppliers?per_page=all
  loadMachineTypesForTooling()   // GET /machine-types
]);
```

**Impact:** Modal doesn't open until all 3 API calls complete. With hundreds of categories/suppliers, this can take 1-2+ seconds
**Fix:**
- Add loading spinner
- Lazy load dropdowns (search-based)
- Cache results in sessionStorage/localStorage

---

### 1.3 API Calls Without Loading Indicators

**Affected Modals:**

| Modal | File | Function | API Call |
|-------|------|----------|----------|
| Receive PO | purchase-orders.blade.php:764 | `showReceiveModal(poId)` | `GET /purchase-orders/{poId}` |
| Complete Job | job-reservations.blade.php:77 | `showCompleteModal(id)` | `GET /api/v1/job-reservations/{id}` |
| Replace Tool | tooling.js:321 | `openReplaceToolModal(toolingId)` | `GET /machine-tooling/compatible-replacements/{id}` |
| View Tool Details | tooling.js:367 | `viewToolDetails(toolingId)` | `GET /machine-tooling/{id}` |

**Impact:** User clicks button, nothing happens visibly for 0.5-2 seconds, then modal appears
**Fix:** Add loading state to buttons or show loading overlay

---

### 1.4 Heavy Dropdown Population

**File:** `laravel/public/tooling.js`

```javascript
// Loads ALL categories, suppliers, machine types at once
async function loadCategoriesForTooling() {
  const response = await authenticatedFetch('/categories?per_page=all');
  // Populates dropdown with potentially hundreds of options
}
```

**Impact:**
- Large DOM manipulation on modal open
- Downloads/processes entire datasets unnecessarily
- No search/filter capability for large lists

**Fix:**
- Implement search-based select (Select2, Choices.js)
- Load on-demand with pagination
- Cache loaded options

---

### 1.5 Multiple getElementById() Calls on Form Reset

**File:** `laravel/public/maintenance.js` - `openMachineModal()`, `openAssetModal()`, etc.

```javascript
document.getElementById('machineModalTitle').textContent = id ? 'Edit Machine' : 'Add Machine';
document.getElementById('machineId').value = id || '';
document.getElementById('machineName').value = machine?.name || '';
document.getElementById('machineType').value = machine?.type || '';
// ... 10+ more getElementById calls
```

**Impact:** Synchronous DOM lookups on every modal open
**Fix:** Cache element references or use FormData reset

---

## 2. PDF Report Performance Issues (CRITICAL)

### 2.1 N+1 Query Problems

**File:** `laravel/app/Http/Controllers/ReportsController.php`

#### A. Velocity Analysis Report (lines 125-178)

```php
public function velocityAnalysis(Request $request)
{
    $products = Product::where('is_active', true)->get();  // Query 1: Get all products

    foreach ($products as $product) {
        // Query 2, 3, 4... N: Query transactions for EACH product
        $transactions = InventoryTransaction::where('product_id', $product->id)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->get();
    }
}
```

**Impact:** For 1,000 products = **1,001 database queries**
**Estimated Time:** 5-10+ seconds for large datasets

**Fix:**
```php
$products = Product::where('is_active', true)
    ->with(['transactions' => function($query) use ($startDate, $endDate) {
        $query->whereBetween('created_at', [$startDate, $endDate]);
    }])
    ->get();  // Now only 1 query with JOIN
```

---

#### B. Obsolete Inventory Report (lines 237-288)

```php
$products = Product::all()->filter(function($product) use ($cutoffDate) {
    // Query 2: Last transaction for this product
    $lastTransaction = InventoryTransaction::where('product_id', $product->id)
        ->latest('created_at')
        ->first();

    // Query 3: Recent activity check
    $recentActivity = InventoryTransaction::where('product_id', $product->id)
        ->where('created_at', '>=', $cutoffDate)
        ->exists();
});
```

**Impact:** For 1,000 products = **2,001 database queries** (1 + 2 per product)
**Estimated Time:** 10-20+ seconds

**Fix:**
```php
$products = Product::with([
    'transactions' => function($query) {
        $query->latest('created_at');
    }
])->get()->filter(function($product) use ($cutoffDate) {
    $lastTransaction = $product->transactions->first();
    $recentActivity = $product->transactions->where('created_at', '>=', $cutoffDate)->isNotEmpty();
});
```

---

#### C. Stock Movement Report (lines 185-235)

```php
$products = Product::all();  // Query 1

foreach ($products as $product) {
    // Query 2, 3, 4... N: Get transactions for each product
    $transactions = InventoryTransaction::where('product_id', $product->id)
        ->whereBetween('created_at', [$startDate, $endDate])
        ->get();
}
```

**Impact:** For 1,000 products = **1,001 queries**

**Fix:** Same eager loading approach as velocity analysis

---

#### D. Reorder Report (lines 291-363)

```php
$products = Product::with(['category', 'supplier'])->get();  // Query 1

foreach ($products as $product) {
    // Multiple loops through inventories for each product
    $totalQuantity = $product->inventories->sum(function($inv) {
        // Heavy calculations per inventory item
    });
}
```

**Impact:**
- Loads ALL products at once into memory
- Complex pack/each conversions in nested loops
- No early filtering of products that don't need reordering

**Fix:**
- Filter products by `reorder_point > 0` first
- Pre-calculate inventory totals in database
- Consider materialized view for stock levels

---

### 2.2 No Pagination or Chunking

**All Reports:**
- Use `.get()` or `.all()` to load entire datasets
- No limit on number of products/transactions processed
- All data loaded into PHP memory at once

**Impact:**
- Memory exhaustion with large datasets (10,000+ products)
- Slow processing as dataset grows
- No ability to show partial results

**Fix:**
- Implement chunking: `Product::chunk(100, function($products) { ... })`
- Add date range limits
- Paginate results in PDF

---

### 2.3 Synchronous PDF Generation

**File:** `laravel/app/Http/Controllers/ReportsController.php`

All report methods generate PDFs synchronously:
```php
public function velocityAnalysis(Request $request)
{
    // ... heavy database queries ...
    // ... complex calculations ...

    $pdf = PDF::loadView('reports.velocity-analysis', compact('velocityData'));
    return $pdf->download('velocity-analysis.pdf');  // Blocks until complete
}
```

**Impact:**
- HTTP request times out on large reports (30-60+ second PHP execution)
- User sees loading spinner indefinitely
- Server resources blocked during generation
- No way to cancel/retry

**Fix:**
- Move to Laravel queue: `GeneratePdfReport::dispatch($type, $params)`
- Store PDF in storage and provide download link
- Show progress via websockets or polling
- Email PDF when complete for very large reports

---

### 2.4 Heavy In-Memory Processing

**Examples from ReportsController.php:**

```php
// Velocity Analysis - Multiple collection operations
$velocityData = $products->map(function($product) use ($transactions) {
    return [
        'usage' => $transactions->where('product_id', $product->id)
            ->where('quantity_change', '<', 0)
            ->sum('quantity_change'),
        'receipts' => $transactions->where('product_id', $product->id)
            ->where('quantity_change', '>', 0)
            ->sum('quantity_change'),
    ];
})->filter(function($item) {
    return $item['usage'] != 0;
});

// Reorder Report - Complex pack/each calculations
foreach ($products as $product) {
    $totalQuantity = $product->inventories->sum(function($inv) {
        if ($product->stock_uom === 'pack') {
            return ($inv->quantity_packs * $product->pack_size) + $inv->quantity_each;
        } else {
            return $inv->quantity_each + ($inv->quantity_packs * $product->pack_size);
        }
    });
}
```

**Impact:**
- Multiple iterations over same collections
- Complex calculations repeated per product
- No database-level aggregation

**Fix:**
- Use database aggregations (SUM, COUNT)
- Create indexed views for common calculations
- Cache intermediate results

---

### 2.5 Frontend: No Progress Indication

**File:** `laravel/resources/views/reports.blade.php:1032-1075`

```javascript
async function exportPdf(reportType) {
    const notification = showNotification('Generating PDF...', 'info', false);

    try {
        const response = await authenticatedFetch(url);
        const blob = await response.blob();
        // Download
    } catch (error) {
        showNotification('Failed to generate PDF', 'danger');
    }
}
```

**Impact:**
- User sees "Generating PDF..." but no progress
- No indication of how long it will take
- Can't tell if request is stuck or progressing

**Fix:**
- Add progress bar with estimates
- Show "This may take 1-2 minutes for large datasets"
- Allow cancellation
- Move to background job with status polling

---

## 3. Additional Performance Concerns

### 3.1 Bootstrap Modal Initialization

**File:** `laravel/resources/views/partials/auth-scripts.blade.php:6-65`

```javascript
function showModal(modalElement) {
    if (window.bootstrap && window.bootstrap.Modal) {
        const modal = new window.bootstrap.Modal(modalElement);
        modal.show();
    } else {
        // Manual fallback - creates backdrop, sets styles
    }
}
```

**Impact:**
- Creates new Modal instance every time
- Could cache instances instead

**Fix:** Cache modal instances, reuse them

---

### 3.2 Lack of Loading States

**Files:** Most modal implementations

- No loading spinners during async operations
- Buttons remain clickable during processing
- No visual feedback for user actions

**Fix:**
- Add `disabled` state to buttons during processing
- Show spinners in button or modal overlay
- Disable form during submission

---

## 4. Priority Recommendations

### High Priority (Fix First)

1. **Fix N+1 queries in all PDF reports** (ReportsController.php)
   - Velocity Analysis: lines 125-178
   - Stock Movement: lines 185-235
   - Obsolete Inventory: lines 237-288
   - Reorder Report: lines 291-363

2. **Remove 500ms setTimeout** (tooling.js:180)

3. **Add loading indicators to all async modal operations**

### Medium Priority

4. **Move PDF generation to background jobs**
5. **Implement lazy loading for dropdowns** (categories, suppliers, machine types)
6. **Add pagination/chunking to reports**

### Low Priority

7. **Cache modal Bootstrap instances**
8. **Cache dropdown data in localStorage**
9. **Add progress bars for PDF generation**

---

## 5. Testing Plan

After fixes are implemented, test:

1. **Modal Speed:**
   - Time to open each modal (should be < 200ms)
   - Network tab: count API requests on modal open

2. **PDF Generation:**
   - Generate velocity report with 1,000+ products
   - Check database query count (should be < 10 queries)
   - Time to generate (target: < 5 seconds or background job)

3. **Memory Usage:**
   - Monitor PHP memory during large PDF generation
   - Ensure no memory exhaustion errors

---

## 6. File Reference Index

### PHP Files
- `laravel/app/Http/Controllers/ReportsController.php` - All PDF generation (lines 125-363)

### JavaScript Files
- `laravel/public/tooling.js` - Tool modal operations (lines 180, 321, 367)
- `laravel/public/maintenance.js` - Maintenance modal operations

### Blade Templates
- `laravel/resources/views/purchase-orders.blade.php` - PO receive modal (line 764)
- `laravel/resources/views/fulfillment/job-reservations.blade.php` - Complete modal (line 77)
- `laravel/resources/views/reports.blade.php` - PDF export frontend (lines 1032-1075)
- `laravel/resources/views/partials/auth-scripts.blade.php` - Modal helpers (lines 6-65)

---

## 7. Estimated Impact

### Before Fixes
- Modal open time: 0.5-2 seconds
- PDF generation (1,000 products): 10-30+ seconds
- Database queries per report: 1,000-2,000+

### After Fixes
- Modal open time: < 200ms
- PDF generation (1,000 products): < 5 seconds or background job
- Database queries per report: < 10

**Expected User Experience Improvement:** 5-10x faster modals, 2-6x faster PDF generation
