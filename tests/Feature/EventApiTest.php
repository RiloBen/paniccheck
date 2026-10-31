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

        $response->assertStatus(401)
            ->assertJson(['message' => 'Harus login untuk melakukan konfirmasi']);
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

        $response = $this->actingAs($citizen)
            ->postJson("/api/events/{$event->id}/confirm", [
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

        $response = $this->actingAs($citizen)
            ->postJson("/api/events/{$staleEvent->id}/confirm", [
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

        $response = $this->actingAs($citizen)
            ->postJson("/api/events/{$event->id}/resolve");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Akses ditolak. Hanya relawan terverifikasi yang bisa menutup kejadian.']);

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

        $response = $this->actingAs($volunteer)
            ->postJson("/api/events/{$event->id}/resolve");

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

        $response = $this->actingAs($volunteer)
            ->postJson('/api/events/99999/resolve');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Event tidak ditemukan']);
    }
}

