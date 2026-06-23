<?php
require_once __DIR__ . '/db.php';

try {
    $pdo = getDB();

    // 1. Tabla de Mensajes
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        wa_id VARCHAR(50),
        role ENUM('user', 'assistant', 'system'),
        content TEXT,
        message_id VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Tabla de Leads (Prospectos)
    $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
        wa_id VARCHAR(50) PRIMARY KEY,
        qualification_status ENUM('nuevo', 'calificado', 'descalificado') DEFAULT 'nuevo',
        nombre VARCHAR(255),
        negocio VARCHAR(255),
        resumen TEXT,
        solicitud TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Tabla de Inventario
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255),
        description TEXT,
        price DECIMAL(10,2),
        stock INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. NUEVA: Tabla de Conocimiento (RAG Local)
    $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_chunks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_file VARCHAR(255),
        content TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FULLTEXT (content)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "<h2 style='color:green'>¡Base de datos de Salvix Wireless IA Agent (Versión RAG) lista con éxito!</h2>";
    echo "<p>Ya puedes cerrar esta pestaña y volver al panel de admin.</p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>Error al configurar la base de datos:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
