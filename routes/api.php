<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;
use App\Models\Event;
use Illuminate\Support\Facades\Schema;

Route::post('/reports', [ReportController::class, 'store']);

Route::get('/events', function () {
    // shortcut: return seed data jika tabel events belum dimigrate di DB, upgrade saat migrasi selesai
    if (Schema::hasTable('events')) {
        try {
            $events = Event::with('reports')->get();
            if ($events->isNotEmpty()) {
                return response()->json($events->map(function ($ev) {
                    $latestReport = $ev->reports->last();
                    return [
                        'id' => $ev->id,
                        'category' => $ev->category,
                        'status' => $ev->status,
                        'latitude' => (float) $ev->latitude,
                        'longitude' => (float) $ev->longitude,
                        'water_level' => $latestReport ? ($latestReport->water_level ?? 'selutut') : 'selutut',
                        'reports_count' => $ev->reports->count() ?: 1,
                        'is_verified' => (bool) ($ev->is_verified ?? false),
                        'location_name' => 'Titik Koordinat ' . round($ev->latitude, 4) . ', ' . round($ev->longitude, 4),
                        'updated_at_human' => $ev->updated_at ? $ev->updated_at->diffForHumans() : 'Baru saja'
                    ];
                }));
            }
        } catch (\Throwable $e) {
            // fallback ke mock data di bawah
        }
    }

    // Default seed titik banjir Surabaya untuk kesiapan demo
    return response()->json([
        [
            'id' => 1,
            'category' => 'banjir',
            'status' => 'ACTIVE',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'water_level' => 'sepinggang_keatas',
            'reports_count' => 6,
            'is_verified' => true,
            'location_name' => 'Jl. Mayjen Sungkono (Dekat Ciputra World)',
            'updated_at_human' => '8 menit lalu'
        ],
        [
            'id' => 2,
            'category' => 'banjir',
            'status' => 'STALE',
            'latitude' => -7.2858,
            'longitude' => 112.7383,
            'water_level' => 'semata_kaki',
            'reports_count' => 2,
            'is_verified' => false,
            'location_name' => 'Jl. Raya Darmo (Depan Taman Bungkul)',
            'updated_at_human' => '2 jam 15 mnt lalu'
        ],
        [
            'id' => 3,
            'category' => 'banjir',
            'status' => 'ACTIVE',
            'latitude' => -7.2797,
            'longitude' => 112.7758,
            'water_level' => 'selutut',
            'reports_count' => 4,
            'is_verified' => true,
            'location_name' => 'Jl. Raya Kertajaya Indah',
            'updated_at_human' => '25 menit lalu'
        ],
        [
            'id' => 4,
            'category' => 'banjir',
            'status' => 'RESOLVED',
            'latitude' => -7.2705,
            'longitude' => 112.7562,
            'water_level' => 'semata_kaki',
            'reports_count' => 5,
            'is_verified' => true,
            'location_name' => 'Jl. Dharmawangsa (Depan RSUD Dr. Soetomo)',
            'updated_at_human' => '45 menit lalu'
        ]
    ]);
});
