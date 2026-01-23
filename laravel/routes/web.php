<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
});

// Inventory Management
Route::get('/categories', function () {
    return view('categories');
});

Route::get('/suppliers', function () {
    return view('suppliers');
});

Route::get('/low-stock', function () {
    return view('low-stock');
});

Route::get('/critical-stock', function () {
    return view('critical-stock');
});

// Operations
Route::get('/purchase-orders', function () {
    return view('purchase-orders');
});

Route::get('/cycle-counting', function () {
    return view('cycle-counting');
});

Route::get('/storage-locations', function () {
    return view('storage-locations');
});

Route::get('/transactions', function () {
    return view('transactions');
});

// Fulfillment
Route::get('/fulfillment/material-check', function () {
    return view('fulfillment.material-check');
});

// Reports & Maintenance
Route::get('/reports', function () {
    return view('reports');
});

Route::get('/maintenance', function () {
    return view('maintenance');
});
