<?php
require_once __DIR__ . '/db.php';

echo "<h2>Iniciando configuración de MySQL...</h2>";

try {
    // Intentar conectar sin base de datos primero para crearla
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $dbname = getenv('DB_NAME');
    
    $pdo_init = new PDO("mysql:host=$host", $user, $pass);
    $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "✅ Base de datos '$dbname' lista o ya existente.<br>";

    $pdo = getDB();

    // 1. Crear tabla de Mensajes (Sintaxis MySQL)
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        wa_id VARCHAR(50) NOT NULL,
        role VARCHAR(20) NOT NULL,
        content TEXT NOT NULL,
        message_id VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✅ Tabla 'messages' lista.<br>";

    // 2. Crear tabla de Leads (Sintaxis MySQL)
    $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
        wa_id VARCHAR(50) PRIMARY KEY,
        nombre VARCHAR(100),
        negocio VARCHAR(100),
        resumen TEXT,
        solicitud TEXT,
        qualification_status VARCHAR(20) DEFAULT 'en_progreso',
        disqualify_reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    // 3. Crear tabla de Inventario (Sintaxis MySQL)
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10, 2) DEFAULT 0.00,
        stock INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✅ Tabla 'inventory' lista.<br>";

    echo "<br><strong>¡Todo listo! Ya puedes volver al <a href='admin.php'>Panel de Administración</a>.</strong>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
