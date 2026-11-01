<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Pastikan user sudah login
        if (!Auth::check()) {
            return response()->json(['message' => 'Anda belum login.'], 401);
        }

        $user = Auth::user();

        // Cek apakah jabatan user ada di dalam daftar yang diizinkan (misal: 'relawan' atau 'admin')
        if (!in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'Akses ditolak. Fitur ini khusus untuk Relawan/Admin.'
            ], 403);
        }

        // Jika jabatannya cocok, silakan masuk ke fungsi selanjutnya
        return $next($request);
    }
}