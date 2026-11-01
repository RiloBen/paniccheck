<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use Carbon\Carbon;

class QuickCheckController extends Controller
{
    /**
     * Basis data spasial komprehensif wilayah dan jalan utama Surabaya.
     * Digunakan untuk geocoding instan dan ekstraksi entitas lokasi lokal (0 milidetik).
     */
    protected array $surabayaLocations = [
        'mayjen_sungkono' => [
            'pattern' => '/(sungkono|mayjen\s*sungkono|ciputra\s*world|underpass\s*mayjen)/i',
            'name' => 'Jl. Mayjen Sungkono (Dekat Ciputra World)',
            'short_name' => 'Jl. Mayjen Sungkono',
            'latitude' => -7.2892,
            'longitude' => 112.7153,
        ],
        'raya_darmo' => [
            'pattern' => '/(darmo|raya\s*darmo|taman\s*bungkul|bungkul|al-falah|al\s*falah)/i',
            'name' => 'Jl. Raya Darmo (Depan Taman Bungkul)',
            'short_name' => 'Jl. Raya Darmo',
            'latitude' => -7.2858,
            'longitude' => 112.7383,
        ],
        'kertajaya' => [
            'pattern' => '/(kertajaya|kertajaya\s*indah|klampis|its\b|mulyorejo)/i',
            'name' => 'Jl. Raya Kertajaya Indah',
            'short_name' => 'Jl. Raya Kertajaya Indah',
            'latitude' => -7.2797,
            'longitude' => 112.7758,
        ],
        'dharmawangsa' => [
            'pattern' => '/(dharmawangsa|rsud\s*soetomo|soetomo|unair\s*b|kampus\s*b)/i',
            'name' => 'Jl. Dharmawangsa (Depan RSUD Dr. Soetomo)',
            'short_name' => 'Jl. Dharmawangsa',
            'latitude' => -7.2705,
            'longitude' => 112.7562,
        ],
        'ahmad_yani' => [
            'pattern' => '/(ahmad\s*yani|a\.?\s*yani|bundaran\s*waru|royal\s*plaza|dolog|polda\s*jatim)/i',
            'name' => 'Jl. Frontage Ahmad Yani (Dekat Royal Plaza / Waru)',
            'short_name' => 'Jl. Frontage Ahmad Yani',
            'latitude' => -7.3150,
            'longitude' => 112.7340,
        ],
        'ngagel' => [
            'pattern' => '/(ngagel|ngagel\s*jaya|novotel|kali\s*ngagel|barata\s*jaya)/i',
            'name' => 'Jl. Ngagel (Kawasan Kali Ngagel)',
            'short_name' => 'Jl. Ngagel',
            'latitude' => -7.2915,
            'longitude' => 112.7482,
        ],
        'tunjungan_basra' => [
            'pattern' => '/(tunjungan|basuki\s*rahmat|basra|tunjungan\s*plaza|tp\b|embong\s*malang)/i',
            'name' => 'Jl. Basuki Rahmat / Tunjungan',
            'short_name' => 'Jl. Basuki Rahmat / Tunjungan',
            'latitude' => -7.2625,
            'longitude' => 112.7390,
        ],
        'pemuda' => [
            'pattern' => '/(pemuda|jalan\s*pemuda|grahadi|balai\s*pemuda|delta\s*plaza|surabaya\s*plaza)/i',
            'name' => 'Jl. Pemuda (Dekat Gedung Grahadi)',
            'short_name' => 'Jl. Pemuda',
            'latitude' => -7.2650,
            'longitude' => 112.7480,
        ],
        'wiyung' => [
            'pattern' => '/(wiyung|menganti|babatan|kebraon|graha\s*famili|pakuwon\s*trade)/i',
            'name' => 'Jl. Raya Menganti Wiyung',
            'short_name' => 'Jl. Raya Menganti Wiyung',
            'latitude' => -7.3102,
            'longitude' => 112.6950,
        ],
        'rungkut' => [
            'pattern' => '/(rungkut|upn\b|sier|rungkut\s*madya|rungkut\s*industri|rungkut\s*asri)/i',
            'name' => 'Kawasan Industri & Pemukiman Rungkut',
            'short_name' => 'Kawasan Rungkut',
            'latitude' => -7.3275,
            'longitude' => 112.7660,
        ],
        'kenjeran' => [
            'pattern' => '/(kenjeran|suramadu|pantai\s*kenjeran|taman\s*hiburan\s*pantai)/i',
            'name' => 'Jl. Kenjeran (Akses Suramadu)',
            'short_name' => 'Jl. Kenjeran',
            'latitude' => -7.2430,
            'longitude' => 112.7780,
        ],
        'wonokromo' => [
            'pattern' => '/(wonokromo|stasiun\s*wonokromo|dtc|joyoboyo|kebun\s*binatang|kbs\b)/i',
            'name' => 'Kawasan Wonokromo (Sekitar Joyoboyo / DTC)',
            'short_name' => 'Kawasan Wonokromo',
            'latitude' => -7.3005,
            'longitude' => 112.7370,
        ],
        'diponegoro' => [
            'pattern' => '/(diponegoro|jl\.?\s*diponegoro|rkz\b|st\s*vincentius)/i',
            'name' => 'Jl. Diponegoro (Dekat RS RKZ)',
            'short_name' => 'Jl. Diponegoro',
            'latitude' => -7.2870,
            'longitude' => 112.7310,
        ],
        'hr_muhammad' => [
            'pattern' => '/(hr\s*muhammad|h\.?r\.?\s*muhammad|prada\s*kali\s*kendal)/i',
            'name' => 'Jl. Mayjen HR Muhammad',
            'short_name' => 'Jl. HR Muhammad',
            'latitude' => -7.2850,
            'longitude' => 112.6930,
        ],
        'merr' => [
            'pattern' => '/(merr\b|ir\s*soekarno|dr\s*ir\s*soekarno|soekarno\s*hatta)/i',
            'name' => 'Jl. Dr. Ir. H. Soekarno (MERR)',
            'short_name' => 'Jl. MERR Surabaya',
            'latitude' => -7.3050,
            'longitude' => 112.7820,
        ],
        'jemursari' => [
            'pattern' => '/(jemursari|jemur\s*andayani|rsi\s*jemursari)/i',
            'name' => 'Jl. Raya Jemursari',
            'short_name' => 'Jl. Raya Jemursari',
            'latitude' => -7.3180,
            'longitude' => 112.7450,
        ],
        'manyar' => [
            'pattern' => '/(manyar|manyar\s*kertoarjo|terminal\s*bratang|bratang)/i',
            'name' => 'Jl. Manyar Kertoarjo (Bratang)',
            'short_name' => 'Jl. Manyar Kertoarjo',
            'latitude' => -7.2820,
            'longitude' => 112.7630,
        ],
        'gubeng' => [
            'pattern' => '/(gubeng|stasiun\s*gubeng|raya\s*gubeng|sumatra|sulawesi)/i',
            'name' => 'Kawasan Raya Gubeng (Dekat Stasiun Gubeng)',
            'short_name' => 'Jl. Raya Gubeng',
            'latitude' => -7.2720,
            'longitude' => 112.7500,
        ],
        'kupang_jaya' => [
            'pattern' => '/(kupang\s*jaya|banyu\s*urip|pasar\s*kembang|girilaya)/i',
            'name' => 'Jl. Kupang Jaya / Banyu Urip',
            'short_name' => 'Jl. Kupang Jaya',
            'latitude' => -7.2750,
            'longitude' => 112.7100,
        ],
        'tandes_margomulyo' => [
            'pattern' => '/(tandes|margomulyo|manukan|balongsari)/i',
            'name' => 'Kawasan Tandes / Margomulyo',
            'short_name' => 'Kawasan Tandes',
            'latitude' => -7.2550,
            'longitude' => 112.6780,
        ],
        'benowo_gbt' => [
            'pattern' => '/(benowo|gelora\s*bung\s*tomo|gbt\b|pakal)/i',
            'name' => 'Kawasan Benowo / GBT',
            'short_name' => 'Kawasan Benowo',
            'latitude' => -7.2350,
            'longitude' => 112.6250,
        ],
        'ketintang_unesa' => [
            'pattern' => '/(ketintang|unesa\s*ketintang|gayungan|menanggal)/i',
            'name' => 'Kawasan Ketintang / Gayungan',
            'short_name' => 'Kawasan Ketintang',
            'latitude' => -7.3110,
            'longitude' => 112.7230,
        ],
        'bubutan' => [
            'pattern' => '/(bubutan|tugu\s*pahlawan|pasar\s*turi|kramat\s*gantung)/i',
            'name' => 'Kawasan Bubutan (Dekat Tugu Pahlawan)',
            'short_name' => 'Kawasan Bubutan',
            'latitude' => -7.2480,
            'longitude' => 112.7360,
        ],
        'arjuno' => [
            'pattern' => '/(arjuno|arjuna|pn\s*surabaya)/i',
            'name' => 'Jl. Arjuno Surabaya',
            'short_name' => 'Jl. Arjuno',
            'latitude' => -7.2680,
            'longitude' => 112.7280,
        ],
    ];

