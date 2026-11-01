# PANICCHECK 🌊🚨
**Real-Time Flood Monitoring, Crowdsourced Reporting & Verification Platform (Surabaya Urban Disaster Resilience)**  
*Developed for Hackathon INCEPTION 2026 — Subtema 3: Sustainable Environment and Disaster Resilience*

---

## 📌 Ringkasan Proyek

**PANICCHECK** adalah platform pemantauan dan pelaporan bencana berbasis komunitas (*crowdsourced*) yang mentransformasi laporan warga yang tersebar menjadi satu **kejadian tunggal teragregasi (*clustered event*)** yang terlokalisasi, berbukti foto, terverifikasi geospasial, dan memiliki label kesegaran waktu transparan (*Freshness Tag* / *STALE Status*).

> **Mengatasi "The Panic Delusion":**  
> Platform darurat konvensional sering berasumsi bahwa warga di tengah kepanikan banjir akan membuka aplikasi rumit, login, dan mengisi form panjang. PANICCHECK membalik pendekatan tersebut: pelaporan darurat dapat dilakukan **instan tanpa login**, didukung **Quick Check Text Scanner** (ekstraksi pesan broadcast WhatsApp), serta sistem verifikasi silang geospasial lapangan $\le 500\text{ m}$.

---

## 🚀 Fitur Unggulan

1. **Alur Pelaporan Kilat Bebas Hambatan (The Panic Delusion Solver)**:
   - Form lapor darurat instan langsung dari layar utama tanpa sekat login.
   - Pindai teks broadcast WhatsApp/grup keluarga (Quick Check) dengan ekstraksi entitas lokasi instan.
2. **Agregasi & Validasi Geospasial $\le 500\text{ m}$ (Anti-Abuse)**:
   - Laporan dalam radius $<500\text{ m}$ otomatis digabung ke dalam satu titik bencana (*event cluster*).
   - Validasi formula Haversine untuk konfirmasi silang *"Masih Banjir"* dan penutupan kejadian oleh relawan wajib berada dalam radius maksimal 500 meter.
   - Sistem pencegahan spam IP dan manipulasi multi-window dengan cooldown terpadu.
3. **Safety-First Worst-Case Water Level**:
   - Eskalasi bahaya langsung naik saat terdeteksi air tinggi (`sepinggang_keatas`).
   - Penurunan level bahaya mewajibkan konsensus minimal 2 laporan warga berturut-turut untuk mencegah informasi palsu.
4. **Sistem Hak Akses SPA (Single Page Application)**:
   - Peta Leaflet berjalan terus-menerus tanpa *refresh* / kedip halaman.
   - Peran dinamis: **Guest** (Lapor instan), **Warga** (Konfirmasi silang), **Relawan** (Verifikasi dan penutupan status surut dengan tombol VIP lapangan), dan **Admin**.
5. **Transparansi Kesegaran Data**:
   - Status otomatis: `ACTIVE` (hijau/kuning/merah), `STALE` (abu-abu jika >2 jam tanpa pembaruan), dan `RESOLVED` (surut).

---

## 🛠️ Tech Stack & Arsitektur

- **Backend**: Laravel 12 / 13 (PHP 8.3), Laravel Sanctum (SPA Authentication), SQLite (Testing/Self-contained) & MySQL.
- **Frontend**: Laravel Blade, Tailwind CSS (Mobile-First Emergency Contrast), Alpine.js (Reactive SPA State Management), Leaflet.js (Peta Spasial Interaktif).
- **Pengujian Otomatis**: PHPUnit 12 (55 Feature Tests, 251 Assertions — 100% Passed).
- **Infrastruktur / Deployment**: Dockerfile, Nixpacks, Railway / Render Cloud Ready.

---

## 🤖 AI Use Declaration (Deklarasi Pemanfaatan AI)

Sesuai dengan ketentuan integritas akademik dan kompetisi **INCEPTION 2026** (PRD Bab 2 & Bab 20), tim menyatakan penggunaan kecerdasan buatan (*Artificial Intelligence*) secara jujur dan transparan:

