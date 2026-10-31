# Dokumentasi API PANICCHECK

**Base URL (Testing Lokal):** `http://<IP-KOMPUTER-BACKEND>:8000`
*(Contoh: http://192.168.10.127:8000)*

---

## 1. Ambil Data Titik Banjir (Untuk Peta)
**Endpoint:** `GET /api/events`
**Deskripsi:** Mengambil daftar pusat kejadian (event) bencana hasil *clustering* Haversine untuk dirender menjadi pin di peta Leaflet.
**Headers:** Tidak butuh token (Publik)

**Contoh Response (JSON):**
```json
[
  {
    "id": 1,
    "latitude": "-7.250445",
    "longitude": "112.768845",
    "status": "ACTIVE", // Bisa berisi: ACTIVE, STALE, atau RESOLVED[cite: 5]
    "created_at": "2026-10-31T08:00:00.000000Z",
    "reports": [
      {
        "id": 101,
        "water_level": "Selutut",
        "description": "Jalan tergenang parah",
        "photo_path": "reports/nama_file.jpg"
      }
    ]
  }
]

```

---

## 2. Kirim Laporan Baru

**Endpoint:** `POST /api/reports`
**Deskripsi:** Mengirim data laporan banjir baru dari warga.
**Headers:** Tidak butuh token (Publik)
**Aturan Penting:** **WAJIB** menggunakan `FormData` di frontend (bukan JSON), karena terdapat pengiriman file fisik.

**Payload (FormData):**

* `latitude` (String/Float) - Titik koordinat Y
* `longitude` (String/Float) - Titik koordinat X
* `category` (String) - Kategori bencana (contoh: "Banjir")
* `water_level` (String) - Tingkat genangan (contoh: "Mata Kaki", "Selutut")
* `description` (String) - Keterangan situasi
* `photo` (File) - File gambar (.jpg / .png)

---

## 3. Riwayat Laporan Pribadi

**Endpoint:** `GET /api/my-reports`
**Deskripsi:** Menarik riwayat laporan khusus milik pengguna yang sedang membuka aplikasi/login.
**Headers:**

* `Authorization: Bearer <token_sanctum>` *(Wajib disertakan di header fungsi fetch/axios)*

**Contoh Response (JSON):**

```json
[
  {
    "id": 102,
    "event_id": 2,
    "category": "Banjir",
    "water_level": "Mata Kaki",
    "status_event_saat_ini": "RESOLVED",
    "created_at": "2026-10-31T09:30:00.000000Z"
  }
]