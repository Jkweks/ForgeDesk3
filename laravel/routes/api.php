<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ImportExportController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\MaintenanceTaskController;
use App\Http\Controllers\Api\MaintenanceRecordController;
use App\Http\Controllers\Api\InventoryLocationController;

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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('v1')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/inventory/{status}', [DashboardController::class, 'inventoryByStatus']);
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        
        // Products
        Route::apiResource('products', ProductController::class);
        Route::post('/products/{product}/adjust', [ProductController::class, 'adjustInventory']);
        Route::get('/products/{product}/transactions', [ProductController::class, 'getTransactions']);

        // Inventory Locations
        Route::get('/products/{product}/locations', [InventoryLocationController::class, 'index']);
        Route::post('/products/{product}/locations', [InventoryLocationController::class, 'store']);
        Route::put('/products/{product}/locations/{location}', [InventoryLocationController::class, 'update']);
        Route::delete('/products/{product}/locations/{location}', [InventoryLocationController::class, 'destroy']);
        Route::post('/products/{product}/locations/transfer', [InventoryLocationController::class, 'transfer']);
        Route::post('/products/{product}/locations/{location}/adjust', [InventoryLocationController::class, 'adjust']);
        Route::get('/products/{product}/locations/statistics', [InventoryLocationController::class, 'statistics']);
        Route::get('/locations', [InventoryLocationController::class, 'getAllLocations']);

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
    });
});