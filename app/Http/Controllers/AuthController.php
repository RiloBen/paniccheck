<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Fitur daftar akun baru (Warga)
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user', // Otomatis menjadi warga biasa saat daftar
        ]);

        // Membuat karcis masuk (token)
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Pendaftaran berhasil',
            'user' => $user,
            'access_token' => $token
        ]);
    }

    // Fitur masuk ke akun (Login)
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        // Cek apakah email ada dan password cocok
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau password salah'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'user' => $user,
            'access_token' => $token
        ]);
    }

    // Fitur keluar dari akun (Logout)
    public function logout(Request $request)
    {
        // Menghancurkan token yang sedang dipakai pada perangkat saat ini
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Berhasil logout, akses token telah dihancurkan.'
        ]);
    }

    // Fitur Warga mendaftar/naik pangkat menjadi Relawan
    public function upgradeToRelawan(Request $request)
    {
        $user = $request->user(); // Ambil data user yang sedang login

        // Cek jika dia sudah jadi relawan atau admin, tolak permintaannya
        if ($user->role === 'relawan' || $user->role === 'admin') {
            return response()->json([
                'message' => 'Akun Anda sudah memiliki akses khusus.'
            ], 400);
        }

        // Ubah pangkatnya di database
        $user->update([
            'role' => 'relawan'
        ]);

        return response()->json([
            'message' => 'Selamat! Akun Anda telah ditingkatkan menjadi Relawan.',
            'user' => $user
        ]);
    }
}