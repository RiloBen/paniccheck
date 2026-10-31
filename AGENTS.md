<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

# Project Coding & Collaboration Rules (PANICCHECK)

Patuhi aturan-aturan berikut secara ketat dalam setiap interaksi dan pengerjaan kode di project ini:

## 1. Pemahaman Menyeluruh & Keselarasan Struktur
- **Pahami Big Picture**: Sebelum fokus ke satu bagian atau membuat perubahan, pahami gambaran project secara keseluruhan (arsitektur sistem, konteks PRD, peran frontend/backend).
- **Konsistensi Struktur**: Pastikan setiap kode baru selaras dan nyambung dengan struktur direktori, konvensi penamaan, dan gaya kode yang sudah ada di repositori.
- **Konfirmasi Perubahan Pendekatan**: Jika ada kebutuhan untuk mengubah pendekatan/desain yang sebelumnya sudah dibuat, jelaskan alasannya secara transparan dan minta persetujuan user terlebih dahulu sebelum mengeksekusinya.

## 2. Solusi Komprehensif (Bukan Sekadar Snippet untuk Ditempel)
- **Alasan & Dampak**: Setiap memberikan kode atau solusi, selalu sertakan:
  1. Alasan kenapa memilih cara/teknologi tersebut.
  2. Pengaruhnya terhadap komponen lain di project.
  3. Alternatif lain yang sebenarnya bisa dipakai beserta alasan mengapa tidak dipilih.
- **Edukasi & Anatomi Kode**: Jelaskan fungsi tiap bagian penting dari kode, cara memakainya secara praktis, dan jebakan/kesalahan yang sering terjadi (*common pitfalls*).

## 3. Investigasi Masalah & Penanganan Bug (Root-Cause First)
- Jika menemukan bug atau error:
  1. Selidiki dan temukan **akar masalahnya** (*root cause*) terlebih dahulu.
  2. Jelaskan penyebab utama dan dampak yang ditimbulkannya.
  3. Berikan solusi perbaikan yang tuntas.
  4. Periksa dan verifikasi bagian lain di project agar tidak terjadi regresi (*ripple effect*) dan sistem tetap sinkron.

## 4. Kualitas Rekayasa Menyeluruh
- Dalam setiap keputusan teknis, selalu pertimbangkan pilar:
  - **Keamanan**: Validasi input, otorisasi peran, pencegahan injection/XSS.
  - **Kecepatan / Performa**: Efisiensi query, indexing, lazy load, CDN caching.
  - **Integritas Database**: Skema migrasi, transaksi, integritas relasi.
  - **Tampilan (UX/UI)**: Kemudahan pakai, kontras warna tinggi untuk darurat, aksesibilitas layar sentuh.
  - **Kerapian Kode**: Bersih, mudah dibaca, tanpa bloat/abstraksi berlebih.

## 5. Review Kode: Kritis & Edukatif
- Saat mereview kode (baik kode tim maupun kode yang ada), bersikaplah kritis terhadap potensi celah atau inefisiensi, namun selalu sertakan logika, alasan teknis, dan cara berpikir di baliknya agar menjadi proses belajar bersama.

## 6. Ringkasan Progres Berkala
- Jika sesi percakapan sudah berlangsung cukup panjang atau menyelesaikan milestone besar, sediakan ringkasan progress terstruktur yang mencakup:
  1. Yang sudah selesai
  2. Yang sedang dikerjakan
  3. Masalah yang belum selesai
  4. Keputusan penting
  5. Langkah berikutnya

## 7. Pengingat Uji Coba (Safety Stop)
- Setiap kali memberikan kode atau instruksi modifikasi file, ingatkan user untuk **menyimpan (save) dan menjalankan / menguji kodenya terlebih dahulu** sebelum melangkah ke tahap selanjutnya.
