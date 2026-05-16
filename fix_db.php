<?php
require_once __DIR__ . '/db.php';

try {
    $pdo = getDB();
    echo "<h2>Reparando Base de Datos...</h2>";

    // 1. Asegurar columnas en 'leads'
    $pdo->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS resumen TEXT AFTER negocio");
    $pdo->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS solicitud TEXT AFTER resumen");
    $pdo->exec("ALTER TABLE leads ADD COLUMN IF NOT EXISTS qualification_status ENUM('nuevo', 'calificado', 'descalificado') DEFAULT 'nuevo' AFTER wa_id");
    echo "✅ Tabla 'leads' actualizada.<br>";

    // 2. Asegurar tabla 'knowledge_chunks'
    $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_chunks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_file VARCHAR(255),
        content TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FULLTEXT (content)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "✅ Tabla 'knowledge_chunks' lista.<br>";

    // 3. Limpiar logs antiguos para ver mejor los nuevos
    file_put_contents(__DIR__ . '/debug.log', "[" . date('Y-m-d H:i:s') . "] LOG REINICIADO TRAS REPARACIÓN\n");
    echo "✅ Log reiniciado.<br>";

    echo "<h2 style='color:green'>¡Reparación completada!</h2>";
    echo "<p>Prueba ahora a enviar un mensaje al bot.</p>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>Error en reparación:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
