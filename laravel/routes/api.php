<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ImportExportController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\MaintenanceTaskController;
use App\Http\Controllers\Api\MaintenanceRecordController;
use App\Http\Controllers\Api\InventoryLocationController;
use App\Http\Controllers\Api\JobReservationController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\InventoryTransactionController;
use App\Http\Controllers\Api\RequiredPartsController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\CycleCountController;
use App\Http\Controllers\Api\MachineToolingController;
use App\Http\Controllers\Api\MaterialCheckController;

// Public test route (no auth required)
Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});

Route::get('/v1/test', function () {
    return response()->json([
        'message' => 'ForgeDesk API is working!',
        'version' => '1.0'
    ]);
});

// Public authentication routes
Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = \App\Models\User::where('email', $request->email)->first();

    if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    $token = $user->createToken('auth-token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => $user
    ]);
});

Route::post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Logged out']);
})->middleware('auth:sanctum');

// Fulfillment routes (public for internal use)
Route::prefix('v1')->group(function () {
    Route::get('/fulfillment/test', [MaterialCheckController::class, 'test']);
    Route::post('/fulfillment/material-check', [MaterialCheckController::class, 'checkMaterials']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1')->group(function () {
        // Current user
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/inventory/{status}', [DashboardController::class, 'inventoryByStatus']);
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        
        // Categories
        Route::apiResource('categories', CategoryController::class);
        Route::get('/categories-tree', [CategoryController::class, 'tree']);
        Route::get('/category-systems', [CategoryController::class, 'systems']);
        Route::post('/categories/sort-order', [CategoryController::class, 'updateSortOrder']);
        Route::post('/categories/bulk-action', [CategoryController::class, 'bulkAction']);

        // Suppliers
        Route::apiResource('suppliers', SupplierController::class);
        Route::get('/supplier-countries', [SupplierController::class, 'countries']);
        Route::get('/supplier-statistics', [SupplierController::class, 'statistics']);
        Route::get('/suppliers/{supplier}/products', [SupplierController::class, 'products']);
        Route::get('/suppliers/{supplier}/contacts', [SupplierController::class, 'contacts']);
        Route::get('/suppliers/{supplier}/low-stock-report', [SupplierController::class, 'lowStockReport']);
        Route::post('/suppliers/bulk-action', [SupplierController::class, 'bulkAction']);

        // Products
        Route::apiResource('products', ProductController::class);
        Route::post('/products/{product}/adjust', [ProductController::class, 'adjustInventory']);
        Route::post('/products/{product}/issue-to-job', [ProductController::class, 'issueToJob']);
        Route::get('/products/{product}/transactions', [ProductController::class, 'getTransactions']);
        Route::get('/products/{product}/calculate-reorder', [ProductController::class, 'calculateReorderPoint']);
        Route::get('/finish-codes', [ProductController::class, 'getFinishCodes']);
        Route::get('/unit-of-measures', [ProductController::class, 'getUnitOfMeasures']);

        // Inventory Locations
        Route::get('/products/{product}/locations', [InventoryLocationController::class, 'index']);
        Route::post('/products/{product}/locations', [InventoryLocationController::class, 'store']);
        Route::put('/products/{product}/locations/{location}', [InventoryLocationController::class, 'update']);
        Route::delete('/products/{product}/locations/{location}', [InventoryLocationController::class, 'destroy']);
        Route::post('/products/{product}/locations/transfer', [InventoryLocationController::class, 'transfer']);
        Route::post('/products/{product}/locations/{location}/adjust', [InventoryLocationController::class, 'adjust']);
        Route::get('/products/{product}/locations/statistics', [InventoryLocationController::class, 'statistics']);
        Route::get('/locations', [InventoryLocationController::class, 'getAllLocations']);

        // Storage Locations (Master Location Management)
        Route::apiResource('storage-locations', App\Http\Controllers\Api\StorageLocationController::class);
        Route::get('/storage-locations-stats', [App\Http\Controllers\Api\StorageLocationController::class, 'withStats']);
        Route::get('/storage-locations-names', [App\Http\Controllers\Api\StorageLocationController::class, 'locationNames']);

        // Job Reservations
        Route::get('/products/{product}/reservations', [JobReservationController::class, 'index']);
        Route::get('/products/{product}/reservations/active', [JobReservationController::class, 'active']);
        Route::post('/products/{product}/reservations', [JobReservationController::class, 'store']);
        Route::put('/products/{product}/reservations/{reservation}', [JobReservationController::class, 'update']);
        Route::post('/products/{product}/reservations/{reservation}/fulfill', [JobReservationController::class, 'fulfill']);
        Route::post('/products/{product}/reservations/{reservation}/release', [JobReservationController::class, 'release']);
        Route::delete('/products/{product}/reservations/{reservation}', [JobReservationController::class, 'destroy']);
        Route::get('/products/{product}/reservations/statistics', [JobReservationController::class, 'statistics']);
        Route::get('/jobs', [JobReservationController::class, 'getAllJobs']);

        // Inventory Transactions (Activity & Audit Trail)
        Route::get('/transactions', [InventoryTransactionController::class, 'index']);
        Route::post('/transactions/manual', [InventoryTransactionController::class, 'createManual']);
        Route::get('/transactions/{transaction}', [InventoryTransactionController::class, 'show']);
        Route::get('/transactions-statistics', [InventoryTransactionController::class, 'statistics']);
        Route::get('/transactions-types', [InventoryTransactionController::class, 'types']);
        Route::get('/transactions-export', [InventoryTransactionController::class, 'export']);
        Route::get('/transactions-recent', [InventoryTransactionController::class, 'recentActivity']);
        Route::get('/transactions-timeline', [InventoryTransactionController::class, 'timeline']);
        Route::get('/products/{product}/transactions', [InventoryTransactionController::class, 'productTransactions']);

        // Configurator & BOM (Required Parts)
        Route::get('/products/{product}/required-parts', [RequiredPartsController::class, 'index']);
        Route::post('/products/{product}/required-parts', [RequiredPartsController::class, 'store']);
        Route::put('/products/{product}/required-parts/{requiredPart}', [RequiredPartsController::class, 'update']);
        Route::delete('/products/{product}/required-parts/{requiredPart}', [RequiredPartsController::class, 'destroy']);
        Route::get('/products/{product}/bom-explosion', [RequiredPartsController::class, 'explosion']);
        Route::get('/products/{product}/bom-availability', [RequiredPartsController::class, 'checkAvailability']);
        Route::post('/products/{product}/required-parts/sort-order', [RequiredPartsController::class, 'updateSortOrder']);
        Route::get('/products/{product}/where-used', [RequiredPartsController::class, 'whereUsed']);

        // Reports & Analytics
        Route::get('/reports/low-stock', [ReportsController::class, 'lowStockReport']);
        Route::get('/reports/committed-parts', [ReportsController::class, 'committedPartsReport']);
        Route::get('/reports/velocity', [ReportsController::class, 'stockVelocityAnalysis']);
        Route::get('/reports/reorder-recommendations', [ReportsController::class, 'reorderRecommendations']);
        Route::get('/reports/obsolete', [ReportsController::class, 'obsoleteInventory']);
        Route::get('/reports/usage-analytics', [ReportsController::class, 'usageAnalytics']);
        Route::get('/reports/export', [ReportsController::class, 'exportReport']);

        // Purchase Orders
        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit']);
        Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
        Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
        Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
        Route::get('/purchase-orders-open', [PurchaseOrderController::class, 'open']);
        Route::get('/purchase-orders-statistics', [PurchaseOrderController::class, 'statistics']);

        // Cycle Counting
        Route::apiResource('cycle-counts', CycleCountController::class);
        Route::post('/cycle-counts/{cycleCountSession}/start', [CycleCountController::class, 'start']);
        Route::post('/cycle-counts/{cycleCountSession}/record-count', [CycleCountController::class, 'recordCount']);
        Route::post('/cycle-counts/{cycleCountSession}/approve-variances', [CycleCountController::class, 'approveVariances']);
        Route::post('/cycle-counts/{cycleCountSession}/complete', [CycleCountController::class, 'complete']);
        Route::post('/cycle-counts/{cycleCountSession}/cancel', [CycleCountController::class, 'cancel']);
        Route::get('/cycle-counts/{cycleCountSession}/variance-report', [CycleCountController::class, 'varianceReport']);
        Route::get('/cycle-counts-active', [CycleCountController::class, 'active']);
        Route::get('/cycle-counts-statistics', [CycleCountController::class, 'statistics']);

        // Orders
        Route::apiResource('orders', OrderController::class);
        Route::post('/orders/{order}/commit', [OrderController::class, 'commitInventory']);
        Route::post('/orders/{order}/release', [OrderController::class, 'releaseInventory']);
        Route::post('/orders/{order}/ship', [OrderController::class, 'shipOrder']);
        
        // Import/Export
        Route::post('/import/products', [ImportExportController::class, 'importProducts']);
        Route::get('/export/products', [ImportExportController::class, 'exportProducts']);
        Route::get('/export/template', [ImportExportController::class, 'downloadTemplate']);

        // Maintenance
        Route::get('/maintenance/dashboard', [MaintenanceController::class, 'dashboard']);
        Route::get('/maintenance/upcoming-tasks', [MaintenanceController::class, 'upcomingTasks']);
        Route::get('/maintenance/recent-records', [MaintenanceController::class, 'recentRecords']);

        // Machines
        Route::apiResource('machines', MachineController::class);
        Route::get('/machine-types', [MachineController::class, 'getTypes']);

        // Assets
        Route::apiResource('assets', AssetController::class);

        // Maintenance Tasks
        Route::apiResource('maintenance-tasks', MaintenanceTaskController::class);

        // Maintenance Records
        Route::apiResource('maintenance-records', MaintenanceRecordController::class);

        // Machine Tooling
        Route::get('/machine-tooling/inventory', [MachineToolingController::class, 'inventory']);
        Route::get('/machine-tooling/all', [MachineToolingController::class, 'all']);
        Route::get('/machine-tooling/statistics', [MachineToolingController::class, 'statistics']);
        Route::get('/machine-tooling/tool-life-units', [MachineToolingController::class, 'toolLifeUnits']);
        Route::get('/machine-tooling/tool-types', [MachineToolingController::class, 'toolTypes']);
        Route::get('/machines/{machine}/tooling', [MachineToolingController::class, 'index']);
        Route::post('/machines/{machine}/tooling', [MachineToolingController::class, 'store']);
        Route::get('/machines/{machine}/tooling/compatible-tools', [MachineToolingController::class, 'compatibleTools']);
        Route::get('/machine-tooling/{id}', [MachineToolingController::class, 'show']);
        Route::put('/machine-tooling/{id}/update-life', [MachineToolingController::class, 'updateToolLife']);
        Route::post('/machine-tooling/{id}/replace', [MachineToolingController::class, 'replace']);
        Route::post('/machine-tooling/{id}/remove', [MachineToolingController::class, 'remove']);
    });
});