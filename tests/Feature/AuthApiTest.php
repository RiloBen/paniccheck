<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Uji pendaftaran akun warga baru berhasil dengan data yang valid.
     */
    public function test_citizen_can_register_with_valid_data(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'access_token',
            ])
            ->assertJsonPath('user.role', 'user');

        $this->assertDatabaseHas('users', [
            'email' => 'budi@example.com',
            'role' => 'user',
        ]);
    }

    /**
     * Uji validasi gagal jika format email salah atau email sudah terdaftar.
     */
    public function test_registration_fails_with_duplicate_or_invalid_email(): void
    {
        User::create([
            'name' => 'Warga Lama',
            'email' => 'duplikat@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Warga Baru',
            'email' => 'duplikat@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Uji validasi gagal jika panjang password kurang dari 6 karakter.
     */
    public function test_registration_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Warga Baru',
            'email' => 'pendek@example.com',
            'password' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Uji login berhasil dengan kredensial yang valid dan menerima token Sanctum.
     */
    public function test_user_can_login_with_correct_credentials_and_receive_token(): void
    {
        User::create([
            'name' => 'Siti Nurhaliza',
            'email' => 'siti@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'siti@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user',
                'access_token',
            ]);
    }

    /**
     * Uji login gagal jika kata sandi salah (HTTP 401).
     */
    public function test_login_fails_with_incorrect_password(): void
    {
        User::create([
            'name' => 'Siti Nurhaliza',
            'email' => 'siti@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'siti@example.com',
            'password' => 'passwordsalah',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Email atau password salah']);
    }

    /**
     * Uji logout berhasil dan token dicabut (HTTP 200).
     */
    public function test_user_can_logout_and_revoke_token(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Berhasil logout, akses token telah dihancurkan.']);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    /**
     * Uji warga dapat upgrade pangkat menjadi relawan (HTTP 200).
     */
    public function test_citizen_can_upgrade_to_relawan(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/upgrade-relawan');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Selamat! Akun Anda telah ditingkatkan menjadi Relawan.',
                'user' => ['role' => 'relawan'],
            ]);

        $this->assertEquals('relawan', $user->fresh()->role);
    }

    /**
     * Uji relawan atau admin tidak dapat melakukan upgrade ulang (HTTP 400).
     */
    public function test_already_relawan_or_admin_cannot_upgrade_again(): void
    {
        $relawan = User::factory()->create(['role' => 'relawan']);

        $response = $this->actingAs($relawan, 'sanctum')
            ->postJson('/api/upgrade-relawan');

        $response->assertStatus(400)
            ->assertJson(['message' => 'Akun Anda sudah memiliki akses khusus.']);
    }
}


