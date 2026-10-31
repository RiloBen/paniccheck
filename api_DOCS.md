# Dokumentasi API PANICCHECK

**Base URL (Testing Lokal):** `http://<IP-KOMPUTER-BACKEND>:8000`  
*(Contoh: http://127.0.0.1:8000 atau http://192.168.10.127:8000)*

---

## 1. Ambil Data Titik Banjir (Untuk Peta)
**Endpoint:** `GET /api/events`  
**Deskripsi:** Mengambil daftar pusat kejadian (event) bencana aktif & stale hasil *clustering* Haversine untuk dirender menjadi pin di peta Leaflet.  
**Headers:** Tidak butuh token (Publik)

**Contoh Response (JSON):**
```json
{
  "message": "Berhasil mengambil titik lokasi banjir",
  "data": [
    {
      "id": 1,
      "category": "Banjir",
      "latitude": "-7.28920000",
      "longitude": "112.71530000",
      "status": "ACTIVE",
      "created_at": "2026-10-31T08:00:00.000000Z",
      "updated_at": "2026-10-31T08:35:00.000000Z",
      "reports": [
        {
          "id": 101,
          "event_id": 1,
          "water_level": "sepinggang_keatas",
          "description": "Depan Ciputra World air sepinggang, motor mogok banyak.",
          "created_at": "2026-10-31T08:00:00.000000Z"
        }
      ]
    }
  ]
}
```

---

## 2. Kirim Laporan Darurat Baru (Warga Tanpa Login)
**Endpoint:** `POST /api/reports`  
**Deskripsi:** Mengirim data laporan banjir baru dari warga dengan otomatisasi clustering 500 meter / 2 jam.  
**Headers:** Tidak butuh token (Publik)  
**Aturan Penting:** **WAJIB** menggunakan `FormData` di frontend (bukan JSON), karena terdapat pengiriman file fisik foto.

**Payload (FormData):**
* `latitude` (Float/String, Required) - Titik koordinat Latitude
* `longitude` (Float/String, Required) - Titik koordinat Longitude
* `category` (String, Required) - Kategori bencana (default: "Banjir")
* `water_level` (String, Optional) - "semata_kaki", "selutut", atau "sepinggang_keatas"
* `description` (String, Optional) - Keterangan situasi di lapangan
* `photo` (File, Optional) - File gambar (.jpg, .jpeg, .png, maks 2MB)

**Contoh Response (JSON):**
```json
{
  "message": "Laporan berhasil diproses!",
  "event_id": 1,
  "photo_url": "http://127.0.0.1:8000/storage/reports/contoh_foto.jpg"
}
```

---

## 3. Konfirmasi Silang Warga ("Masih Banjir")
**Endpoint:** `POST /api/events/{id}/confirm`  
**Deskripsi:** Warga login mengonfirmasi bahwa genangan air di lokasi event masih berlangsung. Memperbarui sliding window event dan mereaktivasi status STALE kembali menjadi ACTIVE.  
**Headers:**
* `Authorization: Bearer <token_sanctum>` (Wajib)
* `Accept: application/json`

**Payload (JSON):**
* `description` (String, Optional) - Deskripsi kondisi terkini

**Contoh Response (JSON):**
```json
{
  "message": "Konfirmasi berhasil ditambahkan dan status diperbarui!"
}
```

---

## 4. Penutupan Kejadian / Surut (Relawan Terverifikasi & Admin)
**Endpoint:** `POST /api/events/{id}/resolve`  
**Deskripsi:** Menutup event dan mengubah status menjadi `RESOLVED` secara permanen. Hanya bisa diakses oleh akun dengan role `relawan` atau `admin`.  
**Headers:**
* `Authorization: Bearer <token_sanctum>` (Wajib)
* `Accept: application/json`

**Contoh Response (JSON):**
```json
{
  "message": "Event berhasil ditutup dan ditandai RESOLVED."
}
```

---

## 5. Riwayat Laporan Pribadi
**Endpoint:** `GET /api/my-reports`  
**Deskripsi:** Mengambil daftar laporan yang dikirimkan oleh pengguna yang sedang login.  
**Headers:**
* `Authorization: Bearer <token_sanctum>` (Wajib)
* `Accept: application/json`

**Contoh Response (JSON):**
```json
{
  "message": "Berhasil mengambil riwayat laporan pribadi",
  "data": [
    {
      "id": 102,
      "event_id": 1,
      "latitude": -7.2892,
      "longitude": 112.7153,
      "category": "Banjir",
      "water_level": "selutut",
      "photo_path": "reports/foto.jpg",
      "description": "Genangan di depan toko",
      "created_at": "2026-10-31T09:30:00.000000Z",
      "event": {
        "id": 1,
        "status": "ACTIVE"
      }
    }
  ]
}
```
