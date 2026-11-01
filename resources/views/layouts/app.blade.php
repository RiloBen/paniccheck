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
        [x-cloak] {
            display: none !important;
        }
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
      x-data="authApp()"
      x-init="initAuth()"
      x-on:show-toast.window="showToast($event.detail.msg, $event.detail.type)"
      x-on:auth-error.window="handleAuthError($event.detail)"
      x-on:open-login-modal.window="openLoginModal()"
      x-on:force-logout.window="forceLogout()">

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

        <div class="flex items-center space-x-2.5">
            <div class="hidden sm:flex items-center space-x-1.5 text-xs text-slate-300 bg-slate-800/80 px-2.5 py-1 rounded-full border border-slate-700/60">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Live Monitor</span>
            </div>

            <!-- Mode Pengunjung Anonim (Guest): Tombol Masuk / Relawan -->
            <template x-if="!isLoggedIn">
                <button @click="openLoginModal()" 
                        class="bg-blue-600 hover:bg-blue-500 active:scale-95 text-white text-xs font-bold py-1.5 px-3.5 rounded-xl shadow-lg shadow-blue-600/30 border border-blue-400/40 flex items-center space-x-1.5 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                    <span>Masuk / Relawan</span>
                </button>
            </template>

            <!-- Mode Sudah Login (Warga / Relawan / Admin): Dropdown Profil -->
            <template x-if="isLoggedIn">
                <div class="relative" @click.outside="showProfileDropdown = false">
                    <button @click="showProfileDropdown = !showProfileDropdown" 
                            class="flex items-center space-x-2 py-1 px-2.5 rounded-xl text-xs font-semibold border transition"
                            :class="{
                                'bg-emerald-950/60 border-emerald-500/50 text-emerald-300 hover:bg-emerald-900/50 shadow-sm shadow-emerald-500/20': userRole === 'relawan' || userRole === 'admin',
                                'bg-slate-800/80 border-slate-700 text-slate-200 hover:bg-slate-800': userRole === 'user'
                            }">
                        <!-- Icon Avatar -->
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold"
                              :class="{
                                  'bg-emerald-600 text-white': userRole === 'relawan' || userRole === 'admin',
                                  'bg-blue-600 text-white': userRole === 'user'
                              }">
                            <span x-text="userName ? userName.charAt(0).toUpperCase() : 'U'"></span>
                        </span>

                        <!-- Label Profil di Navbar -->
                        <span class="hidden sm:inline font-bold" 
                              x-text="(userRole === 'relawan' || userRole === 'admin') ? 'Mode Relawan Aktif' : userName"></span>
                        <span class="sm:hidden font-bold" 
                              x-text="(userRole === 'relawan' || userRole === 'admin') ? 'Relawan Aktif' : 'Profil'"></span>

                        <svg class="w-3.5 h-3.5 text-slate-400 transition transform" :class="{ 'rotate-180': showProfileDropdown }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div x-cloak x-show="showProfileDropdown" 
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
                         class="absolute right-0 mt-2 w-56 bg-slate-900/95 border border-slate-700/80 rounded-2xl shadow-2xl backdrop-blur-md py-2 z-50 text-xs text-slate-200">
                        
                        <!-- User Info Header in Dropdown -->
                        <div class="px-3.5 py-2 border-b border-slate-800">
                            <p class="font-bold text-white text-sm truncate" x-text="userName"></p>
                            <p class="text-[11px] text-slate-400 truncate" x-text="userEmail"></p>
                            <span class="inline-block mt-1 text-[10px] font-bold px-2 py-0.5 rounded-full"
                                  :class="{
                                      'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': userRole === 'relawan' || userRole === 'admin',
                                      'bg-blue-500/20 text-blue-300 border border-blue-500/30': userRole === 'user'
                                  }"
                                  x-text="(userRole === 'relawan' || userRole === 'admin') ? '🛡️ Relawan Lapangan' : '👤 Warga Terdaftar'"></span>
                        </div>

                        <!-- Dropdown Actions -->
                        <div class="py-1">
                            <!-- Tombol "Daftar Jadi Relawan" (Hanya untuk Warga Biasa: role === 'user') -->
                            <template x-if="userRole === 'user'">
                                <button @click="submitUpgradeRelawan()" 
                                        :disabled="isUpgrading"
                                        class="w-full text-left px-3.5 py-2 hover:bg-slate-800/80 text-amber-300 font-semibold flex items-center space-x-2 transition disabled:opacity-50">
                                    <span>🎖️</span>
                                    <span x-text="isUpgrading ? 'Memproses...' : 'Daftar Jadi Relawan'"></span>
                                </button>
                            </template>

                            <!-- Tombol "Keluar" (Logout) -->
                            <button @click="submitLogout()" 
                                    class="w-full text-left px-3.5 py-2 hover:bg-red-950/40 text-red-400 font-semibold flex items-center space-x-2 transition">
                                <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                <span>Keluar</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </header>

    <!-- Main Content Slot -->
    <main class="flex-1 relative overflow-hidden">
        @yield('content')
    </main>

    <!-- Modal Login SPA (Wajib x-cloak Anti-Berkedip) -->
    <div x-cloak x-show="showLoginModal" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="closeLoginModal()">
        
        <div @click.outside="closeLoginModal()" 
             class="bg-slate-900 border border-slate-700/80 w-full max-w-sm rounded-3xl p-6 shadow-2xl relative text-slate-100 space-y-4"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-2">

            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">🔐</span>
                    <div>
                        <h3 class="font-bold text-white text-base">Masuk ke PANICCHECK</h3>
                        <p class="text-[11px] text-slate-400">Akses fitur verifikasi & relawan lapangan</p>
                    </div>
                </div>
                <button @click="closeLoginModal()" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Error Banner -->
            <template x-if="loginError">
                <div class="bg-red-950/60 border border-red-500/60 p-2.5 rounded-xl text-xs text-red-200 flex items-center space-x-2">
                    <span>⚠️</span>
                    <span x-text="loginError"></span>
                </div>
            </template>

            <!-- Login Form -->
            <form @submit.prevent="submitLogin()" class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-300 font-semibold mb-1">Alamat Email</label>
                    <input type="email" x-model="loginEmail" required placeholder="contoh: relawan@paniccheck.com" 
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                </div>

                <div>
                    <label class="block text-slate-300 font-semibold mb-1">Kata Sandi</label>
                    <input type="password" x-model="loginPassword" required placeholder="••••••••" 
                           class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                </div>

                <button type="submit" 
                        :disabled="isLoggingIn"
                        class="w-full bg-blue-600 hover:bg-blue-500 active:scale-95 text-white font-bold py-2.5 rounded-xl text-xs shadow-lg shadow-blue-600/30 flex items-center justify-center space-x-2 transition disabled:opacity-50">
                    <span x-show="!isLoggingIn">Masuk Sekarang</span>
                    <span x-show="isLoggingIn">Memproses...</span>
                </button>
            </form>

            <!-- Quick Demo Fill Buttons -->
            <div class="pt-2 border-t border-slate-800 text-[11px] text-slate-400">
                <span class="block mb-1.5 font-medium text-slate-500">Akun Cepat Demo:</span>
                <div class="grid grid-cols-2 gap-1.5">
                    <button type="button" 
                            @click="loginEmail = 'relawan@paniccheck.com'; loginPassword = 'rahasia123'"
                            class="bg-slate-800 hover:bg-slate-700/80 text-emerald-300 border border-emerald-500/30 py-1 px-2 rounded-lg text-left truncate transition">
                        🛡️ Relawan Demo
                    </button>
                    <button type="button" 
                            @click="loginEmail = 'admin@paniccheck.com'; loginPassword = 'rahasia123'"
                            class="bg-slate-800 hover:bg-slate-700/80 text-amber-300 border border-amber-500/30 py-1 px-2 rounded-lg text-left truncate transition">
                        ⚡ Admin Demo
                    </button>
                </div>
            </div>
        </div>
    </div>

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
                'bg-amber-600/95 border-amber-500 text-white': toast.type === 'warning',
                'bg-blue-600/95 border-blue-500 text-white': toast.type === 'info'
             }" 
             class="px-4 py-3 rounded-xl shadow-2xl backdrop-blur border text-sm font-medium flex items-center space-x-2.5">
            <span x-text="toast.message"></span>
        </div>
    </div>

    <script>
    function authApp() {
        return {
            isLoggedIn: false,
            userRole: 'guest',
            userName: '',
            userEmail: '',
            showLoginModal: false,
            showProfileDropdown: false,
            loginEmail: '',
            loginPassword: '',
            loginError: '',
            isLoggingIn: false,
            isUpgrading: false,
            toast: { show: false, message: '', type: 'success' },
            toastTimer: null,

            initAuth() {
                this.syncStateFromStorage();
                window.addEventListener('storage', () => {
                    this.syncStateFromStorage();
                    window.dispatchEvent(new CustomEvent('auth-changed', { detail: { role: this.userRole } }));
                });
            },

            syncStateFromStorage() {
                const token = localStorage.getItem('access_token');
                const role = localStorage.getItem('user_role');
                const name = localStorage.getItem('user_name');
                const email = localStorage.getItem('user_email');

                if (token && role) {
                    this.isLoggedIn = true;
                    this.userRole = role;
                    this.userName = name || (role === 'relawan' ? 'Relawan' : (role === 'admin' ? 'Admin' : 'Warga'));
                    this.userEmail = email || '';
                } else {
                    this.isLoggedIn = false;
                    this.userRole = 'guest';
                    this.userName = '';
                    this.userEmail = '';
                }
            },

            showToast(msg, type = 'success') {
                if (this.toastTimer) clearTimeout(this.toastTimer);
                this.toast.message = msg;
                this.toast.type = type;
                this.toast.show = true;
                this.toastTimer = setTimeout(() => this.toast.show = false, 3500);
            },

            handleAuthError(detail) {
                const status = (typeof detail === 'object' && detail !== null && detail.status) ? detail.status : (typeof detail === 'number' ? detail : 401);
                const message = (typeof detail === 'object' && detail !== null && detail.message)
                    ? detail.message 
                    : (status === 401 ? 'Sesi telah berakhir. Silakan masuk kembali.' : 'Akses ditolak.');
                this.showToast(message, 'error');
                if (status === 401) {
                    this.forceLogout();
                }
            },

            openLoginModal() {
                this.loginError = '';
                this.loginEmail = '';
                this.loginPassword = '';
                this.showLoginModal = true;
            },

            closeLoginModal() {
                this.showLoginModal = false;
                this.loginError = '';
            },

            async submitLogin() {
                if (!this.loginEmail || !this.loginPassword) {
                    this.loginError = 'Email dan password wajib diisi.';
                    return;
                }

                this.isLoggingIn = true;
                this.loginError = '';

                try {
                    const res = await fetch('/api/login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            email: this.loginEmail,
                            password: this.loginPassword
                        })
                    });

                    const data = await res.json().catch(() => ({}));

                    if (res.ok && data.access_token) {
                        const role = data.user?.role || 'user';
                        const name = data.user?.name || 'Warga';
                        const email = data.user?.email || this.loginEmail;

                        localStorage.setItem('access_token', data.access_token);
                        localStorage.setItem('user_role', role);
                        localStorage.setItem('user_name', name);
                        localStorage.setItem('user_email', email);
                        localStorage.setItem('paniccheck_token_' + role, data.access_token);

                        this.syncStateFromStorage();
                        this.closeLoginModal();
                        this.showToast('✓ Berhasil Masuk! Selamat datang, ' + name, 'success');

                        // Notifikasi ke seluruh komponen peta untuk sinkronisasi seketika
                        window.dispatchEvent(new CustomEvent('auth-changed', { detail: { role: role } }));
                    } else {
                        this.loginError = data.message || 'Email atau password salah.';
                        this.showToast(this.loginError, 'error');
                    }
                } catch (err) {
                    console.error('Login error:', err);
                    this.loginError = 'Gagal terhubung ke server.';
                    this.showToast(this.loginError, 'error');
                } finally {
                    this.isLoggingIn = false;
                }
            },

            async submitLogout() {
                const token = localStorage.getItem('access_token');
                this.showProfileDropdown = false;

                if (token) {
                    try {
                        await fetch('/api/logout', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Authorization': 'Bearer ' + token
                            }
                        });
                    } catch (e) {
                        console.warn('Logout network error:', e);
                    }
                }

                this.forceLogout();
                this.showToast('✓ Berhasil keluar dari sistem.', 'info');
            },

            forceLogout() {
                localStorage.removeItem('access_token');
                localStorage.removeItem('user_role');
                localStorage.removeItem('user_name');
                localStorage.removeItem('user_email');
                localStorage.removeItem('paniccheck_token_user');
                localStorage.removeItem('paniccheck_token_relawan');
                localStorage.removeItem('paniccheck_token_admin');

                this.syncStateFromStorage();
                this.showProfileDropdown = false;
                window.dispatchEvent(new CustomEvent('auth-changed', { detail: { role: 'guest' } }));
            },

            async submitUpgradeRelawan() {
                const token = localStorage.getItem('access_token');
                if (!token) {
                    this.showToast('Harap login terlebih dahulu.', 'warning');
                    return;
                }

                this.isUpgrading = true;
                this.showProfileDropdown = false;

                try {
                    const res = await fetch('/api/upgrade-relawan', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Authorization': 'Bearer ' + token
                        }
                    });

                    const data = await res.json().catch(() => ({}));

                    if (res.ok) {
                        localStorage.setItem('user_role', 'relawan');
                        localStorage.setItem('paniccheck_token_relawan', token);
                        this.syncStateFromStorage();
                        this.showToast('🎉 Selamat! Akun Anda telah ditingkatkan menjadi Relawan.', 'success');
                        window.dispatchEvent(new CustomEvent('auth-changed', { detail: { role: 'relawan' } }));
                    } else {
                        if (res.status === 401 || res.status === 403) {
                            this.handleAuthError({ status: res.status, message: data.message });
                        } else {
                            this.showToast(data.message || 'Gagal upgrade menjadi relawan.', 'error');
                        }
                    }
                } catch (err) {
                    console.error('Upgrade error:', err);
                    this.showToast('Gagal menghubungi server.', 'error');
                } finally {
                    this.isUpgrading = false;
                }
            }
        };
    }
    </script>


    @stack('scripts')
</body>
</html>

