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
}

