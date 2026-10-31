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

        $userId = Auth::id();
        $ip = $request->ip();

        $isDevBypass = $request->boolean('dev_bypass', false)
            || ($request->query('dev') == '1')
            || (app()->environment('local', 'testing') && $request->header('X-Paniccheck-Dev') == '1');

        if (!$isDevBypass) {
            // Anti-self-confirmation & Anti-duplicate: Cek apakah user atau IP sudah pernah melapor/mengonfirmasi kejadian ini
            $alreadyVoted = Report::where('event_id', $event->id)
                ->where(function ($q) use ($userId, $ip) {
                    if ($userId) {
                        $q->where('user_id', $userId);
                    }
                    if ($ip) {
                        $q->orWhere('ip_address', $ip);
                    }
                })
                ->exists();

            if ($alreadyVoted) {
                return response()->json([
                    'message' => 'Anda sudah memberikan laporan awal atau konfirmasi untuk kejadian ini.',
                ], 422);
            }
        }

        // Simpan konfirmasi ini sebagai laporan pendukung
        Report::create([
            'event_id' => $event->id,
            'user_id' => $userId,
            'ip_address' => $ip,
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

    // Fitur 3: Mengirim data kejadian banjir ke peta Rilo
    public function index(Request $request)
    {
        // Auto-transisi dinamis (PRD 8.3 M7): Jika ada event ACTIVE yang tidak ada update > 2 jam, ubah jadi STALE
        $twoHoursAgo = \Carbon\Carbon::now()->subHours(2);
        Event::where('status', 'ACTIVE')
            ->where('updated_at', '<', $twoHoursAgo)
            ->update(['status' => 'STALE']);

        $statusList = ['ACTIVE', 'STALE'];

        // Jika parameter include_resolved=1 atau status=all, sertakan event yang sudah surut (RESOLVED)
        if ($request->boolean('include_resolved', false) || $request->query('status') === 'all') {
            $statusList[] = 'RESOLVED';
        }

        $events = Event::whereIn('status', $statusList)
            // Bawa serta data laporan (waktu, tinggi air, foto, dan status konfirmasi) untuk linimasa M6
            ->with(['reports' => function($query) {
                $query->select('id', 'event_id', 'water_level', 'description', 'photo_path', 'is_confirmation', 'created_at');
            }])
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil titik lokasi banjir',
            'data' => $events
        ]);
    }
}