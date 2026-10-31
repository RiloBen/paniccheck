<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;

class QuickCheckController extends Controller
{
    public function scan(Request $request)
    {
        // Pastikan warga mengirimkan teks pesan WA-nya
        $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $text = strtolower($request->text);

        // 1. Scanner Tinggi Air (Mencari kata kunci spesifik)
        $waterLevel = null;
        if (preg_match('/(sepinggang|pinggang|1 meter|dada|sedada|leher)/', $text)) {
            $waterLevel = 'sepinggang_keatas';
        } elseif (preg_match('/(selutut|lutut|paha|50 cm|50cm)/', $text)) {
            $waterLevel = 'selutut';
        } elseif (preg_match('/(mata kaki|semata kaki|mata_kaki|10 cm|20 cm|30 cm)/', $text)) {
            $waterLevel = 'semata_kaki';
        }

        // 2. Scanner Lokasi (Mencocokkan dengan event yang sedang ACTIVE di database)
        $activeEvents = Event::where('status', 'ACTIVE')->get();
        $matchedEvent = null;

        foreach ($activeEvents as $event) {
            // Pastikan event memiliki nama lokasi
            if ($event->location_name) {
                $locationName = strtolower($event->location_name);
                
                // Pecah nama jalan jadi kata-kata (misal: "Mayjen Sungkono" jadi "mayjen", "sungkono")
                $keywords = explode(' ', $locationName);
                $matchCount = 0;
                
                foreach ($keywords as $word) {
                    // Cek jika panjang kata lebih dari 3 huruf dan teks WA mengandung kata tersebut
                    if (strlen($word) > 3 && strpos($text, $word) !== false) {
                        $matchCount++;
                    }
                }

                // Jika minimal 1 kata kunci penting cocok, anggap itu lokasi yang dimaksud
                if ($matchCount > 0) {
                    $matchedEvent = $event;
                    break; // Berhenti mencari jika sudah ketemu
                }
            }
        }

        // 3. Kembalikan hasil pemindaian ke Frontend
        return response()->json([
            'status' => 'success',
            'extracted_data' => [
                'water_level' => $waterLevel,
                'location_hint' => $matchedEvent ? $matchedEvent->location_name : null,
                'event_id' => $matchedEvent ? $matchedEvent->id : null,
                'latitude' => $matchedEvent ? $matchedEvent->latitude : null,
                'longitude' => $matchedEvent ? $matchedEvent->longitude : null,
            ],
            'message' => $matchedEvent 
                ? 'Potensi lokasi ditemukan di peta aktif!' 
                : 'Tidak ada kecocokan di peta. Silakan buat laporan baru.'
        ]);
    }
}