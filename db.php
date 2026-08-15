<?php
require_once __DIR__ . '/config.php';

/**
 * Conexión a MySQL usando PDO.
 * - getDB(): usa el tenant activo ($GLOBALS['TENANT']) si existe; si no, la BD del .env.
 * - getBaseDB(): SIEMPRE la BD del .env (registro de tenants), ignore el contexto.
 */

function connectDB($host, $dbname, $user, $pass) {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        logger("ERROR de conexión a MySQL ($dbname): " . $e->getMessage());
        exit;
    }
}

function getBaseDB() {
    static $basePdo = null;
    if ($basePdo !== null) return $basePdo;

    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');

    if (!$dbname || !$user) {
        logger("ERROR: Configuración de MySQL incompleta en el .env (DB_NAME, DB_USER, DB_PASS)");
        exit;
    }

    $basePdo = connectDB($host, $dbname, $user, $pass);
    return $basePdo;
}

function getDB() {
    static $pdoCache = [];

    $tenant = $GLOBALS['TENANT'] ?? null;
    $key = $tenant ? 'tenant:' . $tenant['db_name'] : 'base';

    if (isset($pdoCache[$key])) return $pdoCache[$key];

    if ($tenant) {
        $pdo = connectDB(
            $tenant['db_host'] ?: 'localhost',
            $tenant['db_name'],
            $tenant['db_user'],
            $tenant['db_pass']
        );
    } else {
        $pdo = getBaseDB();
    }

    $pdoCache[$key] = $pdo;
    return $pdo;
}