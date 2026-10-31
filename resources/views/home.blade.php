@extends('layouts.app')

@section('title', 'PANICCHECK - Peta Pemantauan & Laporan Banjir Surabaya')

@section('content')
<div x-data="paniccheckApp()" x-init="init()" class="w-full h-full relative">

    <!-- Interactive Leaflet Map (M4) -->
    @include('components.map')

    <!-- Floating Action Button: Lapor Banjir (M2) & Quick Stats Bar -->
    <div class="absolute bottom-6 inset-x-0 z-30 flex flex-col items-center pointer-events-none px-4">
        <!-- Live Alert Banner (Mobile-friendly) -->
        <div class="mb-3 pointer-events-auto bg-slate-900/90 backdrop-blur-md border border-slate-700/80 px-4 py-2 rounded-2xl shadow-xl flex items-center space-x-3 text-xs text-slate-200">
            <span class="flex h-2 w-2 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
            </span>
            <span class="font-medium">
                <span class="font-bold text-white" x-text="events.filter(e => e.status === 'ACTIVE').length"></span> Titik Banjir Aktif
            </span>
            <span class="text-slate-500">•</span>
            <span class="text-slate-400">
                <span class="text-amber-400 font-bold" x-text="events.filter(e => e.status === 'STALE').length"></span> Butuh Verifikasi
            </span>
        </div>

        <!-- Main FAB: Lapor Banjir Darurat -->
        <div class="pointer-events-auto flex items-center space-x-3">
            <button @click="openReportModal()" 
                    class="bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 active:scale-95 text-white font-extrabold py-3.5 px-6 rounded-full shadow-2xl shadow-red-600/40 border border-red-400/40 flex items-center space-x-2.5 transition transform text-sm tracking-wide">
                <span class="text-lg animate-bounce">🚨</span>
                <span>LAPOR BANJIR SEKARANG</span>
            </button>
        </div>
    </div>

    <!-- Event Detail Drawer (M4 / M5) -->
    @include('components.event-drawer')

    <!-- Report Modal (M2) -->
    @include('components.report-modal')

</div>
@endsection

