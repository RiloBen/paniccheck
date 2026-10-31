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
        // 1. Validasi data yang dikirim dari form Rilo
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'category' => 'required|string',
        ]);

        $lat = $request->latitude;
        $lon = $request->longitude;
        $category = $request->category;
        
        // Jendela waktu 2 jam dari laporan terakhir
        $twoHoursAgo = Carbon::now()->subHours(2);
        
        // 2. Cari event terdekat menggunakan Rumus Haversine (di bawah 0.5 km atau 500 meter)
        $closestEvent = Event::where('category', $category)
            ->where('status', 'ACTIVE') // Hanya gabung ke event yang masih aktif
            ->where('updated_at', '>=', $twoHoursAgo)
            ->whereRaw("( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) <= 0.5", [$lat, $lon, $lat])
            ->selectRaw("*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance", [$lat, $lon, $lat])
            ->orderBy('distance')
            ->first();

        if ($closestEvent) {
            // 3a. Jika ada event di radius 500m, gabungkan ke event tersebut
            $eventId = $closestEvent->id;
            
            // touch() akan memperbarui kolom 'updated_at' ke waktu sekarang, 
            // sehingga event ini tetap hidup (sliding window 2 jam)
            $closestEvent->touch(); 
        } else {
            // 3b. Jika tidak ada event terdekat, ciptakan event baru
            $newEvent = Event::create([
                'category' => $category,
                'latitude' => $lat,
                'longitude' => $lon,
                'status' => 'ACTIVE'
            ]);
            $eventId = $newEvent->id;
        }

        // 4. Simpan laporan riil dari warga
        $report = Report::create([
            'event_id' => $eventId,
            'user_id' => Auth::id(), // Akan berisi null jika warga melapor tanpa login
            'latitude' => $lat,
            'longitude' => $lon,
            'category' => $category,
            'water_level' => $request->water_level,
            'description' => $request->description,
        ]);

        return response()->json([
            'message' => 'Laporan berhasil diproses!', 
            'event_id' => $eventId
        ]);
    }
}