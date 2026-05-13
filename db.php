<?php
require_once __DIR__ . '/config.php';

/**
 * Conexión a MySQL local de Namecheap usando PDO
 */

function getDB() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // Cargamos los datos del .env (usaremos variables específicas para MySQL ahora)
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');

    if (!$dbname || !$user) {
        die("Error: Configuración de MySQL incompleta en el .env (DB_NAME, DB_USER, DB_PASS)");
    }

    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión a MySQL: " . $e->getMessage());
    }
}
