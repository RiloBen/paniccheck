<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use Carbon\Carbon;

class UpdateStaleEvents extends Command
{
    // Ini adalah nama panggilan untuk robotmu nanti di terminal
    protected $signature = 'events:check-stale';

    // Deskripsi tugas si robot
    protected $description = 'Mengecek dan mengubah event ACTIVE menjadi STALE jika tidak ada update selama 2 jam';

    public function handle()
    {
        // 1. Cari batas waktu 2 jam yang lalu
        $twoHoursAgo = Carbon::now()->subHours(2);

        // 2. Cari semua event ACTIVE yang update terakhirnya lebih lama dari 2 jam lalu, lalu ubah jadi STALE
        $updatedCount = Event::where('status', 'ACTIVE')
            ->where('updated_at', '<', $twoHoursAgo)
            ->update(['status' => 'STALE']);

        // 3. Tampilkan pesan di terminal (berguna buat demo simulasi waktu nanti)
        $this->info("Berhasil mengubah {$updatedCount} event menjadi STALE.");
    }
}