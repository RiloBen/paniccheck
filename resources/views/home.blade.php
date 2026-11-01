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
        isScanningQuickCheck: false,
        _quickCheckTimer: null,
        photoPreview: null,
        photoFile: null,
        reportError: null,
        userCoords: { latitude: -7.2819, longitude: 112.7483 }, // Default Surabaya
        isDetectingUserCoords: false,
        userRole: localStorage.getItem('user_role') || 'guest',
        isLoggedIn: !!localStorage.getItem('access_token'),

        form: {
            latitude: -7.2892,
            longitude: 112.7153,
            category: 'Banjir',
            water_level: 'sepinggang_keatas',
            description: ''
        },

        syncAuth() {
            this.userRole = localStorage.getItem('user_role') || 'guest';
            this.isLoggedIn = !!localStorage.getItem('access_token');
        },

        init() {
            this.syncAuth();
            window.addEventListener('auth-changed', (e) => {
                this.syncAuth();
                this.renderMarkers();
            });
            window.addEventListener('resolve-event', (e) => {
                this.resolveFlood(e.detail);
            });
            this.initMap();
            this.trackUserLocation();
            this.fetchEvents();
            window.addEventListener('reload-events', () => this.fetchEvents());
            window.addEventListener('teleport-gps-selected', () => {
                if (this.selectedEvent) {
                    this.userCoords = {
                        latitude: parseFloat((parseFloat(this.selectedEvent.latitude) + 0.0001).toFixed(6)),
                        longitude: parseFloat((parseFloat(this.selectedEvent.longitude) + 0.0001).toFixed(6))
                    };
                    this.showToast('📍 GPS disimulasikan berada di lokasi kejadian (~15m)!', 'success');
                } else {
                    this.showToast('Pilih salah satu titik banjir di peta terlebih dahulu.', 'warning');
                }
            });
        },

        isDevMode() {
            return new URLSearchParams(window.location.search).get('dev') === '1';
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

        getSeverityScore(level) {
            if (level === 'sepinggang_keatas') return 3;
            if (level === 'selutut') return 2;
            if (level === 'semata_kaki') return 1;
            return 0;
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
                this.isScanningQuickCheck = false;
                clearTimeout(this._quickCheckTimer);
                return;
            }

            // 1. Evaluasi instan client-side (0ms latency untuk responsivitas seketika)
            this.evaluateQuickCheck(q);

            // 2. Evaluasi mendalam backend /api/quick-check (NLP & pencocokan database terpadu)
            clearTimeout(this._quickCheckTimer);
            this.isScanningQuickCheck = true;
            this._quickCheckTimer = setTimeout(() => {
                this.fetchQuickCheckBackend(q);
            }, 250);
        },

        evaluateQuickCheck(q) {
            const text = q.toLowerCase();

            // Deteksi Ketinggian Air dari kata kunci
            let waterLevel = null;
            if (/(sepinggang|pinggang|sedada|dada|leher|1\s*meter|1\s*m\b|1\.5\s*m|dalam\s*banget|tenggelam|parah)/i.test(q)) {
                waterLevel = 'sepinggang_keatas';
            } else if (/(selutut|lutut|sepaha|paha|setengah\s*meter|50\s*cm|60\s*cm|sedang)/i.test(q)) {
                waterLevel = 'selutut';
            } else if (/(mata\s*kaki|semata\s*kaki|mata_kaki|tumit|10\s*cm|20\s*cm|30\s*cm|dangkal|genangan\s*ringan)/i.test(q)) {
                waterLevel = 'semata_kaki';
            }

            const hasFlood = /(banjir|genangan|tergenang|air\s*naik|luapan|meluap|tenggelam|mogok|waspada|macet\s*total|arus|bencana|darurat|sepinggang|selutut|mata\s*kaki)/i.test(q);
            const hasSafe = /(lancar|aman|surut|kering|tidak\s*(ada\s*)?banjir|bebas\s*banjir|nihil\s*genangan|sudah\s*lewat|terkendali)/i.test(q);

            if (hasFlood && !waterLevel) {
                waterLevel = 'selutut';
            }

            // Kamus Spasial Surabaya Komprehensif (30+ titik strategis)
            const surabayaStreets = [
                { pattern: /sungkono|mayjen|ciputra\s*world/i, name: 'Jl. Mayjen Sungkono (Dekat Ciputra World)', short: 'Jl. Mayjen Sungkono', lat: -7.2892, lng: 112.7153 },
                { pattern: /darmo|bungkul|al-falah/i, name: 'Jl. Raya Darmo (Depan Taman Bungkul)', short: 'Jl. Raya Darmo', lat: -7.2858, lng: 112.7383 },
                { pattern: /kertajaya|klampis|its\b/i, name: 'Jl. Raya Kertajaya Indah', short: 'Jl. Raya Kertajaya Indah', lat: -7.2797, lng: 112.7758 },
                { pattern: /dharmawangsa|soetomo|unair/i, name: 'Jl. Dharmawangsa (Depan RSUD Dr. Soetomo)', short: 'Jl. Dharmawangsa', lat: -7.2705, lng: 112.7562 },
                { pattern: /ahmad\s*yani|a\.?\s*yani|bundaran\s*waru|royal\s*plaza/i, name: 'Jl. Frontage Ahmad Yani (Dekat Royal Plaza)', short: 'Jl. Frontage Ahmad Yani', lat: -7.3150, lng: 112.7340 },
                { pattern: /ngagel|novotel|kali\s*ngagel/i, name: 'Jl. Ngagel', short: 'Jl. Ngagel', lat: -7.2915, lng: 112.7482 },
                { pattern: /tunjungan|basuki\s*rahmat|tp\b/i, name: 'Jl. Basuki Rahmat / Tunjungan', short: 'Jl. Basuki Rahmat', lat: -7.2625, lng: 112.7390 },
                { pattern: /pemuda|grahadi|delta\s*plaza/i, name: 'Jl. Pemuda', short: 'Jl. Pemuda', lat: -7.2650, lng: 112.7480 },
                { pattern: /wiyung|menganti|babatan/i, name: 'Jl. Raya Menganti Wiyung', short: 'Jl. Raya Menganti Wiyung', lat: -7.3102, lng: 112.6950 },
                { pattern: /rungkut|upn\b|sier/i, name: 'Kawasan Rungkut', short: 'Kawasan Rungkut', lat: -7.3275, lng: 112.7660 },
                { pattern: /kenjeran|suramadu/i, name: 'Jl. Kenjeran', short: 'Jl. Kenjeran', lat: -7.2430, lng: 112.7780 },
                { pattern: /wonokromo|joyoboyo|dtc/i, name: 'Kawasan Wonokromo', short: 'Kawasan Wonokromo', lat: -7.3005, lng: 112.7370 },
                { pattern: /diponegoro|rkz/i, name: 'Jl. Diponegoro', short: 'Jl. Diponegoro', lat: -7.2870, lng: 112.7310 },
                { pattern: /hr\s*muhammad/i, name: 'Jl. HR Muhammad', short: 'Jl. HR Muhammad', lat: -7.2850, lng: 112.6930 },
                { pattern: /merr\b|ir\s*soekarno/i, name: 'Jl. Dr. Ir. H. Soekarno (MERR)', short: 'Jl. MERR Surabaya', lat: -7.3050, lng: 112.7820 },
                { pattern: /jemursari/i, name: 'Jl. Raya Jemursari', short: 'Jl. Raya Jemursari', lat: -7.3180, lng: 112.7450 },
                { pattern: /manyar|bratang/i, name: 'Jl. Manyar Kertoarjo', short: 'Jl. Manyar Kertoarjo', lat: -7.2820, lng: 112.7630 },
                { pattern: /gubeng/i, name: 'Jl. Raya Gubeng', short: 'Jl. Raya Gubeng', lat: -7.2720, lng: 112.7500 },
                { pattern: /kupang\s*jaya|banyu\s*urip/i, name: 'Jl. Kupang Jaya', short: 'Jl. Kupang Jaya', lat: -7.2750, lng: 112.7100 },
                { pattern: /tandes|margomulyo/i, name: 'Kawasan Tandes', short: 'Kawasan Tandes', lat: -7.2550, lng: 112.6780 },
                { pattern: /benowo|gbt\b/i, name: 'Kawasan Benowo', short: 'Kawasan Benowo', lat: -7.2350, lng: 112.6250 },
                { pattern: /ketintang|unesa/i, name: 'Kawasan Ketintang', short: 'Kawasan Ketintang', lat: -7.3110, lng: 112.7230 }
            ];

            let matchedLoc = surabayaStreets.find(s => s.pattern.test(q));

            // Dynamic NLP Fallback: Tangkap nama jalan apa pun dari teks bebas (cth: "Banjir di daerah Kupang Baru")
            if (!matchedLoc) {
                const dynamicMatch = q.match(/(?:\b(?:jl\.?|jalan|daerah|kawasan|wilayah|depan|sekitar|arah|sepanjang|gang|komplek)\b)\s+([A-Za-z0-9\s\-]{3,30})/i);
                if (dynamicMatch) {
                    const cleanName = dynamicMatch[1].replace(/\s+(banjir|air|macet|dan|di|ke|ada).*$/i, '').trim();
                    if (cleanName.length >= 3) {
                        matchedLoc = {
                            name: 'Jl. ' + cleanName + ' (Surabaya)',
                            short: 'Jl. ' + cleanName,
                            lat: -7.2575,
                            lng: 112.7521
                        };
                    }
                }
            }

            // Cari kecocokan di events lokal yang aktif
            let matchedEvent = null;
            if (matchedLoc) {
                const queryStr = matchedLoc.short.toLowerCase();
                matchedEvent = this.events.find(e => {
                    const loc = (e.location_name || '').toLowerCase();
                    const desc = (e.reports && e.reports.length > 0) ? e.reports.map(r => r.description || '').join(' ').toLowerCase() : '';
                    const dist = this.calculateHaversine(matchedLoc.lat, matchedLoc.lng, parseFloat(e.latitude), parseFloat(e.longitude));
                    return loc.includes(queryStr) || desc.includes(queryStr.replace('jl. ', '')) || dist <= 800;
                });
            } else {
                matchedEvent = this.events.find(e => {
                    const loc = (e.location_name || '').toLowerCase();
                    return loc && text.includes(loc.split(' ')[1] || '---');
                });
            }

            if (matchedEvent) {
                this.quickCheckResult = {
                    type: matchedEvent.status, // ACTIVE | STALE | RESOLVED
                    event: matchedEvent,
                    location_hint: matchedEvent.location_name || (matchedLoc ? matchedLoc.name : 'Titik Kejadian Terdaftar'),
                    water_level: matchedEvent.water_level || waterLevel || 'selutut',
                    query: matchedLoc ? matchedLoc.short : q
                };
            } else if (hasFlood) {
                // Teks bebas mengindikasikan banjir di lokasi baru
                this.quickCheckResult = {
                    type: 'NEW_DETECTION',
                    event: null,
                    location_hint: matchedLoc ? matchedLoc.name : 'Titik Baru Terdeteksi dari Pesan Warga',
                    water_level: waterLevel || 'selutut',
                    latitude: matchedLoc ? matchedLoc.lat : -7.2892,
                    longitude: matchedLoc ? matchedLoc.lng : 112.7153,
                    query: matchedLoc ? matchedLoc.short : q
                };
            } else if (matchedLoc && (hasSafe || !hasFlood)) {
                this.quickCheckResult = {
                    type: 'SAFE',
                    event: null,
                    location_hint: matchedLoc.name,
                    query: matchedLoc.short
                };
            } else {
                this.quickCheckResult = {
                    type: 'UNRECOGNIZED',
                    event: null,
                    query: q
                };
            }
        },

        async fetchQuickCheckBackend(q) {
            try {
                const res = await fetch('/api/quick-check', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ text: q })
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data && data.status === 'success') {
                        const mType = data.match_type;
                        if (mType === 'ACTIVE' || mType === 'STALE' || mType === 'RESOLVED') {
                            this.quickCheckResult = {
                                type: mType,
                                event: data.event,
                                location_hint: data.extracted_data.location_hint,
                                water_level: data.extracted_data.water_level,
                                query: data.extracted_data.location_hint
                            };
                        } else if (mType === 'NEW_DETECTION') {
                            this.quickCheckResult = {
                                type: 'NEW_DETECTION',
                                event: null,
                                location_hint: data.extracted_data.location_hint,
                                water_level: data.extracted_data.water_level,
                                latitude: data.extracted_data.latitude,
                                longitude: data.extracted_data.longitude,
                                query: data.extracted_data.location_hint
                            };
                        } else if (mType === 'SAFE') {
                            this.quickCheckResult = {
                                type: 'SAFE',
                                event: null,
                                location_hint: data.extracted_data.location_hint,
                                query: data.extracted_data.location_hint
                            };
                        } else if (mType === 'UNRECOGNIZED') {
                            if (!this.quickCheckResult || this.quickCheckResult.type === 'UNRECOGNIZED') {
                                this.quickCheckResult = {
                                    type: 'UNRECOGNIZED',
                                    event: null,
                                    query: q
                                };
                            }
                        }
                    }
                }
            } catch (err) {
                console.warn('QuickCheck API fallback to local parser:', err);
            } finally {
                this.isScanningQuickCheck = false;
            }
        },

        plotNewDetectionFromQuickCheck(result) {
            if (!result) return;
            const lat = parseFloat(result.latitude || -7.2892);
            const lng = parseFloat(result.longitude || 112.7153);

            this.selectedEvent = null;
            this.reportError = null;
            this.form.latitude = lat;
            this.form.longitude = lng;
            this.locationAccuracy = 15; // Set akurasi tetap agar tidak otomatis tertimpa GPS browser
            this.form.water_level = result.water_level || 'selutut';
            this.form.description = this.quickCheckText || ('Indikasi banjir di ' + (result.location_hint || 'Surabaya'));

            this.showReportModal = true;

            if (this.map) {
                this.map.flyTo([lat, lng], 16, { animate: true, duration: 1.2 });
            }

            this.showToast('📍 Data lokasi & kondisi air otomatis dimasukkan ke form laporan!', 'info');
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

            const isDev = this.isDevMode();

            // Cek jarak spasial <= 500 meter (PRD M6 / Bab 8.2)
            if (this.selectedEvent && !isDev) {
                const dist = this.getDistanceToEvent(this.selectedEvent);
                if (dist !== null && dist > 500) {
                    const distStr = dist >= 1000 ? (dist / 1000).toFixed(1) + ' km' : dist + ' m';
                    this.showToast(`Anda terdeteksi berjarak ${distStr} dari titik kejadian. Konfirmasi hanya diizinkan dalam radius 500 meter.`, 'warning');
                    return;
                }
            }

            this.isConfirming = true;
            try {
                let token = localStorage.getItem('access_token');
                if (!token && isDev) {
                    token = await this.getSanctumToken('user');
                }
                if (!token && !isDev) {
                    this.showToast('Silakan masuk terlebih dahulu untuk mengonfirmasi status banjir.', 'warning');
                    window.dispatchEvent(new CustomEvent('open-login-modal'));
                    this.isConfirming = false;
                    return;
                }

                let headers = {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                };
                if (token) headers['Authorization'] = 'Bearer ' + token;
                if (isDev) headers['X-Paniccheck-Dev'] = '1';

                // Koordinat pengirim
                let lat = this.userCoords ? this.userCoords.latitude : (this.selectedEvent ? this.selectedEvent.latitude : null);
                let lon = this.userCoords ? this.userCoords.longitude : (this.selectedEvent ? this.selectedEvent.longitude : null);

                let payload = {
                    latitude: lat,
                    longitude: lon,
                    description: 'Kondisi air masih tinggi di lapangan (konfirmasi warga).'
                };
                if (isDev) payload.dev_bypass = true;

                let res = await fetch('/api/events/' + eventId + '/confirm' + (isDev ? '?dev=1' : ''), {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify(payload)
                });

                if (res.status === 401 || res.status === 403) {
                    const errData = await res.json().catch(() => ({}));
                    window.dispatchEvent(new CustomEvent('auth-error', { detail: { status: res.status, message: errData.message } }));
                    return;
                }

                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    this.markConfirmed(eventId);
                    this.showToast('✓ Konfirmasi berhasil ditambahkan! Status diperbarui.', 'success');
                    const target = this.events.find(e => e.id == eventId);
                    if (target) {
                        target.reports_count = (target.reports_count || 1) + 1;
                        target.status = 'ACTIVE';
                        target.is_verified = true;
                        if (!target.reports) target.reports = [];
                        target.reports.unshift({
                            id: Date.now(),
                            is_confirmation: true,
                            water_level: target.water_level,
                            description: 'Konfirmasi silang warga: Kondisi air masih tinggi di lapangan.',
                            created_at: new Date().toISOString()
                        });
                    }
                    if (this.selectedEvent && this.selectedEvent.id == eventId) {
                        if (this.selectedEvent !== target) {
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
                        }
                    }
                    this.renderMarkers();
                } else {
                    if (res.status === 422) {
                        if (data.message && data.message.includes('sudah memberikan')) {
                            this.markConfirmed(eventId);
                        }
                        this.showToast(data.message || 'Konfirmasi tidak memenuhi syarat.', 'warning');
                    } else {
                        this.showToast(data.message || 'Gagal mengirim konfirmasi.', 'error');
                    }
                }
            } catch (err) {
                console.warn('Network error during confirmation:', err);
                this.showToast('Gagal terhubung ke server untuk mengirim konfirmasi.', 'error');
            } finally {
                this.isConfirming = false;
            }
        },

        async resolveFlood(eventId) {
            const isDev = this.isDevMode();
            const token = localStorage.getItem('access_token') || localStorage.getItem('paniccheck_token_relawan') || localStorage.getItem('paniccheck_token_admin');
            const isRelawanOrAdmin = (this.userRole === 'relawan' || this.userRole === 'admin');

            if (!isRelawanOrAdmin && !isDev) {
                this.showToast('Akses Ditolak: Fitur ini khusus untuk Relawan / Admin.', 'error');
                return;
            }

            // Cek jarak spasial <= 500m untuk verifikasi lapangan relawan (PRD Bab 5 & 8.4)
            if (this.selectedEvent && !isDev) {
                const dist = this.getDistanceToEvent(this.selectedEvent);
                if (dist !== null && dist > 500) {
                    const distStr = dist >= 1000 ? (dist / 1000).toFixed(1) + ' km' : dist + ' m';
                    this.showToast(`Verifikasi lapangan relawan wajib berada dalam radius 500m (Jarak Anda: ${distStr}).`, 'warning');
                    return;
                }
            }

            if (!confirm('Konfirmasi Penutupan: Anda menyatakan genangan air di titik ini sudah surut total?')) return;
            this.isResolving = true;
            try {
                const headers = {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                };
                if (token) headers['Authorization'] = 'Bearer ' + token;
                if (isDev) headers['X-Paniccheck-Dev'] = '1';

                let lat = this.userCoords ? this.userCoords.latitude : (this.selectedEvent ? this.selectedEvent.latitude : null);
                let lon = this.userCoords ? this.userCoords.longitude : (this.selectedEvent ? this.selectedEvent.longitude : null);

                let payload = {
                    latitude: lat,
                    longitude: lon
                };
                if (isDev) payload.dev_bypass = true;

                const res = await fetch('/api/events/' + eventId + '/resolve' + (isDev ? '?dev=1' : ''), {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify(payload)
                });

                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    this.showToast('✓ Kejadian banjir resmi ditutup & status berubah RESOLVED!', 'success');
                    const target = this.events.find(e => e.id == eventId);
                    if (target) {
                        target.status = 'RESOLVED';
                    }
                    if (this.selectedEvent && this.selectedEvent.id == eventId) {
                        this.selectedEvent.status = 'RESOLVED';
                    }
                    this.renderMarkers();
                } else {
                    if (res.status === 401 || res.status === 403) {
                        window.dispatchEvent(new CustomEvent('auth-error', { detail: { status: res.status, message: data.message } }));
                    } else {
                        this.showToast(data.message || 'Akses ditolak atau posisi di luar radius 500m.', 'error');
                    }
                }
            } catch (err) {
                if (isDev) {
                    this.showToast('✓ Ditandai surut (RESOLVED)', 'success');
                    const target = this.events.find(e => e.id == eventId);
                    if (target) target.status = 'RESOLVED';
                    if (this.selectedEvent && this.selectedEvent.id == eventId) {
                        this.selectedEvent.status = 'RESOLVED';
                    }
                    this.renderMarkers();
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

            const isRelawanOrAdmin = (this.userRole === 'relawan' || this.userRole === 'admin');

            filtered.forEach(ev => {
                const markerIcon = this.createMarkerIcon(ev);
                const marker = L.marker([ev.latitude, ev.longitude], { icon: markerIcon });

                marker.on('click', () => {
                    this.selectedEvent = ev;
                    this.map.panTo([ev.latitude, ev.longitude]);
                });

                // Tooltip / Popup Leaflet sesuai PRD Frontend (Sistem Autentikasi SPA & Hak Akses)
                let popupHtml = `
                    <div class="p-1 text-slate-900 font-sans text-xs min-w-[180px]">
                        <div class="font-bold text-sm mb-1 text-slate-900">${ev.location_name || 'Titik Genangan'}</div>
                        <div class="text-[11px] text-slate-600">Status: <span class="font-bold text-slate-800">${ev.status === 'ACTIVE' ? 'Aktif' : (ev.status === 'STALE' ? 'Perlu Konfirmasi' : 'Surut')}</span></div>
                        <div class="text-[11px] text-slate-600">Ketinggian: <span class="font-bold text-slate-800">${ev.water_level || 'Normal'}</span></div>
                `;

                // Tombol "Konfirmasi Surut / Update Status" HANYA tampil untuk Relawan / Admin (disembunyikan total dari Pengunjung Anonim & Warga Biasa)
                if (isRelawanOrAdmin && ev.status !== 'RESOLVED') {
                    popupHtml += `
                        <button onclick="window.dispatchEvent(new CustomEvent('resolve-event', { detail: ${ev.id} }))" 
                                class="mt-2.5 w-full bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-bold py-1.5 px-2 rounded-lg text-xs flex items-center justify-center space-x-1 shadow transition cursor-pointer">
                            <span>🛡️</span>
                            <span>Konfirmasi Surut / Update Status</span>
                        </button>
                    `;
                }

                popupHtml += `</div>`;
                marker.bindPopup(popupHtml);

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
            this.reportError = null;
            this.showReportModal = true;
            if (!this.locationAccuracy) {
                this.detectGPS();
            }
        },

        openReportWithCoordinates(lat, lng) {
            this.selectedEvent = null;
            this.reportError = null;
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
            if (!file) return;
            // shortcut: hard reject >2MB client-side to match server cap; compress when upgrade needed
            if (file.size > 2 * 1024 * 1024) {
                this.showToast('Foto terlalu besar (maks 2MB). Gunakan foto beresolusi lebih rendah.', 'error');
                e.target.value = '';
                return;
            }
            this.photoFile = file;
            if (this.photoPreview) URL.revokeObjectURL(this.photoPreview);
            this.photoPreview = URL.createObjectURL(file);
        },

        async submitReport() {
            if (!this.form.latitude || !this.form.longitude) {
                this.showToast('Koordinat lokasi wajib terisi!', 'error');
                return;
            }

            this.isSubmitting = true;
            this.reportError = null;

            const formData = new FormData();
            formData.append('latitude', this.form.latitude);
            formData.append('longitude', this.form.longitude);
            formData.append('category', this.form.category);
            formData.append('water_level', this.form.water_level);
            formData.append('description', this.form.description);
            if (this.photoFile) {
                formData.append('photo', this.photoFile);
            }

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const token = localStorage.getItem('access_token');
                const headers = {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                };
                if (token) headers['Authorization'] = 'Bearer ' + token;

                const res = await fetch('/api/reports', {
                    method: 'POST',
                    headers: headers,
                    body: formData
                });

                // Parse response — guard against PHP warnings polluting JSON (item 2)
                const text = await res.text();
                let data = {};
                try { data = JSON.parse(text); } catch (_) {}

                if (res.ok) {
                    this.onReportSuccess(Object.fromEntries(formData), data.event_id, data.water_level);
                } else {
                    const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Gagal mengirim laporan. Periksa kelengkapan form.');
                    this.reportError = msg; // item 3: shown inside modal, not below it
                    this.showToast(msg, 'warning');
                }
            } catch (err) {
                console.warn('Network error:', err);
                this.reportError = 'Gagal terhubung ke server. Menggunakan mode darurat lokal.';
                this.showToast(this.reportError, 'warning');
                this.onReportSuccess(Object.fromEntries(formData), Date.now());
            } finally {
                this.isSubmitting = false;
            }
        },

        onReportSuccess(payload, eventId, resolvedWaterLevel) {
            // Tutup modal dan bersihkan form
            this.showReportModal = false;
            if (this.photoPreview) URL.revokeObjectURL(this.photoPreview);
            this.photoPreview = null;
            this.photoFile = null;
            this.reportError = null;
            this.form.description = '';

            // Kunci tombol konfirmasi agar pembuat laporan awal tidak self-confirm
            this.markConfirmed(eventId);

            const lat = parseFloat(payload.latitude);
            const lng = parseFloat(payload.longitude);

            // Cek apakah laporan ini teragregasi (clustered) ke event yang sudah ada di peta
            const existing = this.events.find(e => e.id === eventId);
            if (existing) {
                existing.reports_count = (existing.reports_count || 1) + 1;

                // Terapkan Safety-First Worst-Case Bias (tidak menurunkan level bahaya secara gegabah)
                if (resolvedWaterLevel) {
                    existing.water_level = resolvedWaterLevel;
                } else {
                    const currentScore = this.getSeverityScore(existing.water_level);
                    const newScore = this.getSeverityScore(payload.water_level);
                    if (newScore > currentScore) {
                        existing.water_level = payload.water_level;
                    }
                }

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
                    water_level: resolvedWaterLevel || payload.water_level || 'selutut',
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

