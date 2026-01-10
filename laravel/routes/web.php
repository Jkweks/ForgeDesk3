<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
});

Route::get('/categories', function () {
    return view('categories');
});

Route::get('/maintenance', function () {
    return view('maintenance');
});
