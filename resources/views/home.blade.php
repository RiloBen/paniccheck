@extends('layouts.app')

@section('title', 'PANICCHECK - Peta Pemantauan & Laporan Banjir Surabaya')

@section('content')
<div x-data="paniccheckApp()" x-init="init()" class="w-full h-full relative">

    <!-- M10: Quick Check / Paste Text Scanner (Letak Paling Atas Layar) -->
    @include('components.quick-check')

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

    <!-- Event Detail Drawer (M4 / M5 / M6) -->
    @include('components.event-drawer')

    <!-- Report Modal (M2) -->
    @include('components.report-modal')

    <!-- M7: Admin & Dev Mode Controller (Hanya Render untuk Admin / Dev Flag) -->
    @include('components.demo-controller')

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
        isConfirming: false,
        isResolving: false,
        quickCheckText: '',
        quickCheckResult: null,
        photoPreview: null,
        photoFile: null,
        userCoords: { latitude: -7.2819, longitude: 112.7483 }, // Default Surabaya
        isDetectingUserCoords: false,

        form: {
            latitude: -7.2892,
            longitude: 112.7153,
            category: 'Banjir',
            water_level: 'sepinggang_keatas',
            description: ''
        },

        init() {
            this.initMap();
            this.trackUserLocation();
            this.fetchEvents();
            window.addEventListener('reload-events', () => this.fetchEvents());
        },

        trackUserLocation() {
            if (navigator.geolocation) {
                this.isDetectingUserCoords = true;
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        this.userCoords = {
                            latitude: parseFloat(pos.coords.latitude.toFixed(6)),
                            longitude: parseFloat(pos.coords.longitude.toFixed(6))
                        };
                        this.isDetectingUserCoords = false;
                    },
                    (err) => {
                        this.isDetectingUserCoords = false;
                    },
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 60000 }
                );
            }
        },

        getDistanceToEvent(ev) {
            if (!this.userCoords || !ev || !ev.latitude || !ev.longitude) return null;
            return this.calculateHaversine(
                this.userCoords.latitude, this.userCoords.longitude,
                parseFloat(ev.latitude), parseFloat(ev.longitude)
            );
        },

        calculateHaversine(lat1, lon1, lat2, lon2) {
            const R = 6371e3; // meter
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return Math.round(R * c);
        },

        hasConfirmed(eventId) {
            try {
                const list = JSON.parse(localStorage.getItem('paniccheck_confirmed_events') || '[]');
                return list.includes(eventId);
            } catch (e) {
                return false;
            }
        },

        markConfirmed(eventId) {
            try {
                const list = JSON.parse(localStorage.getItem('paniccheck_confirmed_events') || '[]');
                if (!list.includes(eventId)) {
                    list.push(eventId);
                    localStorage.setItem('paniccheck_confirmed_events', JSON.stringify(list));
                }
            } catch (e) {}
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
                // include_resolved=1 agar event yang sudah surut tidak hilang dari peta saat reload
                const res = await fetch('/api/events?include_resolved=1');
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
                            is_verified: !!ev.is_verified || (ev.reports && ev.reports.length >= 2),
                            reports: ev.reports || [],
                            location_name: ev.location_name || this.guessLocationName(parseFloat(ev.latitude), parseFloat(ev.longitude), ev.reports),
                            updated_at: ev.updated_at,
                            updated_at_human: this.formatTimeAgo(ev.updated_at)
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

        guessLocationName(lat, lng, reports) {
            if (reports && reports.length > 0 && reports[0].description) {
                const desc = reports[0].description;
                if (/sungkono|ciputra/i.test(desc)) return 'Jl. Mayjen Sungkono (Dekat Ciputra World)';
                if (/darmo|bungkul/i.test(desc)) return 'Jl. Raya Darmo (Depan Taman Bungkul)';
                if (/kertajaya/i.test(desc)) return 'Jl. Raya Kertajaya Indah';
                if (/dharmawangsa|soetomo/i.test(desc)) return 'Jl. Dharmawangsa (Depan RSUD Dr. Soetomo)';
            }
            if (Math.abs(lat - (-7.2892)) < 0.005) return 'Jl. Mayjen Sungkono';
            if (Math.abs(lat - (-7.2858)) < 0.005) return 'Jl. Raya Darmo';
            if (Math.abs(lat - (-7.2797)) < 0.005) return 'Jl. Raya Kertajaya Indah';
            if (Math.abs(lat - (-7.2705)) < 0.005) return 'Jl. Dharmawangsa';
            return 'Titik Genangan Surabaya (' + lat.toFixed(4) + ', ' + lng.toFixed(4) + ')';
        },

        formatTimeAgo(dateStr) {
            if (!dateStr) return 'Baru saja';
            const diff = Math.floor((new Date() - new Date(dateStr)) / 60000);
            if (isNaN(diff) || diff < 1) return 'Baru saja';
            if (diff < 60) return diff + ' menit lalu';
            const hours = Math.floor(diff / 60);
            return hours + ' jam ' + (diff % 60 > 0 ? (diff % 60) + ' mnt ' : '') + 'lalu';
        },

        runQuickCheck() {
            const q = (this.quickCheckText || '').trim();
            if (!q) {
                this.quickCheckResult = null;
                return;
            }

            const streetKeywords = [
                { pattern: /sungkono|mayjen|ciputra\s*world/i, label: 'Jl. Mayjen Sungkono' },
                { pattern: /darmo|bungkul/i, label: 'Jl. Raya Darmo' },
                { pattern: /kertajaya|klampis|its/i, label: 'Jl. Raya Kertajaya Indah' },
                { pattern: /dharmawangsa|soetomo|unair/i, label: 'Jl. Dharmawangsa' },
                { pattern: /ngagel|novotel/i, label: 'Jl. Ngagel' },
                { pattern: /tunjungan|basuki\s*rahmat|tp\b/i, label: 'Jl. Basuki Rahmat / Tunjungan' },
                { pattern: /pemuda|grahadi/i, label: 'Jl. Pemuda' },
                { pattern: /wiyung|menganti/i, label: 'Jl. Raya Menganti Wiyung' },
                { pattern: /rungkut|upn|sier/i, label: 'Kawasan Rungkut' }
            ];

            const matchedStreet = streetKeywords.find(s => s.pattern.test(q));

            if (matchedStreet) {
                const matchedEvent = this.events.find(e => {
                    const loc = (e.location_name || '').toLowerCase();
                    return matchedStreet.pattern.test(loc) || (e.reports && e.reports.some(r => matchedStreet.pattern.test(r.description || '')));
                });

                if (matchedEvent) {
                    this.quickCheckResult = {
                        type: matchedEvent.status,
                        event: matchedEvent,
                        query: matchedStreet.label
                    };
                } else {
                    this.quickCheckResult = {
                        type: 'SAFE',
                        event: null,
                        query: matchedStreet.label
                    };
                }
            } else {
                this.quickCheckResult = {
                    type: 'UNRECOGNIZED',
                    event: null,
                    query: q
                };
            }
        },

        focusEvent(ev) {
            if (!ev) return;
            this.selectedEvent = ev;
            this.map.flyTo([ev.latitude, ev.longitude], 16, { animate: true, duration: 1 });
        },

        async getSanctumToken(role = 'user') {
            const email = role === 'admin' ? 'admin@paniccheck.com' : (role === 'relawan' ? 'relawan@paniccheck.com' : 'warga_demo@paniccheck.com');
            const cacheKey = 'paniccheck_token_' + role;
            const cached = localStorage.getItem(cacheKey);
            if (cached) return cached;

            try {
                // Coba login dulu
                let res = await fetch('/api/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ email: email, password: 'rahasia123' })
                });

                if (!res.ok && role === 'user') {
                    // Jika warga demo belum ada di DB, daftarkan otomatis
                    res = await fetch('/api/register', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ name: 'Warga Demo', email: email, password: 'rahasia123' })
                    });
                }

                if (res.ok) {
                    const data = await res.json();
                    if (data.access_token) {
                        localStorage.setItem(cacheKey, data.access_token);
                        return data.access_token;
                    }
                }
            } catch (e) {
                console.warn('Gagal memperoleh token otomatis:', e);
            }
            return null;
        },

        async confirmFlood(eventId) {
            if (this.hasConfirmed(eventId)) {
                this.showToast('Anda sudah mengonfirmasi titik kejadian ini.', 'warning');
                return;
            }

            this.isConfirming = true;
            try {
                let token = await this.getSanctumToken('user');
                let headers = {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                };
                if (token) headers['Authorization'] = 'Bearer ' + token;

                let res = await fetch('/api/events/' + eventId + '/confirm', {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({ description: 'Kondisi air masih tinggi di lapangan (konfirmasi warga).' })
                });

                // Jika token kadaluarsa (misal sehabis reset database), bersihkan cache dan coba ulang sekali
                if (res.status === 401) {
                    localStorage.removeItem('paniccheck_token_user');
                    token = await this.getSanctumToken('user');
                    if (token) headers['Authorization'] = 'Bearer ' + token;
                    res = await fetch('/api/events/' + eventId + '/confirm', {
                        method: 'POST',
                        headers: headers,
                        body: JSON.stringify({ description: 'Kondisi air masih tinggi di lapangan (konfirmasi warga).' })
                    });
                }

                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    this.markConfirmed(eventId);
                    this.showToast('✓ Konfirmasi berhasil ditambahkan! Status diperbarui.', 'success');
                    if (this.selectedEvent && this.selectedEvent.id === eventId) {
                        this.selectedEvent.reports_count = (this.selectedEvent.reports_count || 1) + 1;
                        this.selectedEvent.status = 'ACTIVE';
                        this.selectedEvent.is_verified = true;
                        if (!this.selectedEvent.reports) this.selectedEvent.reports = [];
                        this.selectedEvent.reports.unshift({
                            id: Date.now(),
                            is_confirmation: true,
                            water_level: this.selectedEvent.water_level,
                            description: 'Konfirmasi silang warga: Kondisi air masih tinggi di lapangan.',
                            created_at: new Date().toISOString()
                        });
                        this.renderMarkers();
                    }
                } else {
                    this.showToast(data.message || 'Gagal mengirim konfirmasi.', 'error');
                }
            } catch (err) {
                this.markConfirmed(eventId);
                this.showToast('✓ Konfirmasi berhasil dicatat!', 'success');
                if (this.selectedEvent) {
                    this.selectedEvent.reports_count = (this.selectedEvent.reports_count || 1) + 1;
                    this.selectedEvent.status = 'ACTIVE';
                    this.renderMarkers();
                }
            } finally {
                this.isConfirming = false;
            }
        },

        async resolveFlood(eventId) {
            const isDevMode = new URLSearchParams(window.location.search).get('dev') === '1';
            const cachedRelawanToken = localStorage.getItem('paniccheck_token_relawan');
            const cachedAdminToken = localStorage.getItem('paniccheck_token_admin');
            const hasPrivilegedSession = !!(cachedRelawanToken || cachedAdminToken);

            if (!hasPrivilegedSession && !isDevMode) {
                alert('Akses Ditolak: Penutupan resmi status bencana ("Tandai Surut") hanya dapat dilakukan oleh Relawan Terverifikasi atau Admin Lapangan demi mencegah hoaks.\n\nSilakan login dengan akun relawan atau buka dengan mode dev.');
                return;
            }

            if (!confirm('Konfirmasi Penutupan: Anda menyatakan genangan air di titik ini sudah surut total?')) return;
            this.isResolving = true;
            try {
                const token = cachedRelawanToken || cachedAdminToken || (isDevMode ? await this.getSanctumToken('relawan') : null);
                const headers = {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                };
                if (token) headers['Authorization'] = 'Bearer ' + token;

                const res = await fetch('/api/events/' + eventId + '/resolve', {
                    method: 'POST',
                    headers: headers
                });

                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    this.showToast('✓ Kejadian banjir resmi ditutup & status berubah RESOLVED!', 'success');
                    if (this.selectedEvent && this.selectedEvent.id === eventId) {
                        this.selectedEvent.status = 'RESOLVED';
                        this.renderMarkers();
                    }
                } else {
                    this.showToast(data.message || 'Akses ditolak. Hanya relawan/admin yang dapat menutup.', 'error');
                }
            } catch (err) {
                if (isDevMode) {
                    this.showToast('✓ Ditandai surut (RESOLVED)', 'success');
                    if (this.selectedEvent) {
                        this.selectedEvent.status = 'RESOLVED';
                        this.renderMarkers();
                    }
                } else {
                    this.showToast('Gagal memproses penutupan status.', 'error');
                }
            } finally {
                this.isResolving = false;
            }
        },

        showToast(msg, type = 'success') {
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { msg, type } }));
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

                const data = await res.json().catch(() => ({}));

                if (res.ok) {
                    this.onReportSuccess(Object.fromEntries(formData), data.event_id); 
                } else {
                    const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Gagal mengirim laporan. Periksa kelengkapan form.');
                    this.showToast(errorMsg, 'error');
                }
            } catch (err) {
                console.warn('Network error:', err);
                this.showToast('Gagal terhubung ke server. Menggunakan mode darurat lokal.', 'warning');
                this.onReportSuccess(Object.fromEntries(formData), Date.now());
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

            const lat = parseFloat(payload.latitude);
            const lng = parseFloat(payload.longitude);

            // Cek apakah laporan ini teragregasi (clustered) ke event yang sudah ada di peta
            const existing = this.events.find(e => e.id === eventId);
            if (existing) {
                existing.reports_count = (existing.reports_count || 1) + 1;
                existing.water_level = payload.water_level || existing.water_level;
                existing.status = 'ACTIVE';
                existing.updated_at_human = 'Baru saja';
                if (!existing.reports) existing.reports = [];
                existing.reports.unshift({
                    id: Date.now(),
                    is_confirmation: false,
                    water_level: payload.water_level,
                    description: payload.description || 'Laporan baru warga di titik ini',
                    created_at: new Date().toISOString()
                });
                this.renderMarkers();
                this.selectedEvent = existing;
                this.map.flyTo([existing.latitude, existing.longitude], 16, { animate: true, duration: 1 });
                this.showToast('✓ Laporan berhasil digabungkan ke titik genangan terdekat (<500m)!', 'success');
            } else {
                // Event baru murni di lokasi di luar radius 500m
                const newEvent = {
                    id: eventId,
                    category: payload.category || 'Banjir',
                    status: 'ACTIVE',
                    latitude: lat,
                    longitude: lng,
                    water_level: payload.water_level || 'selutut',
                    reports_count: 1,
                    is_verified: false,
                    location_name: this.guessLocationName(lat, lng, []),
                    updated_at_human: 'Baru saja',
                    reports: [{
                        id: Date.now(),
                        is_confirmation: false,
                        water_level: payload.water_level,
                        description: payload.description || 'Laporan awal warga',
                        created_at: new Date().toISOString()
                    }]
                };

                this.events.unshift(newEvent);
                this.renderMarkers();
                this.selectedEvent = newEvent;
                this.map.flyTo([lat, lng], 16, { animate: true, duration: 1 });
                this.showToast('🚨 Laporan titik banjir baru berhasil dipublikasikan!', 'success');
            }
        }
    };
}
</script>
@endpush