    public function scan(Request $request)
    {
        // 1. Validasi input teks
        $request->validate([
            'text' => 'required|string|max:3000',
        ]);

        $rawText = $request->text;
        $text = strtolower($rawText);

        // 2. Scanner Ketinggian Air (Regex NLP)
        $waterLevel = null;
        if (preg_match('/(sepinggang|pinggang|sedada|dada|leher|1\s*meter|1\s*m\b|1\.5\s*m|dalam\s*banget|tenggelam|parah\s*banget|arus\s*deras)/i', $rawText)) {
            $waterLevel = 'sepinggang_keatas';
        } elseif (preg_match('/(selutut|lutut|sepaha|paha|setengah\s*meter|50\s*cm|60\s*cm|70\s*cm|sedang)/i', $rawText)) {
            $waterLevel = 'selutut';
        } elseif (preg_match('/(mata\s*kaki|semata\s*kaki|mata_kaki|tumit|10\s*cm|20\s*cm|30\s*cm|dangkal|genangan\s*ringan)/i', $rawText)) {
            $waterLevel = 'semata_kaki';
        }

        // 3. Deteksi Sentimen Banjir vs Jalur Aman (dengan penanganan negasi seperti "tidak ada banjir")
        $hasExplicitSafe = (bool) preg_match('/(tidak\s*(ada\s*)?banjir|bebas\s*banjir|nihil\s*genangan|tidak\s*tergenang|tidak\s*banjir|surut\s*total|sudah\s*surut)/i', $rawText);
        $hasSafeWords = (bool) preg_match('/(lancar|aman|surut|kering|sudah\s*lewat|terkendali)/i', $rawText);
        $hasSafeIndicator = $hasExplicitSafe || $hasSafeWords;

        // Hapus frase negasi agar "tidak ada banjir" tidak terdeteksi positif sebagai banjir
        $textWithoutNegations = preg_replace('/(tidak\s*(ada\s*)?banjir|bebas\s*banjir|nihil\s*genangan|tidak\s*tergenang|tidak\s*banjir)/i', '', $rawText);
        $hasFloodIndicator = (bool) preg_match('/(banjir|genangan|tergenang|air\s*naik|luapan|meluap|tenggelam|mogok|waspada|macet\s*total|arus|bencana|darurat|sepinggang|selutut|mata\s*kaki)/i', $textWithoutNegations);

        // Jika teks menyebut banjir tapi tidak mencantumkan ketinggian spesifik, default ke selutut
        if ($hasFloodIndicator && !$waterLevel) {
            $waterLevel = 'selutut';
        }

        // 4. Ekstraksi Entitas Lokasi (Pencocokan Cerdas Surabaya + Dynamic NLP)
        $detectedLocation = null;
        foreach ($this->surabayaLocations as $loc) {
            if (preg_match($loc['pattern'], $rawText)) {
                $detectedLocation = $loc;
                break;
            }
        }

        // Fallback Dynamic NLP: Ekstraksi nama jalan/daerah dari teks bebas (apapun teks yang dicopy-paste)
        if (!$detectedLocation) {
            if (preg_match('/(?:\b(?:jl\.?|jalan|daerah|kawasan|wilayah|depan|sekitar|arah|sepanjang|gang|komplek)\b)\s+([A-Za-z0-9\s\-]{3,35})/i', $rawText, $matches)) {
                $extractedStreet = trim($matches[1]);
                // Bersihkan kata sambung di ujung teks
                $extractedStreet = preg_replace('/\s+(banjir|air|macet|tergenang|dan|di|ke|dari|ada|sudah|lancar|aman).*$/i', '', $extractedStreet);
                if (strlen($extractedStreet) >= 3) {
                    $detectedLocation = [
                        'name' => 'Jl. ' . ucwords($extractedStreet) . ' (Surabaya)',
                        'short_name' => 'Jl. ' . ucwords($extractedStreet),
                        'latitude' => -7.2575, // Centroid Surabaya
                        'longitude' => 112.7521,
                    ];
                }
            }
        }

        // 5. Pencocokan dengan Basis Data Kejadian Riil (ACTIVE, STALE, RESOLVED)
        $allEvents = Event::with('reports')->get();
        $matchedEvent = null;

        if ($detectedLocation) {
            $locQuery = strtolower($detectedLocation['short_name']);

            foreach ($allEvents as $event) {
                // Cocokkan via location_name atau deskripsi laporan terkait
                $eventLoc = strtolower($event->location_name ?? '');
                $reportsDesc = $event->reports->pluck('description')->join(' ');

                $isNameMatch = !empty($eventLoc) && (str_contains($eventLoc, $locQuery) || str_contains($locQuery, $eventLoc));
                $isDescMatch = !empty($reportsDesc) && preg_match('/' . preg_quote(str_replace('jl. ', '', $locQuery), '/') . '/i', $reportsDesc);

                // Cocokkan via jarak koordinat (< 1 km) jika lokasi terdaftar
                $isCoordMatch = false;
                if ($event->latitude && $event->longitude && $detectedLocation['latitude'] != -7.2575) {
                    $distKm = $this->calculateHaversineDistance(
                        $detectedLocation['latitude'],
                        $detectedLocation['longitude'],
                        $event->latitude,
                        $event->longitude
                    );
                    $isCoordMatch = ($distKm <= 1.0);
                }

                if ($isNameMatch || $isDescMatch || $isCoordMatch) {
                    $matchedEvent = $event;
                    break;
                }
            }
        } else {
            // Jika lokasi kamus tidak kena, coba cari apakah teks mengandung kata dari nama event di DB
            foreach ($allEvents as $event) {
                if ($event->location_name) {
                    $words = explode(' ', strtolower($event->location_name));
                    foreach ($words as $word) {
                        if (strlen($word) > 4 && str_contains($text, $word)) {
                            $matchedEvent = $event;
                            break 2;
                        }
                    }
                }
            }
        }

        // 6. Evaluasi Hasil Akhir (Mencakup data dummy seeder maupun teks bebas apa pun)

        // Skenario A: Cocok dengan Event di Database (ACTIVE, STALE, atau RESOLVED)
        if ($matchedEvent) {
            $locationLabel = $matchedEvent->location_name ?: ($detectedLocation['name'] ?? 'Titik Kejadian Terdaftar');

            return response()->json([
                'status' => 'success',
                'match_type' => $matchedEvent->status, // 'ACTIVE', 'STALE', atau 'RESOLVED'
                'event' => [
                    'id' => $matchedEvent->id,
                    'status' => $matchedEvent->status,
                    'location_name' => $locationLabel,
                    'water_level' => $matchedEvent->water_level,
                    'reports_count' => $matchedEvent->reports->count(),
                    'latitude' => (float) $matchedEvent->latitude,
                    'longitude' => (float) $matchedEvent->longitude,
                ],
                'extracted_data' => [
                    'water_level' => $waterLevel ?: $matchedEvent->water_level,
                    'location_hint' => $locationLabel,
                    'event_id' => $matchedEvent->id,
                    'latitude' => (float) $matchedEvent->latitude,
                    'longitude' => (float) $matchedEvent->longitude,
                ],
                'message' => 'Titik bencana ditemukan pada peta (' . $matchedEvent->status . ')!',
            ]);
        }

        // Skenario B: Lokasi dikenali dan teks menyatakan aman / bebas banjir
        if ($detectedLocation && ($hasSafeIndicator || !$hasFloodIndicator)) {
            return response()->json([
                'status' => 'success',
                'match_type' => 'SAFE',
                'event' => null,
                'extracted_data' => [
                    'water_level' => null,
                    'location_hint' => $detectedLocation['name'],
                    'event_id' => null,
                    'latitude' => (float) $detectedLocation['latitude'],
                    'longitude' => (float) $detectedLocation['longitude'],
                ],
                'message' => 'Jalur aman terpantau. Tidak ada indikasi titik banjir aktif di sekitar ' . $detectedLocation['short_name'] . '.',
            ]);
        }

        // Skenario C: Belum ada di Database, TAPI Teks Mengindikasikan Banjir Baru (Fitur Utama Teks Bebas)
        if ($hasFloodIndicator) {
            $locationLabel = $detectedLocation 
                ? $detectedLocation['name'] 
                : 'Lokasi Terdeteksi dari Pesan Warga';

            $lat = $detectedLocation ? (float) $detectedLocation['latitude'] : -7.2892;
            $lon = $detectedLocation ? (float) $detectedLocation['longitude'] : 112.7153;

            return response()->json([
                'status' => 'success',
                'match_type' => 'NEW_DETECTION',
                'event' => null,
                'extracted_data' => [
                    'is_new_indication' => true,
                    'water_level' => $waterLevel ?: 'selutut',
                    'location_hint' => $locationLabel,
                    'event_id' => null,
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'category' => 'Banjir',
                    'raw_text' => $rawText,
                ],
                'message' => 'Indikasi titik banjir baru terdeteksi dari teks! Klik tombol untuk plot ke peta.',
            ]);
        }

        // Skenario D: Teks sembarang (tidak terdeteksi lokasi maupun indikasi banjir)
        return response()->json([
            'status' => 'success',
            'match_type' => 'UNRECOGNIZED',
            'event' => null,
            'extracted_data' => [
                'water_level' => null,
                'location_hint' => null,
                'event_id' => null,
                'latitude' => null,
                'longitude' => null,
                'raw_text' => $rawText,
            ],
            'message' => 'Teks terbaca, namun belum terdeteksi nama jalan atau kondisi banjir spesifik. Masukkan nama jalan Surabaya atau tempel pesan broadcast banjir.',
        ]);
    }

    /**
     * Hitung jarak Haversine antara dua titik koordinat (dalam km).
     */
    private function calculateHaversineDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
