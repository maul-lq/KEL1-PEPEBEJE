<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/_boost/browser-logs', function () {
    return response()->json([
        'status' => 'active',
        'message' => 'Laravel Boost browser logger endpoint is active and accepts POST requests.',
    ]);
});
