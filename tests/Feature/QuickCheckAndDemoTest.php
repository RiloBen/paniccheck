<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QuickCheckAndDemoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Uji Halaman Utama memuat komponen Quick Check Scanner (M10).
     */
    public function test_homepage_contains_quick_check_scanner(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Tempel broadcast WA banjir', false);
        $response->assertSee('quickCheckText', false);
        $response->assertSee('runQuickCheck', false);
    }

    /**
     * Uji M7 Fast Forward: Akses ditolak (403) jika bukan admin dan tanpa flag dev mode.
     */
    public function test_demo_fast_forward_denies_unauthorized_user(): void
    {
        $user = User::create([
            'name' => 'Warga Biasa',
            'email' => 'warga@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/demo/fast-forward');

        $response->assertStatus(403);
    }

    /**
     * Uji M7 Fast Forward: Admin berhasil memajukan seluruh event aktif menjadi STALE.
     */
    public function test_demo_fast_forward_shifts_active_events_to_stale_for_admin(): void
    {
        $admin = User::create([
            'name' => 'Admin Demo',
            'email' => 'admin@paniccheck.com',
            'password' => Hash::make('rahasia123'),
            'role' => 'admin',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/demo/fast-forward');

        $response->assertStatus(200)
            ->assertJson([
                'count' => 1,
            ]);

        $event->refresh();
        $this->assertEquals('STALE', $event->status);
        $this->assertTrue($event->updated_at->lessThan(Carbon::now()->subHours(2)));
    }

    /**
     * Uji M7 Fast Forward di local environment dengan param dev=1.
     */
    public function test_demo_fast_forward_works_with_dev_query_parameter_in_local(): void
    {
        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2858,
            'longitude' => 112.7383,
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/demo/fast-forward?dev=1');

        $response->assertStatus(200);
        $event->refresh();
        $this->assertEquals('STALE', $event->status);
    }

    /**
     * Uji M7 Reset Database via API Demo.
     */
    public function test_demo_reset_restores_surabaya_seed_scenario(): void
    {
        $admin = User::create([
            'name' => 'Admin Demo',
            'email' => 'admin@paniccheck.com',
            'password' => Hash::make('rahasia123'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/demo/reset');

        $response->assertStatus(200);

        // Verifikasi seeder mengembalikan 4 titik kejadian Surabaya
        $this->assertEquals(4, Event::count());
        $this->assertTrue(Event::where('status', 'ACTIVE')->exists());
        $this->assertTrue(Event::where('status', 'STALE')->exists());
        $this->assertTrue(Event::where('status', 'RESOLVED')->exists());
    }
}

