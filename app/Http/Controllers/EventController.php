<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Report;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    // Fitur 1: Konfirmasi oleh warga lain
    public function confirm(Request $request, $id)
    {
        // Keamanan: Pastikan yang melakukan konfirmasi adalah warga yang sudah login
        if (!Auth::check()) {
            return response()->json(['message' => 'Harus login untuk melakukan konfirmasi'], 401);
        }

        $event = Event::find($id);
        if (!$event) {
            return response()->json(['message' => 'Event tidak ditemukan'], 404);
        }

        // Simpan konfirmasi ini sebagai laporan pendukung
        Report::create([
            'event_id' => $event->id,
            'user_id' => Auth::id(),
            'latitude' => $event->latitude, 
            'longitude' => $event->longitude,
            'category' => $event->category,
            'is_confirmation' => true,
            'description' => 'Konfirmasi silang warga: ' . $request->description,
        ]);

        // Perbarui waktu event agar tidak mati
        $event->touch();
        
        // Reaktivasi: Jika statusnya STALE, kembalikan ke ACTIVE karena kondisi ternyata masih banjir
        if ($event->status === 'STALE') {
            $event->update(['status' => 'ACTIVE']);
        }

        return response()->json(['message' => 'Konfirmasi berhasil ditambahkan dan status diperbarui!']);
    }

    // Fitur 2: Penutupan event oleh relawan
    public function resolve(Request $request, $id)
    {
        $user = Auth::user();

        // Keamanan Hak Akses: Cek apakah user sudah login dan perannya adalah relawan atau admin
        if (!$user || !in_array($user->role, ['relawan', 'admin'])) {
            return response()->json(['message' => 'Akses ditolak. Hanya relawan terverifikasi yang bisa menutup kejadian.'], 403);
        }

        $event = Event::find($id);
        if (!$event) {
            return response()->json(['message' => 'Event tidak ditemukan'], 404);
        }

        // Kunci status menjadi surut/selesai
        $event->update(['status' => 'RESOLVED']);

        return response()->json(['message' => 'Event berhasil ditutup dan ditandai RESOLVED.']);
    }
}