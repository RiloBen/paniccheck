<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Daftarkan fungsi trigonometri pada SQLite agar rumus Haversine berjalan lancar
        try {
            \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\ConnectionEstablished::class, function ($event) {
                if ($event->connection->getDriverName() === 'sqlite') {
                    $pdo = $event->connection->getPdo();
                    if ($pdo instanceof \PDO) {
                        $pdo->sqliteCreateFunction('acos', 'acos', 1);
                        $pdo->sqliteCreateFunction('cos', 'cos', 1);
                        $pdo->sqliteCreateFunction('sin', 'sin', 1);
                        $pdo->sqliteCreateFunction('radians', 'deg2rad', 1);
                    }
                }
            });
        } catch (\Throwable $e) {
            // Abaikan jika bukan PDO SQLite
        }
    }
}
