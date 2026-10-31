<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    // Mengizinkan kolom ini diisi data secara otomatis
    protected $fillable = [
        'category',
        'latitude',
        'longitude',
        'status',
    ];

    // Relasi: Satu Event (kejadian banjir) bisa memiliki banyak Laporan warga
    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}
