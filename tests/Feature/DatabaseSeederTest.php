<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Uji DatabaseSeeder mempopulasikan akun Admin dan 4 titik skenario Surabaya secara konsisten (M8).
     */
    public function test_database_seeder_populates_admin_and_surabaya_scenario_events(): void
    {
        $this->assertEquals(0, User::count());
        $this->assertEquals(0, Event::count());
        $this->assertEquals(0, Report::count());

        // Jalankan seeder
        $this->seed(DatabaseSeeder::class);

        // 1. Verifikasi akun Admin
        $this->assertEquals(1, User::where('role', 'admin')->count());
        $admin = User::where('email', 'admin@paniccheck.com')->first();
        $this->assertNotNull($admin);
        $this->assertEquals('Komandan Relawan', $admin->name);

        // 2. Verifikasi 4 Titik Skenario Surabaya
        $this->assertEquals(4, Event::count());

        // Mayjen Sungkono (ACTIVE)
        $this->assertTrue(Event::where('status', 'ACTIVE')->where('latitude', -7.28920000)->exists());

        // Raya Darmo (STALE)
        $this->assertTrue(Event::where('status', 'STALE')->where('latitude', -7.28580000)->exists());

        // Dharmawangsa (RESOLVED)
        $this->assertTrue(Event::where('status', 'RESOLVED')->where('latitude', -7.27050000)->exists());

        // 3. Verifikasi Laporan Pendukung Warga (Total 5 laporan)
        $this->assertEquals(5, Report::count());
    }
}

