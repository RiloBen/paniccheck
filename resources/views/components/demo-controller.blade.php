@if((auth()->check() && auth()->user()->role === 'admin') || (app()->isLocal() && request()->query('dev') == '1'))
<!-- M7: Admin & Dev Mode Controller (Hanya Render untuk Admin / Local Dev Parameter) -->
<div x-data="demoController()" 
     class="fixed bottom-6 left-4 z-40 text-xs font-mono select-none pointer-events-auto">
    
    <!-- Collapsed Toggle Button -->
    <div x-show="!isExpanded" class="transition transform">
        <button @click="isExpanded = true"
                class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-black px-3 py-1.5 rounded-full shadow-2xl border-2 border-amber-300 flex items-center space-x-1.5 active:scale-95 transition">
            <span>⚙️</span>
            <span>DEV / ADMIN</span>
        </button>
    </div>

    <!-- Expanded Admin Control Box -->
    <div x-show="isExpanded" 
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-2"
         class="bg-slate-950/95 border border-amber-500/60 rounded-2xl shadow-2xl p-4 w-72 text-slate-100 backdrop-blur-md space-y-3">
        
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
            <div class="flex items-center space-x-1.5">
                <span class="text-amber-400 font-bold">⚙️ DEV / ADMIN CONTROLLER</span>
            </div>
            <button @click="isExpanded = false" class="text-slate-400 hover:text-white p-0.5 rounded">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <p class="text-[10px] text-slate-400 font-sans">
            Alat demonstrasi 7 menit untuk juri. Memicu status STALE seketika atau reset database.
        </p>

        <!-- Action 1: Fast Forward 2 Hours -->
        <button @click="fastForward()"
                :disabled="isLoading"
                class="w-full bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/60 text-amber-300 font-bold py-2 px-3 rounded-xl flex items-center justify-between text-left transition disabled:opacity-50">
            <div>
                <span class="block text-xs font-semibold">⏩ Majukan Waktu 2 Jam</span>
                <span class="block text-[10px] text-amber-400/80 font-normal font-sans">Ubah event aktif menjadi STALE</span>
            </div>
            <span x-show="isLoading" class="animate-spin text-xs">⏳</span>
        </button>

        <!-- Action 2: Reset Skenario Surabaya -->
        <button @click="resetScenario()"
                :disabled="isLoading"
                class="w-full bg-red-500/20 hover:bg-red-500/30 border border-red-500/50 text-red-300 font-bold py-2 px-3 rounded-xl flex items-center justify-between text-left transition disabled:opacity-50">
            <div>
                <span class="block text-xs font-semibold">🔄 Reset Skenario Demo</span>
                <span class="block text-[10px] text-red-400/80 font-normal font-sans">Jalankan migrate:fresh --seed</span>
            </div>
            <span x-show="isLoading" class="animate-spin text-xs">⏳</span>
        </button>

        <!-- Action 3: Teleport GPS ke Titik Banjir Terpilih -->
        <button @click="teleportToSelected()"
                class="w-full bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/50 text-blue-300 font-bold py-2 px-3 rounded-xl flex items-center justify-between text-left transition">
            <div>
                <span class="block text-xs font-semibold">📍 Posisikan GPS ke Titik Terpilih</span>
                <span class="block text-[10px] text-blue-400/80 font-normal font-sans">Simulasi berada di lokasi (&lt;50m)</span>
            </div>
            <span>🎯</span>
        </button>

        <!-- Toast Feedback -->
        <div x-show="toastMessage" 
             x-cloak
             x-transition
             class="bg-slate-900 border border-slate-700 text-emerald-400 p-2 rounded-lg text-[10px] text-center font-sans"
             x-text="toastMessage">
        </div>
    </div>
</div>

<script>
function demoController() {
    return {
        isExpanded: false,
        isLoading: false,
        toastMessage: '',

        showToast(msg) {
            this.toastMessage = msg;
            setTimeout(() => { this.toastMessage = ''; }, 3500);
        },

        teleportToSelected() {
            window.dispatchEvent(new CustomEvent('teleport-gps-selected'));
            this.showToast('📍 GPS didekatkan ke titik terpilih!');
        },

        async fastForward() {
            this.isLoading = true;
            try {
                const res = await fetch('/api/demo/fast-forward?dev=1', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Paniccheck-Dev': '1'
                    }
                });
                const data = await res.json();
                if (res.ok) {
                    this.showToast('✓ ' + (data.message || 'Waktu dimajukan 2 jam!'));
                    // Re-fetch data peta di Alpine parent
                    if (window.dispatchEvent) {
                        window.dispatchEvent(new CustomEvent('reload-events'));
                    }
                } else {
                    this.showToast('Gagal: ' + (data.message || 'Akses ditolak'));
                }
            } catch (err) {
                this.showToast('Error koneksi simulasi');
            } finally {
                this.isLoading = false;
            }
        },

        async resetScenario() {
            if (!confirm('Yakin ingin mereset seluruh database skenario demo ke kondisi awal?')) return;
            this.isLoading = true;
            try {
                const res = await fetch('/api/demo/reset?dev=1', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Paniccheck-Dev': '1'
                    }
                });
                const data = await res.json();
                if (res.ok) {
                    try {
                        localStorage.removeItem('paniccheck_token_user');
                        localStorage.removeItem('paniccheck_token_admin');
                        localStorage.removeItem('paniccheck_token_relawan');
                        localStorage.removeItem('paniccheck_confirmed_events');
                    } catch (e) {}
                    this.showToast('✓ Database skenario Surabaya berhasil direset!');
                    if (window.dispatchEvent) {
                        window.dispatchEvent(new CustomEvent('reload-events'));
                    }
                } else {
                    this.showToast('Gagal: ' + (data.message || 'Akses ditolak'));
                }
            } catch (err) {
                this.showToast('Error koneksi reset');
            } finally {
                this.isLoading = false;
            }
        }
    }
}
</script>
@endif

