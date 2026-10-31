<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'ip_address',
        'latitude',
        'longitude',
        'category',
        'water_level',
        'photo_path',
        'description',
        'is_confirmation',
    ];

    // Relasi: Setiap laporan merujuk pada satu Event utama
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    // Relasi: Laporan ini milik user siapa (bisa null jika lapor tanpa login)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
