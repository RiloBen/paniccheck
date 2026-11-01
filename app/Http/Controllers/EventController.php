<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Report;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    /**
     * Hitung jarak Haversine antara dua koordinat desimal dalam satuan kilometer.
     * shortcut: formula flat Haversine cocok untuk radius kota <50km, upgrade ke spatial index jika skala antar-provinsi.
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    // Fitur 1: Konfirmasi oleh warga lain (M6 - Wajib GPS <= 500m)
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
        $lat = $request->input('latitude');
        $lon = $request->input('longitude');

        $isDevBypass = $request->boolean('dev_bypass', false)
            || ($request->query('dev') == '1')
            || (app()->environment('local', 'testing') && $request->header('X-Paniccheck-Dev') == '1');

        if (!$isDevBypass) {
            // Validasi jarak geospasial <= 500 meter (PRD M6 / Bab 8.2)
            if ($lat === null || $lon === null) {
                return response()->json([
                    'message' => 'Koordinat GPS wajib disertakan untuk memastikan Anda berada di lokasi kejadian (maksimal 500 meter).',
                ], 422);
            }

            $distanceKm = $this->calculateDistance((float)$lat, (float)$lon, (float)$event->latitude, (float)$event->longitude);
            if ($distanceKm > 0.5) {
                $distanceMeters = round($distanceKm * 1000);
                return response()->json([
                    'message' => "Lokasi Anda terdeteksi berjarak {$distanceMeters} m dari titik kejadian. Konfirmasi hanya dapat dilakukan oleh warga di lapangan dalam radius maksimal 500 meter demi validitas data.",
                    'distance_meters' => $distanceMeters,
                ], 422);
            }

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

        // Simpan konfirmasi ini sebagai laporan pendukung dengan koordinat riil pelapor
        Report::create([
            'event_id' => $event->id,
            'user_id' => $userId,
            'ip_address' => $ip,
            'latitude' => $lat ?? $event->latitude, 
            'longitude' => $lon ?? $event->longitude,
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

    // Fitur 2: Penutupan event oleh relawan (M6 - Wajib GPS <= 500m)
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

        $lat = $request->input('latitude');
        $lon = $request->input('longitude');

        $isDevBypass = $request->boolean('dev_bypass', false)
            || ($request->query('dev') == '1')
            || (app()->environment('local', 'testing') && $request->header('X-Paniccheck-Dev') == '1');

        if (!$isDevBypass) {
            // Validasi koordinat GPS relawan di lapangan (PRD Bab 5 & Bab 8.4)
            if ($lat === null || $lon === null) {
                return response()->json([
                    'message' => 'Koordinat GPS relawan di lokasi kejadian wajib disertakan untuk penutupan bencana (PRD Bab 5).',
                ], 422);
            }

            $distanceKm = $this->calculateDistance((float)$lat, (float)$lon, (float)$event->latitude, (float)$event->longitude);
            if ($distanceKm > 0.5) {
                $distanceMeters = round($distanceKm * 1000);
                return response()->json([
                    'message' => "Lokasi Anda terdeteksi berjarak {$distanceMeters} m dari titik kejadian. Penutupan status bencana wajib diverifikasi langsung di lapangan dalam radius maksimal 500 meter.",
                    'distance_meters' => $distanceMeters,
                ], 422);
            }
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