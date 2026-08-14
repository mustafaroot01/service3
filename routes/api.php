<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| /api/v1/admin/...    — Admin Dashboard APIs
| /api/v1/customer/... — Mobile App APIs
| /api/v1/technician/...— Technician APIs
|
*/

Route::prefix('v1')->group(function () {
    // Admin APIs
    require __DIR__ . '/api/admin.php';
    
    // Customer APIs
    require __DIR__ . '/api/customer.php';
    
    // Technician APIs
    require __DIR__ . '/api/technician.php';
});
