<!-- Leaflet Interactive Map Container (Fitur M4) -->
<div class="w-full h-full relative">
    <!-- Map Canvas Element -->
    <div id="map" class="w-full h-full z-0"></div>

    <!-- Filter Bar Melayang (Top Right) -->
    <div class="absolute top-3 right-3 z-10 flex items-center space-x-1.5 bg-slate-900/90 backdrop-blur-md p-1.5 rounded-xl border border-slate-700/70 shadow-lg text-xs">
        <button @click="setFilter('all')" 
                :class="currentFilter === 'all' ? 'bg-blue-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                class="px-2.5 py-1 rounded-lg transition text-[11px]">
            Semua (<span x-text="events.length"></span>)
        </button>
        <button @click="setFilter('ACTIVE')" 
                :class="currentFilter === 'ACTIVE' ? 'bg-red-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                class="px-2.5 py-1 rounded-lg transition text-[11px] flex items-center space-x-1">
            <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
            <span>Aktif</span>
        </button>
        <button @click="setFilter('STALE')" 
                :class="currentFilter === 'STALE' ? 'bg-amber-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                class="px-2.5 py-1 rounded-lg transition text-[11px]">
            Perlu Konfirmasi
        </button>
    </div>

    <!-- Map Legend (Bottom Left) -->
    <div class="absolute bottom-20 left-3 z-10 hidden sm:block bg-slate-900/85 backdrop-blur-md p-2.5 rounded-xl border border-slate-700/60 shadow-lg text-[10px] space-y-1">
        <div class="font-bold text-slate-300 uppercase tracking-wider text-[9px] mb-1">Tingkat Genangan</div>
        <div class="flex items-center space-x-2 text-slate-300">
            <span class="w-2.5 h-2.5 rounded-full bg-red-500 shadow-sm shadow-red-500/50"></span>
            <span>>60 cm (Sepinggang+)</span>
        </div>
        <div class="flex items-center space-x-2 text-slate-300">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 shadow-sm shadow-amber-500/50"></span>
            <span>30-60 cm (Selutut)</span>
        </div>
        <div class="flex items-center space-x-2 text-slate-300">
            <span class="w-2.5 h-2.5 rounded-full bg-yellow-400 shadow-sm shadow-yellow-400/50"></span>
            <span>10-30 cm (Semata Kaki)</span>
        </div>
        <div class="flex items-center space-x-2 text-slate-400">
            <span class="w-2.5 h-2.5 rounded-full bg-slate-500"></span>
            <span>STALE (>2 jam)</span>
        </div>
        <div class="flex items-center space-x-2 text-emerald-400">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
            <span>RESOLVED (Surut)</span>
        </div>
    </div>
</div>

