<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserStoryBugfixesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Masalah 1: Warga non-login tidak dapat menutup event (RESOLVED).
     * Hanya relawan atau admin yang memiliki otorisasi sah.
     */
    public function test_citizen_cannot_resolve_event(): void
    {
        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        $citizen = User::create([
            'name' => 'Warga Tanpa Izin Relawan',
            'email' => 'warga_biasa@gmail.com',
            'password' => Hash::make('rahasia123'),
            'role' => 'user',
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/events/{$event->id}/resolve");

        $response->assertStatus(403);
        $this->assertEquals('ACTIVE', $event->fresh()->status);
    }

    /**
     * Masalah 2: Event RESOLVED tetap dapat diambil melalui query include_resolved=1
     * sehingga saat halaman direload, titik yang sudah surut tidak hilang dari peta.
     */
    public function test_resolved_events_persist_and_can_be_retrieved(): void
    {
        $resolvedEvent = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2705,
            'longitude' => 112.7562,
            'status' => 'RESOLVED',
        ]);

        // Default tanpa parameter: hanya ACTIVE dan STALE
        $responseDefault = $this->getJson('/api/events');
        $responseDefault->assertStatus(200);
        $defaultIds = collect($responseDefault->json('data'))->pluck('id')->all();
        $this->assertNotContains($resolvedEvent->id, $defaultIds);

        // Dengan parameter include_resolved=1 (dipakai oleh frontend Rilo)
        $responseWithResolved = $this->getJson('/api/events?include_resolved=1');
        $responseWithResolved->assertStatus(200);
        $allIds = collect($responseWithResolved->json('data'))->pluck('id')->all();
        $this->assertContains($resolvedEvent->id, $allIds);
    }

    /**
     * Masalah 3: Laporan darurat baru dalam radius <= 500m teragregasi ke event aktif terdekat.
     */
    public function test_report_within_500m_clusters_into_existing_event(): void
    {
        $existingEvent = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.289200,
            'longitude' => 112.715300,
            'status' => 'ACTIVE',
        ]);

        // Kirim laporan dengan selisih jarak ~40 meter
        $response = $this->postJson('/api/reports', [
            'latitude' => -7.289500,
            'longitude' => 112.715500,
            'category' => 'Banjir',
            'water_level' => 'sepinggang_keatas',
            'description' => 'Genangan cukup dalam dekat perempatan',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'event_id' => $existingEvent->id,
        ]);

        $this->assertEquals(1, Event::count());
        $this->assertEquals(1, Report::where('event_id', $existingEvent->id)->count());
    }

    /**
     * Masalah 4: Halaman memuat logika deteksi jarak Haversine untuk peringatan Leptospirosis terarah.
     */
    public function test_homepage_contains_haversine_proximity_logic(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('getDistanceToEvent', false);
        $response->assertSee('calculateHaversine', false);
        $response->assertSee('PERINGATAN LEPTOSPIROSIS', false);
    }

    /**
     * Uji Masalah 1: Pembuat laporan awal tidak dapat melakukan konfirmasi silang ke laporannya sendiri.
     */
    public function test_user_or_ip_cannot_confirm_their_own_reported_event(): void
    {
        $citizen = User::create([
            'name' => 'Warga Pelapor',
            'email' => 'pelapor@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        // Catat bahwa pelapor awal adalah $citizen
        Report::create([
            'event_id' => $event->id,
            'user_id' => $citizen->id,
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'category' => 'Banjir',
            'water_level' => 'sepinggang_keatas',
        ]);

        // Coba lakukan konfirmasi silang atas event miliknya sendiri
        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/events/{$event->id}/confirm", [
                'description' => 'Konfirmasi silang palsu sendiri',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Anda sudah memberikan laporan awal atau konfirmasi untuk kejadian ini.',
            ]);
    }

    /**
     * Uji Masalah 3: Konfirmasi ganda dari user atau IP yang sama ditolak (Anti-Double Voting).
     */
    public function test_duplicate_confirmation_from_same_ip_or_user_is_rejected(): void
    {
        $citizen = User::create([
            'name' => 'Warga Saksi',
            'email' => 'saksi_unik@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        // Konfirmasi pertama sukses
        $res1 = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/events/{$event->id}/confirm", [
                'description' => 'Air masih tinggi',
            ]);
        $res1->assertStatus(200);

        // Konfirmasi kedua dari user yang sama ditolak
        $res2 = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/events/{$event->id}/confirm", [
                'description' => 'Mencoba vote lagi',
            ]);
        $res2->assertStatus(422);
    }

    /**
     * Uji Masalah 2: Laporan ketinggian air lebih rendah tidak langsung menimpa status bahaya (Safety-First).
     */
    public function test_lower_water_level_report_does_not_immediately_overwrite_deep_flood(): void
    {
        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        // Laporan awal banjir dalam: sepinggang_keatas
        Report::create([
            'event_id' => $event->id,
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'category' => 'Banjir',
            'water_level' => 'sepinggang_keatas',
            'created_at' => Carbon::now()->subMinutes(15),
        ]);

        $this->assertEquals('sepinggang_keatas', $event->fresh()->water_level);

        // Satu laporan baru masuk menyatakan semata_kaki
        Report::create([
            'event_id' => $event->id,
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'category' => 'Banjir',
            'water_level' => 'semata_kaki',
            'created_at' => Carbon::now(),
        ]);

        // Status event TETAP sepinggang_keatas karena belum ada konsensus 2 laporan berturut-turut
        $this->assertEquals('sepinggang_keatas', $event->fresh()->water_level);

        // Laporan kedua berturut-turut masuk menyatakan semata_kaki (konsensus tercapai)
        Report::create([
            'event_id' => $event->id,
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'category' => 'Banjir',
            'water_level' => 'semata_kaki',
            'created_at' => Carbon::now(),
        ]);

        // Status event sekarang secara sah terde-eskalasi ke semata_kaki
        $this->assertEquals('semata_kaki', $event->fresh()->water_level);
    }

    /**
     * Uji Masalah 3: Cooldown pelaporan 10 menit untuk lokasi yang sama (<500m) dari IP yang sama.
     */
    public function test_report_cooldown_within_10_minutes_at_same_location(): void
    {
        // Laporan pertama sukses
        $res1 = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
            ->postJson('/api/reports', [
                'latitude' => -7.289200,
                'longitude' => 112.715300,
                'category' => 'Banjir',
                'water_level' => 'sepinggang_keatas',
                'description' => 'Laporan awal',
            ]);
        $res1->assertStatus(200);

        // Laporan kedua dari IP yang sama di lokasi yang sama (<500m) dalam 10 menit
        $res2 = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])
            ->postJson('/api/reports', [
                'latitude' => -7.289300,
                'longitude' => 112.715400,
                'category' => 'Banjir',
                'water_level' => 'sepinggang_keatas',
                'description' => 'Laporan spam cepat',
            ]);

        $res2->assertStatus(422)
            ->assertJson([
                'cooldown' => true,
            ]);
    }
}

