<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Event;
use Carbon\Carbon;

class CleanupOldPhotos extends Command
{
    // Ini nama alias saat kita memanggilnya di terminal nanti
    protected $signature = 'paniccheck:cleanup-photos';

    protected $description = 'Menghapus fisik foto dari storage untuk event RESOLVED yang umurnya lebih dari 7 hari';

    public function handle()
    {
        $thresholdDate = Carbon::now()->subDays(7);
        
        $oldEvents = Event::where('status', 'RESOLVED')
                          ->where('updated_at', '<', $thresholdDate)
                          ->with('reports') 
                          ->get();

        // 1. Beri tahu kita ada berapa event yang berhasil lolos kueri waktu dan status
        $this->info("Ditemukan " . $oldEvents->count() . " event RESOLVED yang sudah lewat 7 hari.");

        $deletedCount = 0;

        foreach ($oldEvents as $event) {
            foreach ($event->reports as $report) {
                // 2. Beri tahu file apa yang sedang dicek
                $this->info("Mengecek laporan dengan path foto di database: " . $report->photo_path);
                
                if ($report->photo_path) {
                    if (Storage::disk('public')->exists($report->photo_path)) {
                        Storage::disk('public')->delete($report->photo_path);
                        $this->info("Berhasil menghapus fisik file: " . $report->photo_path);
                        $deletedCount++;
                    } else {
                        // 3. Peringatan jika file fisik sudah tidak ada di storage
                        $this->warn("File fisik sudah tidak ditemukan di path: " . $report->photo_path);
                    }

                    // Sinkronkan database: kosongkan photo_path agar tidak terjadi broken image link
                    $report->update(['photo_path' => null]);
                }
            }
        }

        $this->info("Garbage Collector selesai: $deletedCount foto lama berhasil dihapus.");
    }
}