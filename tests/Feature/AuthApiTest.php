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
}

