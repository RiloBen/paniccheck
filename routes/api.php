<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AuthController;

// Rute untuk pendaftaran dan login
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rute untuk warga melakukan konfirmasi
Route::post('/events/{id}/confirm', [EventController::class, 'confirm']);

// Rute untuk relawan melakukan penutupan
Route::post('/events/{id}/resolve', [EventController::class, 'resolve']);

Route::post('/reports', [ReportController::class, 'store']);

// Rute untuk peta Rilo mengambil seluruh data kordinat banjir
Route::get('/events', [EventController::class, 'index']);
