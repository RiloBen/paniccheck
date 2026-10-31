<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema; // Ini wajib ditambahkan agar bisa mengatur sensor
use App\Models\Event;
use App\Models\Report;
use Carbon\Carbon;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Matikan sementara alarm sensor rantai (Foreign Key)
        Schema::disableForeignKeyConstraints();

        // 2. Bersihkan data lama dengan aman
        Report::truncate();
        Event::truncate();

        // 3. Nyalakan kembali alarmnya agar database kembali kebal
        Schema::enableForeignKeyConstraints();

        $now = Carbon::now();

        // Skenario 1: Banjir AKTIF
        $eventAktif = Event::create([
            'category' => 'banjir',
            'latitude' => -7.289200,
            'longitude' => 112.715300,
            'status' => 'ACTIVE',
            'created_at' => $now->copy()->subMinutes(30),
            'updated_at' => $now,
        ]);

        Report::insert([
            ['event_id' => $eventAktif->id, 'user_id' => 1, 'latitude' => -7.289210, 'longitude' => 112.715310, 'category' => 'banjir', 'water_level' => 'sepinggang_keatas', 'description' => 'Air masuk rumah cepat sekali', 'created_at' => $now->copy()->subMinutes(30), 'updated_at' => $now->copy()->subMinutes(30)],
            ['event_id' => $eventAktif->id, 'user_id' => 1, 'latitude' => -7.289190, 'longitude' => 112.715290, 'category' => 'banjir', 'water_level' => 'sepinggang_keatas', 'description' => 'Jalanan sudah tidak terlihat', 'created_at' => $now->copy()->subMinutes(15), 'updated_at' => $now->copy()->subMinutes(15)],
            ['event_id' => $eventAktif->id, 'user_id' => 1, 'latitude' => -7.289205, 'longitude' => 112.715305, 'category' => 'banjir', 'water_level' => 'sepinggang_keatas', 'description' => 'Butuh perahu karet segera', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Skenario 2: Banjir STALE
        $eventStale = Event::create([
            'category' => 'banjir',
            'latitude' => -7.285800,
            'longitude' => 112.738300,
            'status' => 'STALE',
            'created_at' => $now->copy()->subHours(3),
            'updated_at' => $now->copy()->subHours(3),
        ]);

        Report::insert([
            ['event_id' => $eventStale->id, 'user_id' => 1, 'latitude' => -7.285800, 'longitude' => 112.738300, 'category' => 'banjir', 'water_level' => 'semata_kaki', 'description' => 'Air mulai menggenang setelah hujan', 'created_at' => $now->copy()->subHours(3), 'updated_at' => $now->copy()->subHours(3)],
        ]);

        // Skenario 3: Banjir RESOLVED
        $eventResolved = Event::create([
            'category' => 'banjir',
            'latitude' => -7.279700,
            'longitude' => 112.775800,
            'status' => 'RESOLVED',
            'created_at' => $now->copy()->subDays(1),
            'updated_at' => $now->copy()->subDays(1),
        ]);

        Report::insert([
            ['event_id' => $eventResolved->id, 'user_id' => 1, 'latitude' => -7.279700, 'longitude' => 112.775800, 'category' => 'banjir', 'water_level' => 'selutut', 'description' => 'Genangan surut berangsur', 'created_at' => $now->copy()->subDays(1), 'updated_at' => $now->copy()->subDays(1)],
        ]);

        $this->command->info('Data simulasi demo Hacking Day berhasil disuntikkan!');
    }
}