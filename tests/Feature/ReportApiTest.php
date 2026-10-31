<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Uji warga tanpa login (anonim) dapat mengirimkan laporan darurat bencana (M2 / FR-1).
     */
    public function test_anonymous_citizen_can_submit_flood_report(): void
    {
        $payload = [
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'category' => 'Banjir',
            'water_level' => 'sepinggang_keatas',
            'description' => 'Genangan tinggi di depan pusat perbelanjaan, jalan ditutup warga.',
        ];

        $response = $this->postJson('/api/reports', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Laporan berhasil diproses!',
            ])
            ->assertJsonStructure(['message', 'event_id']);

        $this->assertDatabaseHas('reports', [
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'category' => 'Banjir',
            'water_level' => 'sepinggang_keatas',
            'user_id' => null, // Terbukti tanpa login
        ]);

        $this->assertDatabaseHas('events', [
            'category' => 'Banjir',
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Uji pembentukan event baru jika tidak ada event aktif dalam radius 500 meter (M3).
     */
    public function test_report_creates_new_event_when_no_active_event_nearby(): void
    {
        $this->assertEquals(0, Event::count());

        $response = $this->postJson('/api/reports', [
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'category' => 'Banjir',
            'water_level' => 'selutut',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, Event::count());

        $event = Event::first();
        $this->assertEquals('ACTIVE', $event->status);
        $this->assertEquals(-7.2892, (float)$event->latitude);
    }

    /**
     * Uji Agregasi Clustering Spasial (FR-2 / M3):
     * Dua laporan berkategori sama dalam jarak <= 500 m dan jeda waktu <= 2 jam
     * otomatis digabung ke dalam satu event tunggal (sliding window).
     */
    public function test_report_clusters_into_existing_event_within_500_meters(): void
    {
        // 1. Buat event aktif di Jl. Mayjen Sungkono
        $initialTime = Carbon::now()->subMinutes(30);
        $existingEvent = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.28920000,
            'longitude' => 112.71530000,
            'status' => 'ACTIVE',
            'created_at' => $initialTime,
            'updated_at' => $initialTime,
        ]);

        // Laporan warga pertama
        Report::create([
            'event_id' => $existingEvent->id,
            'latitude' => -7.28920000,
            'longitude' => 112.71530000,
            'category' => 'Banjir',
            'water_level' => 'selutut',
        ]);

        $this->assertEquals(1, Event::count());
        $this->assertEquals(1, Report::count());

        // 2. Laporan warga kedua masuk pada koordinat berjarak ~65 meter (<= 500 m)
        $response = $this->postJson('/api/reports', [
            'latitude' => -7.28950000,
            'longitude' => 112.71580000,
            'category' => 'Banjir',
            'water_level' => 'sepinggang_keatas',
            'description' => 'Arus air semakin kencang.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Laporan berhasil diproses!',
                'event_id' => $existingEvent->id,
            ]);

        // Pastikan TIDAK membuat event baru (tetap 1 event), namun laporan bertambah menjadi 2
        $this->assertEquals(1, Event::count());
        $this->assertEquals(2, Report::count());

        // Verifikasi sliding window: updated_at event terdorong maju
        $existingEvent->refresh();
        $this->assertTrue($existingEvent->updated_at->greaterThan($initialTime));
    }

    /**
     * Uji laporan di luar radius 500 meter membuat event baru terpisah (FR-2).
     */
    public function test_report_creates_distinct_event_when_distance_exceeds_500_meters(): void
    {
        // Event 1 di Mayjen Sungkono (-7.2892, 112.7153)
        $event1 = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.28920000,
            'longitude' => 112.71530000,
            'status' => 'ACTIVE',
        ]);

        // Laporan 2 di Kertajaya Indah (-7.2797, 112.7758) -> jarak ~6.7 km (> 500m)
        $response = $this->postJson('/api/reports', [
            'latitude' => -7.27970000,
            'longitude' => 112.77580000,
            'category' => 'Banjir',
            'water_level' => 'semata_kaki',
        ]);

        $response->assertStatus(200);

        // Harus terbentuk 2 event yang berbeda
        $this->assertEquals(2, Event::count());
        $response->assertJsonMissing(['event_id' => $event1->id]);
    }

    /**
     * Uji validasi input gagal jika koordinat atau kategori kosong (HTTP 422).
     */
    public function test_report_validation_fails_when_required_fields_are_missing(): void
    {
        $response = $this->postJson('/api/reports', [
            'water_level' => 'semata_kaki',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude', 'category']);
    }

    /**
     * Uji GET /api/my-reports membutuhkan otentikasi login (HTTP 401).
     */
    public function test_my_reports_requires_authentication(): void
    {
        $response = $this->getJson('/api/my-reports');

        $response->assertStatus(401);
    }

    /**
     * Uji pengguna login dapat mengambil riwayat laporan pribadi beserta status kejadian (FR-1 / PRD).
     */
    public function test_authenticated_user_can_view_own_report_history(): void
    {
        $user1 = \App\Models\User::create([
            'name' => 'Warga Pelapor 1',
            'email' => 'pelapor1@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'user',
        ]);

        $user2 = \App\Models\User::create([
            'name' => 'Warga Pelapor 2',
            'email' => 'pelapor2@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'user',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        // Laporan milik user1 (2 laporan dengan waktu berbeda)
        $report1 = Report::create([
            'event_id' => $event->id,
            'user_id' => $user1->id,
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'category' => 'Banjir',
            'water_level' => 'selutut',
            'description' => 'Laporan pertama user 1',
        ]);
        $report1->forceFill(['created_at' => Carbon::now()->subHours(2)])->save();

        $report2 = Report::create([
            'event_id' => $event->id,
            'user_id' => $user1->id,
            'latitude' => -7.2893,
            'longitude' => 112.7154,
            'category' => 'Banjir',
            'water_level' => 'sepinggang_keatas',
            'description' => 'Laporan kedua user 1',
        ]);
        $report2->forceFill(['created_at' => Carbon::now()->subMinutes(5)])->save();

        // Laporan milik user2 (1 laporan)
        Report::create([
            'event_id' => $event->id,
            'user_id' => $user2->id,
            'latitude' => -7.2894,
            'longitude' => 112.7155,
            'category' => 'Banjir',
            'water_level' => 'semata_kaki',
            'description' => 'Laporan milik user 2',
        ]);

        $response = $this->actingAs($user1, 'sanctum')
            ->getJson('/api/my-reports');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'event_id',
                        'water_level',
                        'description',
                        'event' => ['id', 'status'],
                    ]
                ]
            ]);

        // Hanya menerima 2 laporan milik user1
        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Terurut latest first (report2 terbaru berada di index 0)
        $this->assertEquals($report2->id, $data[0]['id']);
        $this->assertEquals($report1->id, $data[1]['id']);

        // Membawa status event
        $this->assertEquals('ACTIVE', $data[0]['event']['status']);
    }
}

