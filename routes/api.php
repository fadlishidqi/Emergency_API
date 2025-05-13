<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportController;

// Route publik
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/refresh', [AuthController::class, 'refreshToken']);

// Route terproteksi (untuk semua user terautentikasi)
Route::middleware('auth:sanctum')->group(function () {
    // Profil user yang login
    Route::get('/profile', [AuthController::class, 'profile']);
    
    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // User bisa melihat dan mengupdate profil mereka sendiri
    Route::get('/users/{id}', [AuthController::class, 'show']);
    Route::put('/users/{id}', [AuthController::class, 'update']);
    
    // ====== ROUTE UNTUK LAPORAN FOTO (BARU) ======
    // CRUD Laporan Foto
    Route::get('/reports', [ReportController::class, 'index']);
    Route::post('/reports', [ReportController::class, 'store']);
    Route::get('/reports/{report}', [ReportController::class, 'show']);
    Route::put('/reports/{report}', [ReportController::class, 'update']);
    Route::delete('/reports/{report}', [ReportController::class, 'destroy']);
    
    // Mendapatkan tipe masalah (untuk dropdown)
    Route::get('/problem-types', [ReportController::class, 'getProblemTypes']);
    // ============================================
    
    // Route khusus Relawan
    Route::middleware('relawan')->prefix('relawan')->group(function () {
        // Tambahkan rute spesifik untuk relawan di sini
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Relawan Dashboard']);
        });
    });
    
    // Route khusus Admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Manajemen User (hanya admin)
        Route::get('/users', [AuthController::class, 'getAllUsers']); // Regular users
        Route::get('/relawan', [AuthController::class, 'getAllRelawan']); // Relawan users
        Route::get('/all-users', [AuthController::class, 'index']); // All users
        
        // Register khusus admin dan relawan
        Route::post('/register-relawan', [AuthController::class, 'registerRelawan']);
        Route::post('/register-admin', [AuthController::class, 'registerAdmin']);
        
        // Delete user
        Route::delete('/users/{id}', [AuthController::class, 'destroy']);
        
        // Admin Dashboard
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Admin Dashboard']);
        });
    });
});