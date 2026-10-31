<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\EventController;

// Rute untuk warga melakukan konfirmasi
Route::post('/events/{id}/confirm', [EventController::class, 'confirm']);

// Rute untuk relawan melakukan penutupan
Route::post('/events/{id}/resolve', [EventController::class, 'resolve']);

Route::post('/reports', [ReportController::class, 'store']);