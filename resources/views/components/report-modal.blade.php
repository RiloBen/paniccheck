<!-- Report Modal / Bottom Sheet (Fitur M2: Pelaporan Darurat Tanpa Login) -->
<div x-cloak x-show="showReportModal"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4">

    <div @click.away="showReportModal = false"
         x-show="showReportModal"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-8 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-8 sm:scale-95"
         class="bg-slate-900 border border-slate-700/80 w-full sm:max-w-lg rounded-t-3xl sm:rounded-2xl shadow-2xl p-5 sm:p-6 text-slate-100 max-h-[92vh] overflow-y-auto space-y-4">

        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-white flex items-center space-x-2">
                    <span class="text-red-500">🚨</span>
                    <span>Lapor Banjir Darurat</span>
                </h2>
                <p class="text-xs text-slate-400">Tanpa login • Otomatis dikonsolidasi ke peta warga</p>
            </div>
            <button @click="showReportModal = false" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Form Body -->
        <form @submit.prevent="submitReport" class="space-y-4 text-xs">
            <!-- Lokasi GPS -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <label class="font-semibold text-slate-300">Lokasi Koordinat GPS <span class="text-red-400">*</span></label>
                    <span class="text-[11px] text-slate-400" x-text="locationAccuracy ? 'Akurasi ±' + Math.round(locationAccuracy) + 'm' : ''"></span>
                </div>

                <div class="flex items-center space-x-2">
                    <button type="button" 
                            @click="detectGPS" 
                            :disabled="isDetectingGPS"
                            class="flex-1 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 py-2.5 px-3 rounded-xl font-medium flex items-center justify-center space-x-2 transition">
                        <svg class="w-4 h-4 text-blue-400" :class="{ 'animate-spin': isDetectingGPS }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                        <span x-text="isDetectingGPS ? 'Mencari Koordinat...' : '📍 Ambil Lokasi Saya'"></span>
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-2 text-[11px]">
                    <div class="bg-slate-950/60 border border-slate-800 p-2 rounded-lg">
                        <span class="text-slate-500 block text-[10px]">Latitude</span>
                        <input type="text" x-model="form.latitude" readonly class="bg-transparent text-slate-200 font-mono w-full focus:outline-none">
                    </div>
                    <div class="bg-slate-950/60 border border-slate-800 p-2 rounded-lg">
                        <span class="text-slate-500 block text-[10px]">Longitude</span>
                        <input type="text" x-model="form.longitude" readonly class="bg-transparent text-slate-200 font-mono w-full focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- Ketinggian Air (3 Level Visual) -->
            <div class="space-y-1.5">
                <label class="font-semibold text-slate-300">Perkiraan Ketinggian Air <span class="text-red-400">*</span></label>
                <div class="grid grid-cols-3 gap-2">
                    <!-- Semata Kaki -->
                    <label class="cursor-pointer border rounded-xl p-2.5 flex flex-col items-center justify-center text-center space-y-1 transition"
                           :class="form.water_level === 'semata_kaki' ? 'bg-yellow-500/20 border-yellow-500 text-yellow-300' : 'bg-slate-800/60 border-slate-700/60 text-slate-400 hover:border-slate-600'">
                        <input type="radio" name="water_level" value="semata_kaki" x-model="form.water_level" class="sr-only">
                        <span class="text-lg">🦶</span>
                        <span class="font-bold text-[11px] leading-tight">Semata Kaki</span>
                        <span class="text-[9px] opacity-75">10–30 cm</span>
                    </label>

                    <!-- Selutut -->
                    <label class="cursor-pointer border rounded-xl p-2.5 flex flex-col items-center justify-center text-center space-y-1 transition"
                           :class="form.water_level === 'selutut' ? 'bg-amber-500/20 border-amber-500 text-amber-300' : 'bg-slate-800/60 border-slate-700/60 text-slate-400 hover:border-slate-600'">
                        <input type="radio" name="water_level" value="selutut" x-model="form.water_level" class="sr-only">
                        <span class="text-lg">🦵</span>
                        <span class="font-bold text-[11px] leading-tight">Selutut</span>
                        <span class="text-[9px] opacity-75">30–60 cm</span>
                    </label>

                    <!-- Sepinggang ke Atas -->
                    <label class="cursor-pointer border rounded-xl p-2.5 flex flex-col items-center justify-center text-center space-y-1 transition"
                           :class="form.water_level === 'sepinggang_keatas' ? 'bg-red-500/20 border-red-500 text-red-300' : 'bg-slate-800/60 border-slate-700/60 text-slate-400 hover:border-slate-600'">
                        <input type="radio" name="water_level" value="sepinggang_keatas" x-model="form.water_level" class="sr-only">
                        <span class="text-lg">⚠️</span>
                        <span class="font-bold text-[11px] leading-tight">Sepinggang+</span>
                        <span class="text-[9px] opacity-75">>60 cm</span>
                    </label>
                </div>
            </div>

            <!-- Unggah Foto Bukti Kamera -->
            <div class="space-y-1.5">
                <label class="font-semibold text-slate-300">Foto Bukti Lapangan (Kamera Langsung)</label>
                <div class="flex items-center space-x-3">
                    <label class="flex-1 cursor-pointer bg-slate-800/80 hover:bg-slate-700/80 border border-dashed border-slate-600 hover:border-slate-500 py-3 px-3 rounded-xl flex items-center justify-center space-x-2 text-slate-300 transition">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <span class="text-xs" x-text="photoPreview ? 'Ganti Foto' : 'Ambil Foto / Pilih File'"></span>
                        <input type="file" accept="image/*" capture="environment" @change="handlePhotoChange($event)" x-ref="photoInput" class="sr-only">
                    </label>

                    <template x-if="photoPreview">
                        <div class="relative w-12 h-12 rounded-xl overflow-hidden border border-slate-700 flex-shrink-0">
                            <img :src="photoPreview" class="w-full h-full object-cover">
                            <button type="button" @click="photoPreview = null; photoFile = null; $refs.photoInput.value = ''" class="absolute top-0.5 right-0.5 bg-red-600 text-white rounded-full p-0.5 text-[8px] leading-none">✕</button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Deskripsi Singkat -->
            <div class="space-y-1.5">
                <label class="font-semibold text-slate-300">Deskripsi Kondisi / Patokan Jalan</label>
                <textarea x-model="form.description" 
                          rows="2" 
                          placeholder="Cth: Depan SPBU, arus cukup deras, mobil kecil tidak bisa lewat..."
                          class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-3 py-2 text-slate-200 placeholder-slate-500 focus:outline-none focus:border-blue-500 transition text-xs"></textarea>
            </div>

            <!-- Error Banner (Item 3: visible above submit, inside modal z-context) -->
            <div x-show="reportError" x-cloak class="bg-red-900/80 border border-red-500/60 text-red-200 text-xs rounded-xl px-3 py-2.5 flex items-start space-x-2">
                <span class="text-red-400 mt-0.5">⚠</span>
                <span x-text="reportError"></span>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button type="button" 
                        @click="submitReport()"
                        :disabled="isSubmitting"
                        class="w-full bg-red-600 hover:bg-red-500 active:bg-red-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg shadow-red-600/30 flex items-center justify-center space-x-2 transition text-sm disabled:opacity-50">
                    <svg x-show="isSubmitting" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span x-text="isSubmitting ? 'Mengirim Laporan...' : '🚨 Kirim Laporan Darurat'"></span>
                </button>
            </div>
        </form>
    </div>
</div>

