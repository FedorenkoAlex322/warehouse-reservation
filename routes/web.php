<?php

use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/supplier/reserve', [SupplierController::class, 'reserve']);
Route::get('/supplier/status/{ref}', [SupplierController::class, 'status']);
