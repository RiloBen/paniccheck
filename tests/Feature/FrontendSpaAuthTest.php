<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontendSpaAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Uji 1: Halaman utama memuat seluruh elemen UI Mode Pengunjung Anonim (Guest) sesuai PRD:
     * - Tombol besar "Lapor Banjir" tanpa barrier login
     * - Tombol "Masuk / Relawan" di Navbar
     * - Modal Login berpelindung x-cloak (anti-berkedip)
     * - CSS rule [x-cloak] { display: none !important; }
     */
    public function test_homepage_contains_guest_ui_and_anti_flicker_modal(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // FAB Lapor Banjir Darurat
        $response->assertSee('LAPOR BANJIR SEKARANG', false);

        // Navbar tombol Masuk / Relawan
        $response->assertSee('Masuk / Relawan', false);

        // Modal login SPA anti-berkedip
        $response->assertSee('x-cloak', false);
        $response->assertSee('showLoginModal', false);
        $response->assertSee('submitLogin()', false);
        $response->assertSee('Masuk ke PANICCHECK', false);

        // CSS rule x-cloak
        $response->assertSee('[x-cloak]', false);
    }

    /**
     * Uji 2: Halaman utama memuat elemen UI Warga dan Relawan:
     * - Label "Mode Relawan Aktif"
     * - Menu dropdown Profil
     * - Tombol "Daftar Jadi Relawan" (hanya untuk warga biasa)
     * - Tombol aksi "Konfirmasi Surut / Update Status"
     */
    public function test_homepage_contains_citizen_and_volunteer_ui_elements(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Elemen Profil & Mode Relawan
        $response->assertSee('Mode Relawan Aktif', false);
        $response->assertSee('Daftar Jadi Relawan', false);
        $response->assertSee('submitUpgradeRelawan()', false);
        $response->assertSee('submitLogout()', false);

        // Tombol sakti relawan untuk penutupan status
        $response->assertSee('Konfirmasi Surut / Update Status', false);
    }

    /**
     * Uji 3: Flow lengkap Autentikasi SPA backend:
     * 1. Login user biasa
     * 2. Upgrade ke relawan
     * 3. Akses resolve event berhasil
     * 4. Logout token dihancurkan
     */
    public function test_spa_auth_full_lifecycle(): void
    {
        $password = 'rahasia123';
        $user = User::create([
            'name' => 'Warga Calon Relawan',
            'email' => 'calon@paniccheck.com',
            'password' => Hash::make($password),
            'role' => 'user',
        ]);

        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        // Langkah 1: Login via POST /api/login
        $loginRes = $this->postJson('/api/login', [
            'email' => 'calon@paniccheck.com',
            'password' => $password,
        ]);

        $loginRes->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'access_token'])
            ->assertJsonPath('user.role', 'user');

        $token = $loginRes->json('access_token');
        $this->assertNotEmpty($token);

        // Sebagai warga, belum boleh resolve event (403)
        $resolveFailRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/events/{$event->id}/resolve", [
                'latitude' => -7.2892,
                'longitude' => 112.7153,
            ]);
        $resolveFailRes->assertStatus(403);

        // Namun warga boleh konfirmasi banjir masih tinggi (200)
        $confirmRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/events/{$event->id}/confirm", [
                'latitude' => -7.2892,
                'longitude' => 112.7153,
                'description' => 'Air masih tergenang.',
            ]);
        $confirmRes->assertStatus(200);

        // Langkah 2: Upgrade ke Relawan via POST /api/upgrade-relawan
        $upgradeRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/upgrade-relawan');

        $upgradeRes->assertStatus(200)
            ->assertJsonPath('user.role', 'relawan');

        $this->assertEquals('relawan', $user->fresh()->role);

        // Langkah 3: Sekarang sebagai relawan, resolve event berhasil (200)
        $resolveSuccessRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/events/{$event->id}/resolve", [
                'latitude' => -7.2892,
                'longitude' => 112.7153,
            ]);
        $resolveSuccessRes->assertStatus(200)
            ->assertJson(['message' => 'Event berhasil ditutup dan ditandai RESOLVED.']);

        $this->assertEquals('RESOLVED', $event->fresh()->status);

        // Langkah 4: Logout via POST /api/logout
        $logoutRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $logoutRes->assertStatus(200)
            ->assertJson(['message' => 'Berhasil logout, akses token telah dihancurkan.']);

        // Token sudah hancur di database
        $this->assertCount(0, $user->fresh()->tokens);
    }

    /**
     * Uji 4: Pelaporan oleh pengguna login menyertakan user_id di database
     * agar riwayat laporan (/api/my-reports) tersinkronisasi.
     */
    public function test_authenticated_user_report_attaches_user_id(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/reports', [
                'latitude' => -7.2892,
                'longitude' => 112.7153,
                'category' => 'Banjir',
                'water_level' => 'sepinggang_keatas',
                'description' => 'Laporan warga login via SPA',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reports', [
            'user_id' => $user->id,
            'description' => 'Laporan warga login via SPA',
        ]);
    }

    /**
     * Uji 5: Kontrak respons JSON 401 dan 403 selalu memiliki atribut 'message'
     * agar ditangkap dengan benar oleh Global Error Handling Toast Frontend.
     */
    public function test_backend_json_messages_for_401_and_403_match_frontend_contract(): void
    {
        // 401 Unauthorized
        $unauthRes = $this->getJson('/api/my-reports');
        $unauthRes->assertStatus(401)
            ->assertJsonStructure(['message']);

        // 403 Forbidden
        $citizen = User::factory()->create(['role' => 'user']);
        $event = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
            'status' => 'ACTIVE',
        ]);

        $forbiddenRes = $this->actingAs($citizen, 'sanctum')
            ->postJson("/api/events/{$event->id}/resolve");
        $forbiddenRes->assertStatus(403)
            ->assertJsonStructure(['message']);
    }
}
