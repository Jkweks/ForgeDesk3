<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
});

Route::get('/categories', function () {
    return view('categories');
});

Route::get('/suppliers', function () {
    return view('suppliers');
});

Route::get('/reports', function () {
    return view('reports');
});

Route::get('/maintenance', function () {
    return view('maintenance');
});
