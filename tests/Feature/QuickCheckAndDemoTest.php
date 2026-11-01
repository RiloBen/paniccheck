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

    /**
     * Uji M10 Quick Check Scanner: Mendeteksi level air dan mencocokkan nama jalan event aktif.
     */
    public function test_quick_check_scanner_detects_water_level_and_matches_active_event(): void
    {
        $event = Event::create([
            'category' => 'Banjir',
            'location_name' => 'Jl. Mayjen Sungkono',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/quick-check', [
            'text' => 'Info banjir lur, daerah mayjen sungkono air sudah sepinggang!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'extracted_data' => [
                    'water_level' => 'sepinggang_keatas',
                    'location_hint' => 'Jl. Mayjen Sungkono',
                    'event_id' => $event->id,
                    'latitude' => -7.2892,
                    'longitude' => 112.7153,
                ],
            ]);
    }

    /**
     * Uji M10: Quick Check Scanner mendeteksi indikasi banjir baru dari broadcast teks WA bebas (tanpa data di database).
     */
    public function test_quick_check_scanner_detects_new_flood_indication_from_arbitrary_whatsapp_broadcast(): void
    {
        // Pastikan tidak ada event di DB
        $this->assertEquals(0, Event::count());

        $response = $this->postJson('/api/quick-check', [
            'text' => 'Waspada lur, Jl. Ahmad Yani depan Royal Plaza banjir selutut macet parah mobil gak bisa lewat!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'match_type' => 'NEW_DETECTION',
                'extracted_data' => [
                    'is_new_indication' => true,
                    'water_level' => 'selutut',
                    'category' => 'Banjir',
                ],
            ]);

        $json = $response->json();
        $this->assertStringContainsString('Ahmad Yani', $json['extracted_data']['location_hint']);
        $this->assertNotNull($json['extracted_data']['latitude']);
        $this->assertNotNull($json['extracted_data']['longitude']);
    }

    /**
     * Uji M10: Quick Check Scanner mendeteksi nama jalan dengan dynamic NLP regex dari teks sembarang.
     */
    public function test_quick_check_scanner_extracts_dynamic_location_from_unlisted_street(): void
    {
        $response = $this->postJson('/api/quick-check', [
            'text' => 'Tolong min, banjir parah sepinggang di Jalan Simo Pomahan gang 5 air masuk rumah!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'match_type' => 'NEW_DETECTION',
                'extracted_data' => [
                    'water_level' => 'sepinggang_keatas',
                ],
            ]);

        $json = $response->json();
        $this->assertStringContainsString('Simo Pomahan', $json['extracted_data']['location_hint']);
    }

    /**
     * Uji M10: Quick Check Scanner mendeteksi jalur aman terpantau.
     */
    public function test_quick_check_scanner_detects_safe_road_condition(): void
    {
        $response = $this->postJson('/api/quick-check', [
            'text' => 'Laporan sore: Jalan Pemuda aman lancar tidak ada banjir sama sekali.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'match_type' => 'SAFE',
            ]);

        $json = $response->json();
        $this->assertStringContainsString('Pemuda', $json['extracted_data']['location_hint']);
    }

    /**
     * Uji M10: Quick Check Scanner menangani teks umum tanpa nama jalan dan tanpa banjir.
     */
    public function test_quick_check_scanner_handles_generic_unrecognized_text(): void
    {
        $response = $this->postJson('/api/quick-check', [
            'text' => 'Halo mas bro besok pagi ada agenda kumpul tim gak ya?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'match_type' => 'UNRECOGNIZED',
            ]);
    }
}

