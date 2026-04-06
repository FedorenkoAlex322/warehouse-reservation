<?php

use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['service' => 'warehouse-reservation', 'status' => 'ok']);
});

Route::post('/supplier/reserve', [SupplierController::class, 'reserve']);
Route::get('/supplier/status/{ref}', [SupplierController::class, 'status']);
