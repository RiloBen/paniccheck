<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\QuickCheckController;

// Rute publik untuk pendaftaran dan login
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rute pelaporan darurat warga tanpa login (M2)
Route::post('/reports', [ReportController::class, 'store']);

// Rute untuk peta Rilo mengambil seluruh data kordinat banjir (M4)
Route::get('/events', [EventController::class, 'index']);

// Rute Quick Check Scanner (M10) - Bisa diakses publik tanpa login
Route::post('/quick-check', [QuickCheckController::class, 'scan']);

// Rute yang membutuhkan otentikasi Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Warga login melakukan konfirmasi silang (M6)
    Route::post('/events/{id}/confirm', [EventController::class, 'confirm']);

    // Melihat riwayat laporan pribadi
    Route::get('/my-reports', [ReportController::class, 'history']);

    // Penutupan event banjir hanya oleh relawan terverifikasi atau admin (M6)
    Route::middleware('role:admin,relawan')
        ->post('/events/{id}/resolve', [EventController::class, 'resolve']);
});

// Rute simulator demo juri & dev mode (M7 - Proteksi di level Controller: Admin / Dev Mode)
Route::post('/demo/fast-forward', [DemoController::class, 'fastForward']);
Route::post('/demo/reset', [DemoController::class, 'reset']);
