<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    /**
     * Uji ketersediaan halaman utama dan status respons HTTP 200.
     */
    public function test_homepage_is_accessible_and_renders_home_view(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('home');
    }

    /**
     * Uji ketersediaan kontainer peta Leaflet dan aset library kritis di DOM.
     */
    public function test_homepage_contains_map_container_and_leaflet_assets(): void
    {
        $response = $this->get('/');

        // Kontainer id="map" untuk inisialisasi Leaflet OpenStreetMap
        $response->assertSee('id="map"', false);

        // Memastikan pustaka Leaflet & Alpine terpasang di layout
        $response->assertSee('unpkg.com/leaflet', false);
        $response->assertSee('unpkg.com/alpinejs', false);
    }

    /**
     * Uji elemen antarmuka laporan darurat, filter bar, dan tombol aksi lapor.
     */
    public function test_homepage_contains_emergency_report_modal_and_filter_elements(): void
    {
        $response = $this->get('/');

        // Header & Logo
        $response->assertSee('PANICCHECK');

        // Tombol FAB Lapor Darurat
        $response->assertSee('LAPOR BANJIR SEKARANG');

        // Filter status bencana
        $response->assertSee('Semua');
        $response->assertSee('Aktif');
        $response->assertSee('Perlu Konfirmasi');

        // Modal formulir pelaporan M2
        $response->assertSee('Lapor Banjir Darurat');
        $response->assertSee('Ambil Lokasi Saya');
    }

    /**
     * Uji integrasi kartu edukasi risiko kesehatan pasca-banjir Leptospirosis (S8).
     */
    public function test_homepage_contains_leptospirosis_health_advisory_text(): void
    {
        $response = $this->get('/');

        $response->assertSee('Waspada Leptospirosis');
    }
}
