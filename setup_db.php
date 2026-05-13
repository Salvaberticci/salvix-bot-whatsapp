<?php
require_once __DIR__ . '/db.php';

echo "<h2>Iniciando configuración de MySQL...</h2>";

try {
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
        qualification_status VARCHAR(20) DEFAULT 'en_progreso',
        disqualify_reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✅ Tabla 'leads' lista.<br>";

    echo "<br><strong>¡Todo listo! Ya puedes volver al <a href='admin.php'>Panel de Administración</a>.</strong>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
}
