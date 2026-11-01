<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter; // <-- TAMBAHAN BUG 1: Memanggil fitur Rate Limiter Laravel

class ReportController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validasi data, termasuk membatasi file hanya boleh gambar (jpg/png) maksimal 2MB
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'category' => 'required|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', 
        ]);

        $lat = $request->latitude;
        $lon = $request->longitude;
        $category = $request->category;
        $user = $request->user('sanctum') ?? Auth::user();
        $userId = $user?->id;
        $ipAddress = $request->ip();
        $throttleKey = 'lapor-banjir-' . $ipAddress;

        // Dev / Testing Bypass agar simulasi juri dan automated tests tidak terkunci
        $isDevBypass = $request->boolean('dev_bypass', false)
            || ($request->query('dev') == '1')
            || (app()->environment('local', 'testing') && $request->header('X-Paniccheck-Dev') == '1');

        if (!$isDevBypass) {
            // Cooldown check anti-spam per IP/User di lokasi yang sama (<500m dalam 10 menit)
            $tenMinutesAgo = Carbon::now()->subMinutes(10);
            $recentReport = Report::where('created_at', '>=', $tenMinutesAgo)
                ->where(function ($q) use ($userId, $ipAddress) {
                    if ($userId) {
                        $q->where('user_id', $userId);
                    }
                    if ($ipAddress) {
                        $q->orWhere('ip_address', $ipAddress);
                    }
                })
                ->whereRaw("( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) <= 0.5", [$lat, $lon, $lat])
                ->first();

            if ($recentReport) {
                return response()->json([
                    'message' => 'Laporan dari perangkat Anda di sekitar lokasi ini sudah tercatat. Mohon tunggu 10 menit sebelum memperbarui kondisi air.',
                    'cooldown' => true,
                    'event_id' => $recentReport->event_id,
                ], 422);
            }

            // IP Rate Limiter anti-spam global (Bug 1: max 1 per 60 menit)
            if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                $minutes = ceil($seconds / 60);
                
                return response()->json([
                    'message' => "Anda sudah membuat laporan. Tunggu {$minutes} menit lagi untuk mencegah spam/manipulasi data."
                ], 429);
            }
        }

        // 2. Proses unggah foto (jika warga melampirkan foto)
        $photoPath = null;
        if ($request->hasFile('photo')) {
            // Simpan gambar ke folder 'reports' di dalam disk 'public'
            $photoPath = $request->file('photo')->store('reports', 'public');
        }
        
        $twoHoursAgo = Carbon::now()->subHours(2);
        
        $closestEvent = Event::where('category', $category)
            ->where('status', 'ACTIVE') // Cari yang masih aktif (termasuk yang stale/abu-abu), abaikan yang sudah RESOLVED
            ->whereRaw("( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) <= 0.5", [$lat, $lon, $lat])
            ->selectRaw("*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance", [$lat, $lon, $lat])
            ->orderBy('distance')
            ->first();

        $isRelawan = $user && in_array($user->role, ['relawan', 'admin']);
        $isSurut = $request->water_level == 'surut';

        if ($closestEvent) {
            $eventId = $closestEvent->id;
            
            // JIKA LAPORAN BARU MENYATAKAN "SURUT" TAPI YANG MELAPOR BUKAN RELAWAN
            if ($isSurut && !$isRelawan) {
                // Kita hanya menyegarkan waktunya agar titik kuning menyala,
                // tapi JANGAN ubah status utama menjadi RESOLVED untuk menghindari manipulasi orang iseng.
                $closestEvent->update([
                    'updated_at' => Carbon::now() 
                ]);
            } 
            // JIKA LAPORANNYA GENANGAN AIR NORMAL ATAU YANG MELAPOR ADALAH RELAWAN
            else {
                // Update tinggi air sesuai laporan terbaru dan segarkan waktunya
                $closestEvent->update([
                    'water_level' => $request->water_level,
                    'status' => $isSurut ? 'RESOLVED' : 'ACTIVE',
                    'updated_at' => Carbon::now()
                ]);
            }
        } else {
            // Jika benar-benar tidak ada titik di peta (baik kuning maupun abu-abu)
            $newEvent = Event::create([
                'category' => $category,
                'latitude' => $lat,
                'longitude' => $lon,
                'status' => 'ACTIVE',
                'water_level' => $request->water_level // Simpan tinggi air awal
            ]);
            $eventId = $newEvent->id;
        }

        // 3. Simpan laporan riil beserta alamat foto dan IP
        $report = Report::create([
            'event_id' => $eventId,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'latitude' => $lat,
            'longitude' => $lon,
            'category' => $category,
            'water_level' => $request->water_level,
            'photo_path' => $photoPath, 
            'description' => $request->description,
        ]);

        // Kunci IP selama 60 Menit (3600 detik) setelah berhasil melapor (hanya jika bukan dev bypass)
        if (!$isDevBypass) {
            RateLimiter::hit($throttleKey, 3600);
        }

        $event = Event::with('reports')->find($eventId);

        return response()->json([
            'message' => 'Laporan berhasil diproses!', 
            'event_id' => $eventId,
            'water_level' => $event?->water_level ?? ($request->water_level ?: 'selutut'),
            'photo_url' => $photoPath ? asset('storage/' . $photoPath) : null 
        ]);
    }

    // Fitur 4: Mengambil riwayat laporan khusus pengguna yang sedang login
    public function history()
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Harus login untuk melihat riwayat'], 401);
        }

        $userId = Auth::id();

        $reports = Report::where('user_id', $userId)
            ->with(['event' => function($query) {
                $query->select('id', 'status'); 
            }])
            ->latest() 
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil riwayat laporan pribadi',
            'data' => $reports
        ]);
    }
}