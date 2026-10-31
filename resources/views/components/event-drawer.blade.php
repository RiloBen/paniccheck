<!-- Event Detail Drawer / Quick Preview Card -->
<div x-cloak x-show="selectedEvent !== null"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-full"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-full"
     class="fixed bottom-0 inset-x-0 z-40 max-w-lg mx-auto p-4 pointer-events-none">

    <template x-if="selectedEvent">
        <div class="bg-slate-900/95 border border-slate-700/80 rounded-2xl shadow-2xl backdrop-blur-md p-5 text-slate-100 pointer-events-auto space-y-4">
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

            <!-- Resolved Alert & Leptospirosis Warning (PRD S8) -->
            <template x-if="selectedEvent.status === 'RESOLVED'">
                <div class="bg-emerald-950/40 border border-emerald-600/50 p-3 rounded-xl text-xs text-emerald-200 space-y-1">
                    <div class="font-bold flex items-center space-x-1.5 text-emerald-400">
                        <span>✓</span>
                        <span>Genangan Resmi Dinyatakan Surut</span>
                    </div>
                    <p class="text-[11px] text-slate-300">
                        🛡️ <strong>Waspada Leptospirosis (H+1 s/d H+14):</strong> Gunakan alas kaki tertutup dan segera cuci area kulit dengan sabun jika berkontak dengan sisa lumpur atau genangan air.
                    </p>
                </div>
            </template>

            <!-- Action buttons -->
            <div class="flex items-center space-x-2 pt-1">
                <button @click="openReportWithCoordinates(selectedEvent.latitude, selectedEvent.longitude)"
                        class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2.5 px-4 rounded-xl text-xs flex items-center justify-center space-x-1.5 shadow-lg shadow-blue-600/30 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Lapor Update di Lokasi Ini</span>
                </button>
            </div>
        </div>
    </template>
</div>

