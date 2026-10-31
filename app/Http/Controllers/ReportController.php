<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

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
        
        // 2. Proses unggah foto (jika warga melampirkan foto)
        $photoPath = null;
        if ($request->hasFile('photo')) {
            // Simpan gambar ke folder 'reports' di dalam disk 'public'
            $photoPath = $request->file('photo')->store('reports', 'public');
        }
        
        $twoHoursAgo = Carbon::now()->subHours(2);
        
        $closestEvent = Event::where('category', $category)
            ->where('status', 'ACTIVE')
            ->where('updated_at', '>=', $twoHoursAgo)
            ->whereRaw("( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) <= 0.5", [$lat, $lon, $lat])
            ->selectRaw("*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance", [$lat, $lon, $lat])
            ->orderBy('distance')
            ->first();

        if ($closestEvent) {
            $eventId = $closestEvent->id;
            $closestEvent->touch(); 
        } else {
            $newEvent = Event::create([
                'category' => $category,
                'latitude' => $lat,
                'longitude' => $lon,
                'status' => 'ACTIVE'
            ]);
            $eventId = $newEvent->id;
        }

        // 3. Simpan laporan riil beserta alamat fotonya
        $report = Report::create([
            'event_id' => $eventId,
            'user_id' => Auth::id(), // Pastikan di bagian atas file sudah ada "use Illuminate\Support\Facades\Auth;"
            'latitude' => $lat,
            'longitude' => $lon,
            'category' => $category,
            'water_level' => $request->water_level,
            'photo_path' => $photoPath, // Alamat jalan foto dimasukkan ke sini
            'description' => $request->description,
        ]);

        return response()->json([
            'message' => 'Laporan berhasil diproses!', 
            'event_id' => $eventId,
            // Kembalikan URL lengkap foto agar Rilo bisa langsung menampilkannya
            'photo_url' => $photoPath ? asset('storage/' . $photoPath) : null 
        ]);
    }

    // Fitur 4: Mengambil riwayat laporan khusus pengguna yang sedang login
    public function history()
    {
        // Keamanan tambahan: Pastikan pengguna benar-benar memiliki sesi login
        if (!Auth::check()) {
            return response()->json(['message' => 'Harus login untuk melihat riwayat'], 401);
        }

        $userId = Auth::id();

        // Cari laporan milik user ini, ambil juga status kejadiannya, lalu urutkan dari yang terbaru
        $reports = Report::where('user_id', $userId)
            ->with(['event' => function($query) {
                $query->select('id', 'status'); // Frontend butuh tahu apakah laporan ini masih ACTIVE atau sudah RESOLVED
            }])
            ->latest() // Cara cepat Laravel untuk mengurutkan berdasarkan created_at (terbaru di atas)
            ->get();

        return response()->json([
            'message' => 'Berhasil mengambil riwayat laporan pribadi',
            'data' => $reports
        ]);
    }
}