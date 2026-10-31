<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\Report;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Event ACTIVE: Mayjen Sungkono (Air tinggi, baru diperbarui)
        $event1 = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.28920000,
            'longitude' => 112.71530000,
            'status' => 'ACTIVE',
            'updated_at' => Carbon::now()->subMinutes(10),
            'created_at' => Carbon::now()->subMinutes(45),
        ]);

        Report::create([
            'event_id' => $event1->id,
            'latitude' => -7.28920000,
            'longitude' => 112.71530000,
            'category' => 'Banjir',
            'water_level' => 'sepinggang_keatas',
            'description' => 'Depan Ciputra World air sepinggang, motor mogok banyak.',
            'created_at' => Carbon::now()->subMinutes(45),
        ]);

        Report::create([
            'event_id' => $event1->id,
            'latitude' => -7.28950000,
            'longitude' => 112.71580000,
            'category' => 'Banjir',
            'water_level' => 'sepinggang_keatas',
            'description' => 'Arus cukup deras ke arah underpass.',
            'is_confirmation' => true,
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        // 2. Event STALE: Raya Darmo (Tidak ada update > 2 jam)
        $event2 = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.28580000,
            'longitude' => 112.73830000,
            'status' => 'STALE',
            'updated_at' => Carbon::now()->subHours(2)->subMinutes(20),
            'created_at' => Carbon::now()->subHours(3),
        ]);

        Report::create([
            'event_id' => $event2->id,
            'latitude' => -7.28580000,
            'longitude' => 112.73830000,
            'category' => 'Banjir',
            'water_level' => 'semata_kaki',
            'description' => 'Genangan semata kaki di depan Taman Bungkul.',
            'created_at' => Carbon::now()->subHours(3),
        ]);

        // 3. Event ACTIVE: Kertajaya Indah (Air sedang)
        $event3 = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.27970000,
            'longitude' => 112.77580000,
            'status' => 'ACTIVE',
            'updated_at' => Carbon::now()->subMinutes(25),
            'created_at' => Carbon::now()->subHours(1),
        ]);

        Report::create([
            'event_id' => $event3->id,
            'latitude' => -7.27970000,
            'longitude' => 112.77580000,
            'category' => 'Banjir',
            'water_level' => 'selutut',
            'description' => 'Jalan Kertajaya Indah tergenang setinggi lutut orang dewasa.',
            'created_at' => Carbon::now()->subHours(1),
        ]);

        // 4. Event RESOLVED: Dharmawangsa (Sudah surut)
        $event4 = Event::create([
            'category' => 'Banjir',
            'latitude' => -7.27050000,
            'longitude' => 112.75620000,
            'status' => 'RESOLVED',
            'updated_at' => Carbon::now()->subMinutes(50),
            'created_at' => Carbon::now()->subHours(2),
        ]);

        Report::create([
            'event_id' => $event4->id,
            'latitude' => -7.27050000,
            'longitude' => 112.75620000,
            'category' => 'Banjir',
            'water_level' => 'semata_kaki',
            'description' => 'Air sudah surut total, jalan bisa dilewati kembali.',
            'created_at' => Carbon::now()->subMinutes(50),
        ]);
    }
}

