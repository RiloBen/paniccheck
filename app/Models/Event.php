<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    // Mengizinkan kolom ini diisi data secara otomatis
    protected $fillable = [
        'category',
        'location_name',
        'latitude',
        'longitude',
        'status',
    ];

    protected $appends = [
        'water_level',
    ];

    // Relasi: Satu Event (kejadian banjir) bisa memiliki banyak Laporan warga
    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Hitung tingkat keparahan genangan berbasis prinsip Safety-First (Worst-Case Bias).
     * Level tertinggi dalam 2 jam terakhir dipertahankan sampai ada konsensus minimal 2 laporan surut berturut-turut.
     */
    public function getWaterLevelAttribute(): string
    {
        $reports = $this->relationLoaded('reports')
            ? $this->reports
            : $this->reports()->get();

        $validReports = $reports->filter(fn($r) => !empty($r->water_level))->values();

        if ($validReports->isEmpty()) {
            return 'selutut';
        }

        $rank = fn(?string $l) => match ($l) {
            'sepinggang_keatas' => 3,
            'selutut' => 2,
            'semata_kaki' => 1,
            default => 0,
        };

        $twoHoursAgo = Carbon::now()->subHours(2);
        $recentReports = $validReports->filter(fn($r) => $r->created_at >= $twoHoursAgo);

        if ($recentReports->isEmpty()) {
            $recentReports = $validReports;
        }

        $maxRank = $recentReports->max(fn($r) => $rank($r->water_level));

        // Cek de-eskalasi: apakah 2 laporan terbaru secara berturut-turut konsisten lebih rendah
        $sortedReports = $validReports->sort(function ($a, $b) {
            if ($a->created_at == $b->created_at) {
                return $b->id <=> $a->id;
            }
            return $b->created_at <=> $a->created_at;
        })->values();

        $latest = $sortedReports->take(2);
        if ($latest->count() >= 2) {
            $r1 = $rank($latest[0]->water_level);
            $r2 = $rank($latest[1]->water_level);
            if ($r1 === $r2 && $r1 < $maxRank) {
                return $latest[0]->water_level;
            }
        }

        return match ($maxRank) {
            3 => 'sepinggang_keatas',
            2 => 'selutut',
            default => 'semata_kaki',
        };
    }
}
