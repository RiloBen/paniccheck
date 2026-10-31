<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PANICCHECK - Pantau & Lapor Banjir Surabaya')</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        panic: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            900: '#0f172a',
                            danger: '#ef4444',
                            warning: '#f59e0b',
                            success: '#10b981',
                            stale: '#64748b'
                        }
                    }
                }
            }
        }
    </script>

    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Alpine.js CDN -->
    <script defer src="https://unpkg.com/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <style>
        /* Leaflet custom map container */
        .leaflet-container {
            font-family: inherit;
            background-color: #0f172a;
        }
        /* Marker pulse animation */
        @keyframes pulse-ring {
            0% { transform: scale(0.9); opacity: 0.9; }
            50% { transform: scale(1.3); opacity: 0.3; }
            100% { transform: scale(0.9); opacity: 0.9; }
        }
        .marker-pulse {
            animation: pulse-ring 2s infinite ease-in-out;
        }
    </style>
</head>
<body class="h-full flex flex-col overflow-hidden select-none" 
      x-data="{
          toast: { show: false, message: '', type: 'success' },
          showToast(msg, type = 'success') {
              this.toast.message = msg;
              this.toast.type = type;
              this.toast.show = true;
              setTimeout(() => this.toast.show = false, 3500);
          }
      }"
      @show-toast.window="showToast($event.detail.msg, $event.detail.type)">

    <!-- Top Navigation Bar -->
    <header class="h-14 bg-slate-900/95 backdrop-blur border-b border-slate-800 px-4 flex items-center justify-between z-30 flex-shrink-0">
        <div class="flex items-center space-x-2.5">
            <div class="w-8 h-8 rounded-lg bg-red-600 flex items-center justify-center font-black text-white text-base shadow-lg shadow-red-600/30">
                !
            </div>
            <div>
                <div class="flex items-center space-x-1.5">
                    <span class="font-extrabold text-base tracking-wider text-white">PANICCHECK</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-400 font-semibold border border-blue-500/30">Surabaya</span>
                </div>
                <div class="text-[11px] text-slate-400 leading-none">Lapisan Kepercayaan Pelaporan Banjir</div>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <div class="hidden sm:flex items-center space-x-1.5 text-xs text-slate-300 bg-slate-800/80 px-2.5 py-1 rounded-full border border-slate-700/60">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Live Monitor</span>
            </div>
        </div>
    </header>

    <!-- Main Content Slot -->
    <main class="flex-1 relative overflow-hidden">
        @yield('content')
    </main>

    <!-- Toast Notification -->
    <div x-cloak x-show="toast.show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-24 left-1/2 -translate-x-1/2 z-50 max-w-sm w-[90%] pointer-events-none">
        <div :class="{
                'bg-emerald-600/95 border-emerald-500 text-white': toast.type === 'success',
                'bg-red-600/95 border-red-500 text-white': toast.type === 'error',
                'bg-amber-600/95 border-amber-500 text-white': toast.type === 'warning'
             }" 
             class="px-4 py-3 rounded-xl shadow-2xl backdrop-blur border text-sm font-medium flex items-center space-x-2.5">
            <span x-text="toast.message"></span>
        </div>
    </div>

    @stack('scripts')
</body>
</html>

