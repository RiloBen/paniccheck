<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    /**
     * Gerbang otorisasi Dev Mode / Admin:
     * Hanya diizinkan jika lingkungan lokal ATAU user login dengan role admin.
     * shortcut: di environment testing/local diizinkan lewat param dev=1, ketatkan dengan token di prod.
     */
    private function authorizeAdminOrDev(Request $request): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        if (Auth::check() && Auth::user()->role === 'admin') {
            return true;
        }

        // Khusus environment local/testing untuk kemudahan simulasi demo
        if (app()->environment('local', 'testing') && ($request->query('dev') == '1' || $request->header('X-Paniccheck-Dev') == '1')) {
            return true;
        }

        return false;
    }

    /**
     * M7: Memajukan waktu 2 jam untuk seluruh event aktif.
     * Mengubah status menjadi STALE tanpa perlu menunggu 2 jam secara nyata saat presentasi.
     */
    public function fastForward(Request $request)
    {
        if (!$this->authorizeAdminOrDev($request)) {
            return response()->json(['message' => 'Akses ditolak. Fitur ini khusus Admin / Dev Mode.'], 403);
        }

        $activeEvents = Event::where('status', 'ACTIVE')->get();

        foreach ($activeEvents as $event) {
            $event->status = 'STALE';
            // Set waktu mundur 2 jam 10 menit ke masa lalu agar terdeteksi stale
            $event->timestamps = false;
            $event->updated_at = Carbon::now()->subHours(2)->subMinutes(10);
            $event->save();
        }

        return response()->json([
            'message' => 'Simulasi berhasil: Seluruh event aktif dialihkan ke status STALE (tidak ada update > 2 jam).',
            'count' => $activeEvents->count()
        ]);
    }

    /**
     * M7: Reset instan seluruh skenario Surabaya ke data seeder awal.
     * Membersihkan tabel data dan menjalankan seeder agar kompatibel di SQLite & MySQL.
     */
    public function reset(Request $request)
    {
        if (!$this->authorizeAdminOrDev($request)) {
            return response()->json(['message' => 'Akses ditolak. Fitur ini khusus Admin / Dev Mode.'], 403);
        }

        // Hapus data secara hierarkis agar aman dari foreign key constraint
        Report::query()->delete();
        Event::query()->delete();
        User::query()->delete();

        Artisan::call('db:seed', [
            '--class' => \Database\Seeders\DatabaseSeeder::class,
            '--force' => true,
        ]);

        return response()->json([
            'message' => 'Database skenario Surabaya berhasil direset ke status awal.'
        ]);
    }
}
