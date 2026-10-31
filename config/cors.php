<?php

return [
    /*
     * Tentukan path API mana saja yang pintunya boleh dibuka dari luar.
     * Kita buka semua rute yang memiliki awalan 'api/*'.
     */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
     * Izinkan semua metode HTTP yang dibutuhkan (GET, POST, PUT, DELETE).
     */
    'allowed_methods' => ['*'],

    /*
     * Buka pintu untuk semua asal domain atau IP. 
     * Ini kunci utama untuk pengujian multi-device.
     */
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    /*
     * Izinkan pengiriman semua jenis header (seperti Authorization untuk token Sanctum).
     */
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
     * Karena kita menggunakan token API Sanctum (bukan otentikasi SPA berbasis cookie),
     * false di sini sudah cukup dan aman untuk komunikasi.
     */
    'supports_credentials' => false,
];