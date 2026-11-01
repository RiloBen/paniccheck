<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EventApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Uji GET /api/events hanya menampilkan kejadian berstatus ACTIVE dan STALE,
     * serta tidak menampilkan kejadian yang sudah RESOLVED (surut permanen).
     */
    public function test_get_events_returns_only_active_and_stale_events(): void
    {
        // 1. Buat event ACTIVE
        $activeEvent = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);
        Report::create([
            'event_id' => $activeEvent->id,
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'category' => 'Banjir',
            'water_level' => 'sepinggang_keatas',
        ]);

        // 2. Buat event STALE
        $staleEvent = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2858,
            'longitude' => 112.7383,
            'status' => 'STALE',
        ]);

        // 3. Buat event RESOLVED
        $resolvedEvent = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2705,
            'longitude' => 112.7562,
            'status' => 'RESOLVED',
        ]);

        $response = $this->getJson('/api/events');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'category',
                        'latitude',
                        'longitude',
                        'status',
                        'reports',
                    ]
                ]
            ]);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($activeEvent->id, $ids);
        $this->assertContains($staleEvent->id, $ids);
        $this->assertNotContains($resolvedEvent->id, $ids); // RESOLVED tidak boleh ikut terpilih

        // Verifikasi ketika include_resolved=1, event RESOLVED ikut dikembalikan
        $responseResolved = $this->getJson('/api/events?include_resolved=1');
        $responseResolved->assertStatus(200);
        $resolvedIds = collect($responseResolved->json('data'))->pluck('id')->all();
        $this->assertContains($resolvedEvent->id, $resolvedIds);
    }

    /**
     * Uji konfirmasi event membutuhkan login akun warga (HTTP 401 jika unauthenticated).
     */
    public function test_confirm_event_requires_authentication(): void
    {
        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson("/api/events/{$event->id}/confirm", [
            'description' => 'Saya lihat di lokasi air masih tinggi.',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Uji warga yang sudah login dapat memberikan konfirmasi silang kejadian banjir (FR-3 / M6).
     */
    public function test_authenticated_citizen_can_confirm_active_flood(): void
    {
        $citizen = User::create([
            'name' => 'Warga Verifikator',
            'email' => 'verifikator@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/events/{$event->id}/confirm", [
                'latitude' => -7.2892,
                'longitude' => 112.7153,
                'description' => 'Kondisi air masih selutut, belum bisa dilewati mobil sedan.',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Konfirmasi berhasil ditambahkan dan status diperbarui!']);

        $this->assertDatabaseHas('reports', [
            'event_id' => $event->id,
            'user_id' => $citizen->id,
            'is_confirmation' => true,
        ]);
    }

    /**
     * Uji transisi status (FR-4 / M7):
     * Konfirmasi warga pada event berstatus STALE otomatis mengembalikan status menjadi ACTIVE.
     */
    public function test_confirm_reactivates_stale_event_back_to_active(): void
    {
        $citizen = User::create([
            'name' => 'Warga Saksi',
            'email' => 'saksi@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $staleEvent = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2858,
            'longitude' => 112.7383,
            'status' => 'STALE',
            'updated_at' => Carbon::now()->subHours(3),
        ]);

        $this->assertEquals('STALE', $staleEvent->status);

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/events/{$staleEvent->id}/confirm", [
                'latitude' => -7.2858,
                'longitude' => 112.7383,
                'description' => 'Hujan deras lagi, air naik lagi semata kaki.',
            ]);

        $response->assertStatus(200);

        // Verifikasi status otomatis bangkit kembali menjadi ACTIVE
        $staleEvent->refresh();
        $this->assertEquals('ACTIVE', $staleEvent->status);
    }

    /**
     * Uji hak akses penutupan event (FR-3 / M6):
     * Warga biasa (role: user) DITOLAK saat mencoba menutup status event (HTTP 403 Forbidden).
     */
    public function test_regular_citizen_cannot_resolve_event(): void
    {
        $citizen = User::create([
            'name' => 'Warga Biasa',
            'email' => 'warga@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/events/{$event->id}/resolve");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Akses ditolak. Anda tidak memiliki izin relawan/admin.']);

        $event->refresh();
        $this->assertEquals('ACTIVE', $event->status); // Status tetap aman tidak tertutup
    }

    /**
     * Uji relawan terverifikasi (role: relawan) dan admin berhasil menutup event menjadi RESOLVED (M6).
     */
    public function test_verified_volunteer_and_admin_can_resolve_event(): void
    {
        $volunteer = User::create([
            'name' => 'Relawan Lapangan BPBD',
            'email' => 'relawan@example.com',
            'password' => Hash::make('password123'),
            'role' => 'relawan',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($volunteer, 'sanctum')
            ->postJson("/api/events/{$event->id}/resolve", [
                'latitude' => -7.2892,
                'longitude' => 112.7153,
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Event berhasil ditutup dan ditandai RESOLVED.']);

        $event->refresh();
        $this->assertEquals('RESOLVED', $event->status);
    }

    /**
     * Uji penanganan error 404 ketika ID kejadian tidak ada di database.
     */
    public function test_returns_404_when_event_not_found(): void
    {
        $volunteer = User::create([
            'name' => 'Relawan',
            'email' => 'relawan2@example.com',
            'password' => Hash::make('password123'),
            'role' => 'relawan',
        ]);

        $response = $this->actingAs($volunteer, 'sanctum')
            ->postJson('/api/events/99999/resolve');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Event tidak ditemukan']);
    }

    /**
     * Uji auto-transisi dinamis (PRD 8.3 M7):
     * Event ACTIVE yang tidak menerima update selama > 2 jam otomatis beralih ke status STALE saat API diakses.
     */
    public function test_get_events_auto_transitions_active_event_older_than_two_hours_to_stale(): void
    {
        $oldEvent = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);
        $oldEvent->timestamps = false;
        $oldEvent->updated_at = Carbon::now()->subHours(2)->subMinutes(15);
        $oldEvent->save();

        // 1. Panggil GET /api/events, event otomatis ditransisikan ke STALE
        $response = $this->getJson('/api/events');
        $response->assertStatus(200);

        $eventData = collect($response->json('data'))->firstWhere('id', $oldEvent->id);
        $this->assertNotNull($eventData);
        $this->assertEquals('STALE', $eventData['status']);
        $this->assertEquals('STALE', $oldEvent->fresh()->status);

        // 2. Warga melakukan konfirmasi pada event STALE tersebut
        $user = User::create([
            'name' => 'Warga Pelapor',
            'email' => 'warga_confirm@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $confirmRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$oldEvent->id}/confirm", [
                'latitude' => $oldEvent->latitude,
                'longitude' => $oldEvent->longitude,
                'description' => 'Air masih menggenang tinggi',
            ]);
        $confirmRes->assertStatus(200);

        // 3. Setelah konfirmasi, status kembali ACTIVE dan tidak menjadi STALE lagi saat reload
        $responseAfterConfirm = $this->getJson('/api/events');
        $responseAfterConfirm->assertStatus(200);

        $eventDataAfter = collect($responseAfterConfirm->json('data'))->firstWhere('id', $oldEvent->id);
        $this->assertEquals('ACTIVE', $eventDataAfter['status']);
        $this->assertEquals('ACTIVE', $oldEvent->fresh()->status);
    }

    /**
     * Uji validasi geospasial (PRD M6 / Bab 8.2):
     * Konfirmasi dalam radius <= 500m dari titik banjir DITERIMA (HTTP 200).
     */
    public function test_confirm_flood_within_500m_is_accepted(): void
    {
        $citizen = User::create([
            'name' => 'Warga Dekat',
            'email' => 'warga_dekat@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.289200,
            'longitude' => 112.715300,
            'status' => 'ACTIVE',
        ]);

        // Lokasi warga selisih ~40 meter
        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/events/{$event->id}/confirm", [
                'latitude' => -7.289500,
                'longitude' => 112.715500,
                'description' => 'Saya di perempatan dekat lampu merah, air memang masih tinggi.',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Konfirmasi berhasil ditambahkan dan status diperbarui!']);

        $report = Report::where('event_id', $event->id)->where('is_confirmation', true)->first();
        $this->assertNotNull($report);
        $this->assertEquals(-7.289500, (float)$report->latitude);
    }

    /**
     * Uji validasi geospasial (PRD M6 / Bab 8.2):
     * Konfirmasi di luar radius > 500m dari titik banjir DITOLAK (HTTP 422).
     */
    public function test_confirm_flood_outside_500m_is_rejected(): void
    {
        $citizen = User::create([
            'name' => 'Warga Jauh',
            'email' => 'warga_jauh@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.289200,
            'longitude' => 112.715300,
            'status' => 'ACTIVE',
        ]);

        // Lokasi warga berjarak ~2.1 km
        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/events/{$event->id}/confirm", [
                'latitude' => -7.275000,
                'longitude' => 112.730000,
                'description' => 'Coba konfirmasi dari jarak jauh',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'distance_meters']);

        $this->assertStringContainsString('500 meter', $response->json('message'));
        $this->assertGreaterThan(500, $response->json('distance_meters'));
    }

    /**
     * Uji konfirmasi tanpa koordinat GPS ditolak (HTTP 422).
     */
    public function test_confirm_flood_without_gps_is_rejected(): void
    {
        $citizen = User::create([
            'name' => 'Warga Tanpa GPS',
            'email' => 'no_gps@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.289200,
            'longitude' => 112.715300,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/events/{$event->id}/confirm", [
                'description' => 'Lapor tanpa kirim GPS',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Koordinat GPS wajib disertakan untuk memastikan Anda berada di lokasi kejadian (maksimal 500 meter).',
            ]);
    }

    /**
     * Uji penutupan oleh relawan di luar radius 500m ditolak (PRD Bab 5 & Bab 8.4).
     */
    public function test_volunteer_resolve_outside_500m_is_rejected(): void
    {
        $volunteer = User::create([
            'name' => 'Relawan Posko Jauh',
            'email' => 'relawan_jauh@example.com',
            'password' => Hash::make('password123'),
            'role' => 'relawan',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.289200,
            'longitude' => 112.715300,
            'status' => 'ACTIVE',
        ]);

        // Jarak ~2 km
        $response = $this->actingAs($volunteer, 'sanctum')
            ->postJson("/api/events/{$event->id}/resolve", [
                'latitude' => -7.270000,
                'longitude' => 112.730000,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('500 meter', $response->json('message'));
        $this->assertEquals('ACTIVE', $event->fresh()->status);
    }

    /**
     * Uji bypass simulasi Dev Mode (?dev=1 / X-Paniccheck-Dev) mengizinkan aksi remote saat demo.
     */
    public function test_dev_mode_bypass_allows_remote_actions_for_presentation(): void
    {
        $volunteer = User::create([
            'name' => 'Relawan Demo',
            'email' => 'relawan_demo@example.com',
            'password' => Hash::make('password123'),
            'role' => 'relawan',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.289200,
            'longitude' => 112.715300,
            'status' => 'ACTIVE',
        ]);

        // Relawan berada jauh tapi mengirimkan dev_bypass / header demo
        $response = $this->actingAs($volunteer, 'sanctum')
            ->withHeader('X-Paniccheck-Dev', '1')
            ->postJson("/api/events/{$event->id}/resolve", [
                'dev_bypass' => true,
            ]);

        $response->assertStatus(200);
        $this->assertEquals('RESOLVED', $event->fresh()->status);
    }
}

