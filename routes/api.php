<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\LeaveRequestController;
use App\Http\Controllers\API\LeaveBalanceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
    
    // Leave Request routes
    Route::prefix('leave-requests')->group(function () {
        Route::get('/', [LeaveRequestController::class, 'index']);
        Route::post('/', [LeaveRequestController::class, 'store']);
        Route::get('/{id}', [LeaveRequestController::class, 'show']);
        
        // Approval/Rejection routes (HR and Admin only)
        Route::post('/{id}/approve', [LeaveRequestController::class, 'approve'])
            ->middleware('role:hr,admin');
        Route::post('/{id}/reject', [LeaveRequestController::class, 'reject'])
            ->middleware('role:hr,admin');
    });
    
    // Leave Balance routes
    Route::prefix('leave-balance')->group(function () {
        Route::get('/', [LeaveBalanceController::class, 'index']);
        Route::get('/all', [LeaveBalanceController::class, 'all'])
            ->middleware('role:hr,admin');
    });
});

