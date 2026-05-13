<?php
require_once __DIR__ . '/config.php';

/**
 * Conexión a PostgreSQL en Render usando PDO
 */

function getDB() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $url = DB_URL;
    if (!$url) {
        die("Error: DATABASE_URL no configurada en el .env");
    }

    // Parsear la URL de Postgres (postgresql://user:pass@host:port/dbname)
    $db_parts = parse_url($url);
    
    $host = $db_parts['host'];
    $port = $db_parts['port'] ?? 5432;
    $user = $db_parts['user'];
    $pass = $db_parts['pass'];
    $dbname = ltrim($db_parts['path'], '/');

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }
}
