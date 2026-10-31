<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use PDO;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootSqliteMathFunctions();
    }

    /**
     * Daftarkan fungsi matematika (acos, cos, sin, radians) ke driver PDO SQLite
     * agar query Haversine Spatial Clustering dapat berjalan di database testing in-memory.
     */
    protected function bootSqliteMathFunctions(): void
    {
        try {
            $connection = DB::connection();
            if ($connection->getDriverName() === 'sqlite') {
                /** @var PDO $pdo */
                $pdo = $connection->getPdo();
                $pdo->sqliteCreateFunction('acos', 'acos', 1);
                $pdo->sqliteCreateFunction('cos', 'cos', 1);
                $pdo->sqliteCreateFunction('sin', 'sin', 1);
                $pdo->sqliteCreateFunction('radians', 'deg2rad', 1);
            }
        } catch (\Throwable $e) {
            // Diamkan jika driver bukan sqlite atau koneksi lazy
        }
    }
}
