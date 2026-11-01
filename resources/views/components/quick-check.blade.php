<!-- M10: Floating Quick Check / Paste Text Scanner (Letak Paling Atas Layar) -->
<div class="absolute top-3 inset-x-0 z-30 px-3 sm:px-4 max-w-lg mx-auto pointer-events-auto">
    <div class="bg-slate-900/95 backdrop-blur-md border border-slate-700/80 rounded-2xl shadow-2xl p-3 text-slate-100 transition-all">
        <!-- Input Bar with Search, Loading Indicator & Dedicated Cari Button -->
        <form @submit.prevent="runQuickCheck()" class="relative flex items-center">
            <span class="absolute left-3 text-slate-400">
                <template x-if="isScanningQuickCheck">
                    <svg class="w-4 h-4 text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                </template>
                <template x-if="!isScanningQuickCheck">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </template>
            </span>
            <input type="text"
                   x-model="quickCheckText"
                   @keydown.enter.prevent="runQuickCheck()"
                   placeholder="Tempel broadcast WA banjir apa pun (cth: A Yani banjir selutut)..."
                   class="w-full bg-slate-800/90 border border-slate-700 rounded-xl pl-9 pr-24 py-2 text-xs text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500 transition">
            
            <div class="absolute right-1.5 flex items-center space-x-1">
                <button type="button"
                        x-show="quickCheckText" 
                        @click="quickCheckText = ''; quickCheckResult = null" 
                        class="text-slate-400 hover:text-white p-1 rounded-full hover:bg-slate-700 transition"
                        title="Bersihkan input">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
                <button type="submit"
                        :disabled="isScanningQuickCheck || !quickCheckText.trim()"
                        class="bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white font-bold text-[11px] px-2.5 py-1 rounded-lg shadow transition flex items-center space-x-1 cursor-pointer">
                    <span x-text="isScanningQuickCheck ? 'Cari...' : 'Cari'"></span>
                </button>
            </div>
        </form>

        <!-- Sample Street Chips for Quick 1-Click Verification -->
        <div class="flex items-center space-x-1.5 mt-2 overflow-x-auto pb-1 text-[11px] scrollbar-none text-slate-400">
            <span class="text-[10px] text-slate-500 whitespace-nowrap">Coba:</span>
            <button @click="quickCheckText = 'Info lur Mayjen Sungkono banjir sepinggang macet total'; runQuickCheck()" 
                    class="px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 hover:border-red-500/50 hover:text-white whitespace-nowrap transition">
                Sungkono
            </button>
            <button @click="quickCheckText = 'Raya Darmo depan Bungkul genangan semata kaki'; runQuickCheck()" 
                    class="px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 hover:border-amber-500/50 hover:text-white whitespace-nowrap transition">
                Darmo
            </button>
            <button @click="quickCheckText = 'Waspada lur, Jl. Ahmad Yani depan Royal Plaza banjir selutut macet parah'; runQuickCheck()" 
                    class="px-2 py-0.5 rounded-full bg-slate-800 border border-amber-500/40 text-amber-300 hover:border-red-500/50 hover:text-white whitespace-nowrap transition">
                A. Yani (Teks Bebas)
            </button>
            <button @click="quickCheckText = 'Kertajaya Indah air selutut mogok'; runQuickCheck()" 
                    class="px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 hover:border-red-500/50 hover:text-white whitespace-nowrap transition">
                Kertajaya
            </button>
            <button @click="quickCheckText = 'Jalan Pemuda aman lancar tidak ada banjir'; runQuickCheck()" 
                    class="px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 hover:border-emerald-500/50 hover:text-white whitespace-nowrap transition">
                Jl Pemuda (Aman)
            </button>
        </div>

        <!-- Result Card Dropdown -->
        <template x-if="quickCheckResult">
            <div class="mt-2.5 pt-2.5 border-t border-slate-800 text-xs transition-all">
                <!-- Result 1: ACTIVE FLOOD -->
                <template x-if="quickCheckResult.type === 'ACTIVE'">
                    <div class="bg-red-950/60 border border-red-500/50 rounded-xl p-2.5 text-red-200 flex items-start justify-between space-x-2">
                        <div class="space-y-0.5 flex-1 min-w-0">
                            <div class="font-bold text-red-400 flex items-center space-x-1.5">
                                <span class="h-2 w-2 rounded-full bg-red-500 animate-ping inline-block"></span>
                                <span>⚠️ Titik Banjir Aktif Terdeteksi</span>
                            </div>
                            <p class="font-semibold text-white text-[11px] truncate" x-text="quickCheckResult.event ? quickCheckResult.event.location_name : quickCheckResult.location_hint"></p>
                            <p class="text-[10px] text-red-300">
                                Ketinggian: <span class="font-bold text-white uppercase" x-text="quickCheckResult.event ? quickCheckResult.event.water_level : quickCheckResult.water_level"></span> • 
                                <span x-text="(quickCheckResult.event ? quickCheckResult.event.reports_count : 1) + ' Laporan Warga'"></span>
                            </p>
                        </div>
                        <button @click="focusEvent(quickCheckResult.event)"
                                class="bg-red-600 hover:bg-red-500 text-white font-bold px-2.5 py-1.5 rounded-lg text-[11px] shadow transition whitespace-nowrap flex items-center space-x-1">
                            <span>Fokuskan</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                </template>

                <!-- Result 2: STALE (NEED CONFIRMATION) -->
                <template x-if="quickCheckResult.type === 'STALE'">
                    <div class="bg-amber-950/50 border border-amber-500/40 rounded-xl p-2.5 text-amber-200 flex items-start justify-between space-x-2">
                        <div class="space-y-0.5 flex-1 min-w-0">
                            <div class="font-bold text-amber-400 flex items-center space-x-1.5">
                                <span>⏱️</span>
                                <span>Perlu Konfirmasi (Info > 2 Jam)</span>
                            </div>
                            <p class="font-semibold text-white text-[11px] truncate" x-text="quickCheckResult.event ? quickCheckResult.event.location_name : quickCheckResult.location_hint"></p>
                            <p class="text-[10px] text-amber-300">
                                Belum ada laporan pembaruan baru. Kondisi mungkin sudah berubah.
                            </p>
                        </div>
                        <button @click="focusEvent(quickCheckResult.event)"
                                class="bg-amber-600 hover:bg-amber-500 text-white font-bold px-2.5 py-1.5 rounded-lg text-[11px] shadow transition whitespace-nowrap flex items-center space-x-1">
                            <span>Fokuskan</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                </template>

                <!-- Result 3: RESOLVED -->
                <template x-if="quickCheckResult.type === 'RESOLVED'">
                    <div class="bg-emerald-950/50 border border-emerald-500/40 rounded-xl p-2.5 text-emerald-200 flex items-start justify-between space-x-2">
                        <div class="space-y-0.5 flex-1 min-w-0">
                            <div class="font-bold text-emerald-400 flex items-center space-x-1.5">
                                <span>✓</span>
                                <span>Lokasi Sudah Surut</span>
                            </div>
                            <p class="font-semibold text-white text-[11px] truncate" x-text="quickCheckResult.event ? quickCheckResult.event.location_name : quickCheckResult.location_hint"></p>
                            <p class="text-[10px] text-slate-300">
                                Genangan telah dinyatakan selesai. Hati-hati risiko Leptospirosis.
                            </p>
                        </div>
                        <button @click="focusEvent(quickCheckResult.event)"
                                class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-2.5 py-1.5 rounded-lg text-[11px] shadow transition whitespace-nowrap flex items-center space-x-1">
                            <span>Fokuskan</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                </template>

                <!-- Result 4: NEW_DETECTION (Indikasi Banjir Baru dari Teks Bebas / Belum Ada di Peta) -->
                <template x-if="quickCheckResult.type === 'NEW_DETECTION'">
                    <div class="bg-gradient-to-r from-red-950/80 to-slate-900 border border-red-500/70 rounded-xl p-2.5 text-red-200 flex items-start justify-between space-x-2 shadow-xl">
                        <div class="space-y-0.5 flex-1 min-w-0">
                            <div class="font-bold text-red-400 flex items-center space-x-1.5">
                                <span class="h-2 w-2 rounded-full bg-red-400 animate-ping inline-block"></span>
                                <span>🚨 Indikasi Titik Baru Terdeteksi dari Teks</span>
                            </div>
                            <p class="font-semibold text-white text-[11px] truncate" x-text="quickCheckResult.location_hint"></p>
                            <p class="text-[10px] text-red-300 flex items-center space-x-1">
                                <span>Estimasi Air:</span>
                                <span class="font-bold text-white uppercase" x-text="quickCheckResult.water_level"></span>
                                <span class="text-amber-300 ml-1">• Belum ada di peta</span>
                            </p>
                        </div>
                        <button @click="plotNewDetectionFromQuickCheck(quickCheckResult)"
                                class="bg-red-600 hover:bg-red-500 active:scale-95 text-white font-bold px-3 py-1.5 rounded-lg text-[11px] shadow-lg transition whitespace-nowrap flex items-center space-x-1">
                            <span>Plot ke Peta</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        </button>
                    </div>
                </template>

                <!-- Result 5: SAFE (Street Recognized, No Flood Found) -->
                <template x-if="quickCheckResult.type === 'SAFE'">
                    <div class="bg-emerald-950/40 border border-emerald-600/30 rounded-xl p-2.5 text-emerald-200">
                        <div class="font-bold text-emerald-400 flex items-center space-x-1.5">
                            <span>🟢</span>
                            <span>Jalur Aman Terpantau</span>
                        </div>
                        <p class="text-[11px] text-slate-300 mt-0.5">
                            Tidak ada laporan titik banjir aktif di sekitar <strong class="text-white" x-text="quickCheckResult.query || quickCheckResult.location_hint"></strong> saat ini.
                        </p>
                    </div>
                </template>

                <!-- Result 6: UNRECOGNIZED (Bimbingan Ramah) -->
                <template x-if="quickCheckResult.type === 'UNRECOGNIZED'">
                    <div class="bg-slate-800/80 border border-slate-700 rounded-xl p-2.5 text-slate-300 text-[11px] flex items-start justify-between space-x-2">
                        <div class="space-y-0.5 flex-1">
                            <span class="font-medium text-slate-200">🔍 Belum terdeteksi lokasi atau indikasi banjir spesifik.</span>
                            <p class="text-[10px] text-slate-400">Sertakan nama jalan Surabaya (cth: Mayjen Sungkono, A Yani, Kenjeran) atau tempel pesan broadcast darurat.</p>
                        </div>
                        <button @click="openReportModal()"
                                class="bg-slate-700 hover:bg-slate-600 text-white font-medium px-2 py-1 rounded-lg text-[10px] whitespace-nowrap transition">
                            Lapor Manual
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