@push('scripts')
<script>
function paniccheckApp() {
    return {
        map: null,
        markersLayer: null,
        events: [],
        selectedEvent: null,
        currentFilter: 'all',
        showReportModal: false,
        isDetectingGPS: false,
        locationAccuracy: null,
        isSubmitting: false,
        photoPreview: null,
        photoFile: null,

        form: {
            latitude: -7.2892,
            longitude: 112.7153,
            category: 'banjir',
            water_level: 'sepinggang_keatas',
            description: ''
        },

        init() {
            this.initMap();
            this.fetchEvents();
        },

        initMap() {
            // Inisialisasi peta Leaflet berpusat di Surabaya
            this.map = L.map('map', {
                zoomControl: false,
                attributionControl: true
            }).setView([-7.2819, 112.7483], 13);

            // Tambahkan kontrol zoom di posisi kanan bawah
            L.control.zoom({ position: 'topleft' }).addTo(this.map);

            // Tile layer OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(this.map);

            this.markersLayer = L.layerGroup().addTo(this.map);

            // Event klik peta untuk memindahkan koordinat form
            this.map.on('click', (e) => {
                if (this.showReportModal) {
                    this.form.latitude = parseFloat(e.latlng.lat.toFixed(6));
                    this.form.longitude = parseFloat(e.latlng.lng.toFixed(6));
                    this.locationAccuracy = null;
                }
            });
        },

        async fetchEvents() {
            try {
                const res = await fetch('/api/events');
                if (res.ok) {
                    const json = await res.json();
                    const rawList = Array.isArray(json) ? json : (json.data || []);
                    if (rawList.length > 0) {
                        this.events = rawList.map(ev => ({
                            id: ev.id,
                            category: ev.category || 'banjir',
                            status: ev.status || 'ACTIVE',
                            latitude: parseFloat(ev.latitude),
                            longitude: parseFloat(ev.longitude),
                            water_level: ev.water_level || (ev.reports && ev.reports.length ? ev.reports[ev.reports.length - 1].water_level : 'selutut'),
                            reports_count: ev.reports_count || (ev.reports ? ev.reports.length : 1),
                            is_verified: !!ev.is_verified,
                            location_name: ev.location_name || ('Titik Koordinat ' + parseFloat(ev.latitude).toFixed(4) + ', ' + parseFloat(ev.longitude).toFixed(4)),
                            updated_at_human: ev.updated_at_human || 'Baru saja'
                        }));
                    } else {
                        this.events = this.getDemoEvents();
                    }
                    this.renderMarkers();
                }
            } catch (err) {
                console.warn('Gagal memuat API events, menggunakan mock lokal:', err);
                this.events = this.getDemoEvents();
                this.renderMarkers();
            }
        },

        getDemoEvents() {
            return [
                {
                    id: 1,
                    category: 'banjir',
                    status: 'ACTIVE',
                    latitude: -7.2892,
                    longitude: 112.7153,
                    water_level: 'sepinggang_keatas',
                    reports_count: 6,
                    is_verified: true,
                    location_name: 'Jl. Mayjen Sungkono (Dekat Ciputra World)',
                    updated_at_human: '8 menit lalu'
                },
                {
                    id: 2,
                    category: 'banjir',
                    status: 'STALE',
                    latitude: -7.2858,
                    longitude: 112.7383,
                    water_level: 'semata_kaki',
                    reports_count: 2,
                    is_verified: false,
                    location_name: 'Jl. Raya Darmo (Depan Taman Bungkul)',
                    updated_at_human: '2 jam 15 mnt lalu'
                },
                {
                    id: 3,
                    category: 'banjir',
                    status: 'ACTIVE',
                    latitude: -7.2797,
                    longitude: 112.7758,
                    water_level: 'selutut',
                    reports_count: 4,
                    is_verified: true,
                    location_name: 'Jl. Raya Kertajaya Indah',
                    updated_at_human: '25 menit lalu'
                },
                {
                    id: 4,
                    category: 'banjir',
                    status: 'RESOLVED',
                    latitude: -7.2705,
                    longitude: 112.7562,
                    water_level: 'semata_kaki',
                    reports_count: 5,
                    is_verified: true,
                    location_name: 'Jl. Dharmawangsa (Depan RSUD Dr. Soetomo)',
                    updated_at_human: '45 menit lalu'
                }
            ];
        },

        renderMarkers() {
            if (!this.markersLayer) return;
            this.markersLayer.clearLayers();

            const filtered = this.events.filter(e => {
                if (this.currentFilter === 'all') return true;
                return e.status === this.currentFilter;
            });

            filtered.forEach(ev => {
                const markerIcon = this.createMarkerIcon(ev);
                const marker = L.marker([ev.latitude, ev.longitude], { icon: markerIcon });

                marker.on('click', () => {
                    this.selectedEvent = ev;
                    this.map.panTo([ev.latitude, ev.longitude]);
                });

                marker.addTo(this.markersLayer);
            });
        },

        createMarkerIcon(ev) {
            let bgClass = 'bg-red-600';
            let pulseHtml = '<span class="absolute -inset-1 rounded-full bg-red-500 opacity-75 marker-pulse"></span>';
            let iconGlyph = '🌊';

            if (ev.status === 'RESOLVED') {
                bgClass = 'bg-emerald-600';
                pulseHtml = '';
                iconGlyph = '✓';
            } else if (ev.status === 'STALE') {
                bgClass = 'bg-slate-600 border border-amber-400';
                pulseHtml = '';
                iconGlyph = '⏳';
            } else if (ev.water_level === 'selutut') {
                bgClass = 'bg-amber-500';
                pulseHtml = '<span class="absolute -inset-1 rounded-full bg-amber-400 opacity-60 marker-pulse"></span>';
            } else if (ev.water_level === 'semata_kaki') {
                bgClass = 'bg-yellow-500';
                pulseHtml = '';
            }

            const html = `
                <div class="relative flex items-center justify-center w-8 h-8 cursor-pointer">
                    ${pulseHtml}
                    <div class="relative w-8 h-8 rounded-full ${bgClass} text-white flex items-center justify-center shadow-lg border-2 border-white text-xs font-bold">
                        ${iconGlyph}
                    </div>
                </div>
            `;

            return L.divIcon({
                className: 'custom-flood-marker',
                html: html,
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });
        },

        setFilter(filter) {
            this.currentFilter = filter;
            this.renderMarkers();
        },

        openReportModal() {
            this.selectedEvent = null;
            this.showReportModal = true;
            if (!this.locationAccuracy) {
                this.detectGPS();
            }
        },

        openReportWithCoordinates(lat, lng) {
            this.selectedEvent = null;
            this.form.latitude = lat;
            this.form.longitude = lng;
            this.locationAccuracy = 10;
            this.showReportModal = true;
        },

        detectGPS() {
            if (!navigator.geolocation) {
                this.showToast('GPS tidak didukung browser ini. Klik peta untuk pilih lokasi.', 'warning');
                return;
            }

            this.isDetectingGPS = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.form.latitude = parseFloat(pos.coords.latitude.toFixed(6));
                    this.form.longitude = parseFloat(pos.coords.longitude.toFixed(6));
                    this.locationAccuracy = pos.coords.accuracy;
                    this.isDetectingGPS = false;
                    this.showToast('Lokasi GPS berhasil terdeteksi!', 'success');
                    if (this.map) {
                        this.map.panTo([this.form.latitude, this.form.longitude]);
                    }
                },
                (err) => {
                    this.isDetectingGPS = false;
                    console.warn('Geolocation error:', err);
                    this.showToast('Izin GPS tidak diberikan. Menggunakan koordinat default Surabaya.', 'warning');
                },
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
            );
        },

        handlePhotoChange(e) {
            const file = e.target.files[0];
            if (file) {
                this.photoFile = file;
                const reader = new FileReader();
                reader.onload = (event) => {
                    this.photoPreview = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        },

        async submitReport() {
            if (!this.form.latitude || !this.form.longitude) {
                this.showToast('Koordinat lokasi wajib terisi!', 'error');
                return;
            }

            this.isSubmitting = true;

            // UBAH: Gunakan FormData agar bisa membawa file foto fisik
            const formData = new FormData();
            formData.append('latitude', this.form.latitude);
            formData.append('longitude', this.form.longitude);
            formData.append('category', this.form.category);
            formData.append('water_level', this.form.water_level);
            formData.append('description', this.form.description);
            
            // Masukkan foto ke dalam paket kiriman jika warga melampirkannya
            if (this.photoFile) {
                formData.append('photo', this.photoFile);
            }

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('/api/reports', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                        // PENTING: 'Content-Type' tidak boleh ditulis di sini. 
                        // Browser akan otomatis mengatur boundary multipart/form-data.
                    },
                    body: formData // Kirim formData, bukan JSON.stringify
                });

                if (res.ok) {
                    const data = await res.json();
                    this.showToast('Laporan berhasil dikirim & teragregasi!', 'success');
                    // Mengirim seluruh form data (termasuk foto bayangan) ke peta agar instan
                    this.onReportSuccess(Object.fromEntries(formData), data.event_id); 
                } else {
                    console.warn('Backend menolak data. Pastikan validasi API cocok.');
                    this.showToast('Gagal mengirim laporan. Cek koneksi.', 'error');
                }
            } catch (err) {
                console.warn('Network error:', err);
                this.showToast('Gagal terhubung ke server.', 'error');
            } finally {
                this.isSubmitting = false;
            }
        },

        onReportSuccess(payload, eventId) {
            // Tutup modal dan bersihkan form
            this.showReportModal = false;
            this.photoPreview = null;
            this.photoFile = null;
            this.form.description = '';

            // Tambahkan / perbarui event di peta secara instan
            const lat = parseFloat(payload.latitude);
            const lng = parseFloat(payload.longitude);

            const newEvent = {
                id: eventId,
                category: payload.category,
                status: 'ACTIVE',
                latitude: lat,
                longitude: lng,
                water_level: payload.water_level,
                reports_count: 1,
                is_verified: false,
                location_name: 'Laporan Baru Warga',
                updated_at_human: 'Baru saja'
            };

            this.events.unshift(newEvent);
            this.renderMarkers();
            this.map.flyTo([lat, lng], 15);
            this.selectedEvent = newEvent;
        }
    };
}
</script>
@endpush

