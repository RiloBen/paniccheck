<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Jika belum login atau role-nya tidak ada dalam daftar yang diizinkan, tendang keluar
        if (!Auth::check() || !in_array(Auth::user()->role, $roles)) {
            return response()->json([
                'message' => 'Akses ditolak. Anda tidak memiliki izin relawan/admin.'
            ], 403);
        }
        return $next($request);
    }
}
