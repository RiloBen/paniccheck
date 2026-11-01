<!-- Event Detail Drawer / Quick Preview Card (M5 & M6) -->
<div x-cloak x-show="selectedEvent !== null"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-full"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-full"
     class="fixed bottom-0 inset-x-0 z-40 max-w-lg mx-auto p-4 pointer-events-none">

    <template x-if="selectedEvent">
        <div class="bg-slate-900/95 border border-slate-700/80 rounded-2xl shadow-2xl backdrop-blur-md p-5 text-slate-100 pointer-events-auto space-y-4 max-h-[85vh] overflow-y-auto">
            <!-- Header bar with handle & close button -->
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
                          :class="{
                              'bg-red-500/20 text-red-400 border border-red-500/30': selectedEvent.status === 'ACTIVE',
                              'bg-amber-500/20 text-amber-400 border border-amber-500/30': selectedEvent.status === 'STALE',
                              'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30': selectedEvent.status === 'RESOLVED'
                          }"
                          x-text="selectedEvent.status === 'ACTIVE' ? 'Aktif' : (selectedEvent.status === 'STALE' ? 'Perlu Konfirmasi' : 'Sudah Surut')">
                    </span>

                    <span class="text-xs text-slate-400" x-text="selectedEvent.reports_count + ' Laporan Digabung'"></span>
                </div>

                <button @click="selectedEvent = null" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Event Title & Location -->
            <div>
                <h3 class="text-lg font-bold text-white tracking-tight" x-text="selectedEvent.location_name || 'Titik Genangan Banjir'"></h3>
                <p class="text-xs text-slate-400 mt-0.5 flex items-center space-x-1">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                    <span x-text="selectedEvent.latitude.toFixed(5) + ', ' + selectedEvent.longitude.toFixed(5)"></span>
                    <span>•</span>
                    <span x-text="'Diperbarui ' + (selectedEvent.updated_at_human || 'Baru saja')"></span>
                </p>
            </div>

            <!-- Water Level Indicator -->
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="bg-slate-800/80 border border-slate-700/60 p-2.5 rounded-xl">
                    <span class="text-slate-400 block text-[11px] mb-0.5">Ketinggian Air</span>
                    <span class="font-bold text-sm"
                          :class="{
                              'text-red-400': selectedEvent.water_level === 'sepinggang_keatas',
                              'text-amber-400': selectedEvent.water_level === 'selutut',
                              'text-yellow-300': selectedEvent.water_level === 'semata_kaki'
                          }"
                          x-text="selectedEvent.water_level === 'sepinggang_keatas' ? 'Sepinggang ke Atas (>60cm)' : (selectedEvent.water_level === 'selutut' ? 'Selutut (30-60cm)' : 'Semata Kaki (10-30cm)')">
                    </span>
                </div>

                <div class="bg-slate-800/80 border border-slate-700/60 p-2.5 rounded-xl">
                    <span class="text-slate-400 block text-[11px] mb-0.5">Validasi Warga</span>
                    <span class="font-semibold text-slate-200"
                          x-text="selectedEvent.is_verified ? '✓ Terverifikasi Lapangan' : 'Menunggu Konfirmasi Silang'">
                    </span>
                </div>
            </div>

            <!-- Stale Alert (PRD M7) -->
            <template x-if="selectedEvent.status === 'STALE'">
                <div class="bg-amber-950/40 border border-amber-600/50 p-3 rounded-xl text-xs text-amber-200 flex items-start space-x-2">
                    <span class="text-amber-400 text-base leading-none">⚠️</span>
                    <div>
                        <span class="font-bold block">Status Perlu Konfirmasi (STALE)</span>
                        Belum ada laporan baru dalam 2 jam terakhir. Kondisi mungkin sudah berubah atau surut.
                    </div>
                </div>
            </template>

            <!-- Resolved Alert & Leptospirosis Warning (PRD S8) - Berbasis Jarak Pengguna -->
            <template x-if="selectedEvent.status === 'RESOLVED'">
                <div class="space-y-2">
                    <!-- Jika Pengguna Terdeteksi Dekat (<= 500 meter) dari Titik Banjir -->
                    <template x-if="getDistanceToEvent(selectedEvent) !== null && getDistanceToEvent(selectedEvent) <= 500">
                        <div class="bg-red-950/80 border-2 border-red-500/80 p-3.5 rounded-xl text-xs text-red-100 space-y-1.5 shadow-xl shadow-red-950/50">
                            <div class="font-extrabold flex items-center space-x-1.5 text-red-400 text-xs tracking-wide">
                                <span class="animate-ping inline-block h-2 w-2 rounded-full bg-red-400"></span>
                                <span>⚠️ PERINGATAN LEPTOSPIROSIS (JARAK <span x-text="getDistanceToEvent(selectedEvent) + 'M'"></span>)</span>
                            </div>
                            <p class="text-[11px] text-red-200 leading-relaxed font-medium">
                                Anda terdeteksi berada di zona genangan yang baru surut! Waspada Leptospirosis melalui luka terbuka & air kotor. Selalu gunakan alas kaki tertutup dan segera cuci kulit dengan sabun antiseptik (masa inkubasi 1–14 hari).
                            </p>
                        </div>
                    </template>

                    <!-- Jika Pengguna Berada Jauh (> 500 meter) atau Lokasi Belum Terdeteksi -->
                    <template x-if="getDistanceToEvent(selectedEvent) === null || getDistanceToEvent(selectedEvent) > 500">
                        <div class="bg-emerald-950/40 border border-emerald-600/50 p-3 rounded-xl text-xs text-emerald-200 space-y-1">
                            <div class="font-bold flex items-center space-x-1.5 text-emerald-400">
                                <span>✓</span>
                                <span>Genangan Resmi Dinyatakan Surut</span>
                            </div>
                            <p class="text-[11px] text-slate-300">
                                Jalur ini telah surut total dan aman dilalui kendaraan.
                                <span class="text-slate-400 block mt-0.5 text-[10px]" 
                                      x-show="getDistanceToEvent(selectedEvent) !== null" 
                                      x-text="'Jarak dari posisi Anda: ' + (getDistanceToEvent(selectedEvent) >= 1000 ? (getDistanceToEvent(selectedEvent)/1000).toFixed(1) + ' km' : getDistanceToEvent(selectedEvent) + ' m')"></span>
                            </p>
                        </div>
                    </template>
                </div>
            </template>

            <!-- M5: Linimasa Vertikal Laporan Warga (Append-only Timeline) -->
            <div class="space-y-2 pt-1 border-t border-slate-800">
                <div class="flex items-center justify-between text-xs text-slate-400">
                    <span class="font-bold text-white flex items-center space-x-1">
                        <span>📜 Linimasa Laporan</span>
                        <span class="text-slate-500 font-normal" x-text="'(' + (selectedEvent.reports ? selectedEvent.reports.length : 1) + ' data)'"></span>
                    </span>
                    <span class="text-[10px]">Kronologis Warga</span>
                </div>

                <div class="space-y-2 max-h-36 overflow-y-auto pr-1">
                    <template x-if="selectedEvent.reports && selectedEvent.reports.length > 0">
                        <div>
                            <template x-for="(rep, idx) in selectedEvent.reports" :key="rep.id || idx">
                                <div class="bg-slate-800/60 border border-slate-700/50 rounded-xl p-2.5 text-xs space-y-1 relative mb-2">
                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="font-semibold text-slate-200 flex items-center space-x-1">
                                            <span x-text="rep.is_confirmation ? '🤝 Konfirmasi Warga' : '🚨 Laporan Awal'"></span>
                                        </span>
                                        <span class="text-slate-400 text-[10px]" x-text="formatTimeAgo(rep.created_at)"></span>
                                    </div>

                                    <p class="text-slate-300 text-[11px]" x-text="rep.description || 'Tidak ada deskripsi rinci.'"></p>

                                    <!-- Foto Bukti Lapangan Jika Ada -->
                                    <template x-if="rep.photo_path">
                                        <div class="mt-1">
                                            <img :src="'/storage/' + rep.photo_path" 
                                                 alt="Bukti foto banjir" 
                                                 class="h-16 w-auto rounded-lg border border-slate-700 object-cover cursor-pointer hover:opacity-90 transition">
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="!selectedEvent.reports || selectedEvent.reports.length === 0">
                        <div class="bg-slate-800/40 rounded-xl p-2 text-center text-slate-400 text-xs">
                            Laporan utama tervalidasi via sistem agregasi.
                        </div>
                    </template>
                </div>
            </div>

            <!-- M6: Action Buttons (Konfirmasi Warga & Penutupan Relawan) -->
            <div class="pt-2 border-t border-slate-800 space-y-2">
                <template x-if="selectedEvent.status !== 'RESOLVED'">
                    <div class="grid grid-cols-2 gap-2">
                        <!-- Tombol Konfirmasi Silang Warga (Maksimal 1 Suara per Sesi & Validasi Jarak <= 500m PRD M6) -->
                        <template x-if="!hasConfirmed(selectedEvent.id)">
                            <div>
                                <!-- Jika berada dalam radius <= 500m ATAU Dev Mode aktif: Tombol Aktif Normal -->
                                <template x-if="(getDistanceToEvent(selectedEvent) !== null && getDistanceToEvent(selectedEvent) <= 500) || isDevMode()">
                                    <button @click="confirmFlood(selectedEvent.id)"
                                            :disabled="isConfirming"
                                            class="w-full bg-amber-600 hover:bg-amber-500 text-white font-bold py-2 px-3 rounded-xl text-xs flex items-center justify-center space-x-1.5 shadow-lg shadow-amber-600/20 active:scale-95 transition disabled:opacity-50">
                                        <span>🤝</span>
                                        <span>Masih Banjir</span>
                                    </button>
                                </template>

                                <!-- Jika berada di luar radius > 500m dan bukan Dev Mode: Tombol Terkunci Transparan -->
                                <template x-if="getDistanceToEvent(selectedEvent) !== null && getDistanceToEvent(selectedEvent) > 500 && !isDevMode()">
                                    <button @click="showToast('Konfirmasi lapangan hanya diizinkan untuk warga dalam radius 500m (Jarak Anda: ' + (getDistanceToEvent(selectedEvent) >= 1000 ? (getDistanceToEvent(selectedEvent)/1000).toFixed(1) + ' km' : getDistanceToEvent(selectedEvent) + ' m') + ').', 'warning')"
                                            class="w-full bg-slate-800/90 border border-slate-700/80 text-slate-400 font-medium py-2 px-2 rounded-xl text-[11px] flex items-center justify-center space-x-1 hover:border-amber-500/50 hover:text-slate-300 transition">
                                        <span>📍</span>
                                        <span x-text="'Di Luar Radius (' + (getDistanceToEvent(selectedEvent) >= 1000 ? (getDistanceToEvent(selectedEvent)/1000).toFixed(1) + ' km' : getDistanceToEvent(selectedEvent) + ' m') + ')'"></span>
                                    </button>
                                </template>

                                <!-- Jika GPS belum terdeteksi dan bukan Dev Mode -->
                                <template x-if="getDistanceToEvent(selectedEvent) === null && !isDevMode()">
                                    <button @click="trackUserLocation(); showToast('Mendeteksi lokasi GPS Anda...', 'info')"
                                            :disabled="isDetectingUserCoords"
                                            class="w-full bg-slate-800 border border-amber-500/40 text-amber-300 font-semibold py-2 px-2 rounded-xl text-[11px] flex items-center justify-center space-x-1 shadow transition">
                                        <span>📡</span>
                                        <span x-text="isDetectingUserCoords ? 'Mendeteksi...' : 'Cek GPS Konfirmasi'"></span>
                                    </button>
                                </template>
                            </div>
                        </template>

                        <template x-if="hasConfirmed(selectedEvent.id)">
                            <button disabled
                                    class="bg-slate-800 border border-slate-700 text-slate-400 font-semibold py-2 px-3 rounded-xl text-xs flex items-center justify-center space-x-1.5 cursor-not-allowed">
                                <span>✓</span>
                                <span>Telah Dikonfirmasi</span>
                            </button>
                        </template>

                        <!-- Tombol Penutupan Relawan / Admin (Terproteksi Role) -->
                        <button @click="resolveFlood(selectedEvent.id)"
                                :disabled="isResolving"
                                class="bg-emerald-600/90 hover:bg-emerald-500 text-white font-bold py-2 px-3 rounded-xl text-xs flex items-center justify-center space-x-1.5 shadow-lg shadow-emerald-600/20 active:scale-95 transition disabled:opacity-50">
                            <span>🛡️</span>
                            <span>Tandai Surut</span>
                        </button>
                    </div>
                </template>

                <div class="flex items-center space-x-2">
                    <button @click="openReportWithCoordinates(selectedEvent.latitude, selectedEvent.longitude)"
                            class="flex-1 bg-slate-800 hover:bg-slate-700 border border-slate-600 text-slate-200 font-semibold py-2 px-3 rounded-xl text-xs flex items-center justify-center space-x-1.5 shadow transition">
                        <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Kirim Foto / Laporan Tambahan</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
