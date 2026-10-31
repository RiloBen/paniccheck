<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Mengecek apakah email admin sudah ada agar tidak error duplikat saat dijalankan berkali-kali
        $adminEmail = 'admin@paniccheck.com';
        
        if (!User::where('email', $adminEmail)->exists()) {
            User::create([
                'name' => 'Komandan Relawan',
                'email' => $adminEmail,
                'password' => Hash::make('rahasia123'), // Password harus di-hash (enkripsi)
                'role' => 'admin', // Kunci utamanya ada di sini, role langsung diset admin
            ]);
            
            $this->command->info('Akun Admin berhasil dibuat!');
        } else {
            $this->command->info('Akun Admin sudah ada di database.');
        }

        $relawanEmail = 'relawan@paniccheck.com';
        if (!User::where('email', $relawanEmail)->exists()) {
            User::create([
                'name' => 'Relawan Lapangan',
                'email' => $relawanEmail,
                'password' => Hash::make('rahasia123'),
                'role' => 'relawan',
            ]);
            $this->command->info('Akun Relawan berhasil dibuat!');
        }
    }
}