| Komponen / Ranah | Kakas / Model AI | Peran & Ruang Lingkup Pemanfaatan | Validasi Manusia & Mitigasi Risiko |
| :--- | :--- | :--- | :--- |
| **Pair Programming & Code Assistant** | **Google Antigravity (Gemini 3.8 Flash & Gemini 2.5 Pro)** | Membantu penyusunan kerangka arsitektur, generasi pengujian otomatis (*automated unit/feature tests*), perumusan formula matematika Haversine geospasial, serta refactoring Alpine.js SPA state management. | **100% Manual Review**: Seluruh kode, logika bisnis, dan skema basis data diuji secara kritis, divalidasi dengan 55 automated tests, dan ditinjau langsung oleh tim pengembang. |
| **Ekstraksi Teks Quick Check** | **Large Language Model (LLM) API** | Ekstraksi entitas nama jalan, kelurahan, dan indikasi banjir dari teks mentah broadcast WhatsApp warga. | **Fallback Lokal 0-Milidetik**: Disediakan parser Regex PHP lokal di sisi server jika API mengalami latensi atau gangguan saat demo juri. |
| **Data Sintetis Pengujian** | **Gemini & FakerPHP** | Membantu pembuatan skenario seeder banjir Surabaya yang realistis untuk keperluan gladi bersih demonstrasi 7 menit. | Seluruh koordinat spasial diverifikasi agar tepat mencerminkan titik-titik rawan banjir historis Surabaya (Mayjen Sungkono, Raya Darmo, Dharmawangsa, Ketintang, Wiyung). |

---

## 📦 Third-Party Libraries & Data Declaration (Deklarasi Pihak Ketiga)

Seluruh pustaka dan aset eksternal yang digunakan dalam proyek ini bersifat *open-source* dan digunakan sesuai lisensi resminya:

### 1. Pustaka Perangkat Lunak (Open Source Software)
- **Laravel Framework & Laravel Sanctum** (Lisensi MIT) — Framework backend utama dan manajemen token autentikasi API.
- **Leaflet.js** (Lisensi BSD-2-Clause) — Pustaka peta interaktif berbasis JavaScript untuk visualisasi titik bencana dan radius geospasial.
- **Alpine.js** (Lisensi MIT) — Framework reaktivitas ringan di sisi browser untuk manajemen modal, drawer, dan sinkronisasi status login SPA.
- **Tailwind CSS** (Lisensi MIT) — Utility-first CSS framework untuk antarmuka *mobile-first* dengan kontras tinggi ramah darurat.
- **PHPUnit** (Lisensi BSD-3-Clause) — Pengujian regresi otomatis fitur backend dan geospasial.

### 2. Peta & Sumber Data Spasial
- **OpenStreetMap (OSM)** (Lisensi ODbL / Open Data Commons) — Sumber ubin peta (*tile layer*) yang dirender melalui Leaflet dengan atribusi penuh: `© OpenStreetMap contributors`.

### 3. Komparasi Etis & Orisinalitas
- **PetaBencana.id** dirujuk murni sebagai studi komparasi publik (*literature review*) untuk memetakan kelemahan agregasi dan stempel kesegaran waktu. **Tidak ada sebaris kode, script, maupun aset gambar dari repositori PetaBencana.id yang disalin ke proyek ini.**

---

## ⚠️ Pernyataan Simulasi Data (Demo Disclaimer)

> [!IMPORTANT]
> Seluruh data laporan banjir, nama warga pelapor, dan status genangan yang terpasang pada aplikasi ini merupakan **data uji coba sintetis untuk simulasi demonstrasi 7 menit dewan juri Hackathon INCEPTION 2026**.  
> Data ini dirancang untuk mendemonstrasikan kapabilitas sistem clustering spasial dan siklus hidup kejadian bencana (`ACTIVE` $\rightarrow$ `STALE` $\rightarrow$ `RESOLVED`), bukan merupakan kondisi faktual bencana di Kota Surabaya saat ini.

---

## 💻 Panduan Menjalankan Aplikasi Secara Lokal

### Prasyarat
- PHP $\ge$ 8.3 dengan ekstensi `pdo_sqlite`, `pdo_mysql`, `mbstring`, `fileinfo`.
- Composer $\ge$ 2.x
- Node.js $\ge$ 20.x & npm

### Langkah Instalasi
```bash
# 1. Clone repositori
git clone https://github.com/<username>/paniccheck.git
cd paniccheck

# 2. Pasang dependensi PHP dan Node.js
composer install
npm install

# 3. Salin file environment dan buat application key
cp .env.example .env
php artisan key:generate

# 4. Siapkan database SQLite dan jalankan seeder Surabaya
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --force

# 5. Hubungkan storage publik untuk unggahan foto
php artisan storage:link

# 6. Build aset frontend (Tailwind & Vite)
npm run build

# 7. Jalankan server lokal
php artisan serve
```
Akses aplikasi melalui peramban web di: `http://localhost:8000`

### Menjalankan Automated Test Suite
```bash
php artisan test
```
*Hasil pengujian saat ini: 55 passed, 251 assertions (100% Green).*

---

## 👥 Tim Pengembang (INCEPTION 2026)

- **Rilo** — Frontend Lead, Mobile-First UI/UX, Leaflet Maps, SPA State Management, Skenario Demo 7 Menit.
- **Michael** — Backend Lead, Database Architecture, Spatial Aggregation & Haversine Cooldown, API Sanctum, Cloud Deployment.

---
*Lisensi: Open-source di bawah lisensi [MIT License](LICENSE).*
