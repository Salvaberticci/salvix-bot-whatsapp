<?php
/**
 * Migrations Runner
 * 
 * Ejecuta todas las migraciones SQL pendientes en orden.
 * Accede via: https://tudominio.com/migrations/run_migrations.php
 * 
 * Cada migración se registra en la tabla `_migrations` para
 * ejecutarse una sola vez.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

echo "<!DOCTYPE html><html lang='es'><head><meta charset='utf-8'>";
echo "<title>Migrations Runner</title>";
echo "<style>
    body { font-family: 'Inter', system-ui, sans-serif; background: #000; color: #fff; padding: 40px; max-width: 800px; margin: 0 auto; }
    h1 { color: #38bdf8; margin-bottom: 8px; }
    .sub { color: #8a8a8a; margin-bottom: 32px; }
    .success { color: #4ade80; }
    .error { color: #ef4444; }
    .skip { color: #8a8a8a; }
    .item { padding: 8px 0; border-bottom: 1px solid #2a2a2a; }
    .summary { margin-top: 24px; padding: 16px; background: #0d0d0d; border-radius: 12px; border: 1px solid #2a2a2a; }
</style></head><body>";
echo "<h1>🔄 Migrations Runner</h1>";
echo "<p class='sub'>Ejecutando migraciones pendientes...</p>";

try {
    $pdo = getDB();

    // 1. Crear tabla de control de migrations si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS `_migrations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(255) NOT NULL,
        `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 2. Obtener migraciones ya ejecutadas
    $stmt = $pdo->query("SELECT migration FROM `_migrations` ORDER BY migration");
    $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 3. Escanear archivos .sql en la carpeta migrations/
    $files = glob(__DIR__ . '/*.sql');
    sort($files);

    if (empty($files)) {
        echo "<p>No hay archivos de migración (.sql) en la carpeta migrations/.</p>";
    } else {
        $countExecuted = 0;
        $countSkipped = 0;
        $countErrors = 0;

        foreach ($files as $filePath) {
            $filename = basename($filePath);

            if (in_array($filename, $executed)) {
                echo "<div class='item'><span class='skip'>⏭️ $filename — ya ejecutada</span></div>";
                $countSkipped++;
                continue;
            }

            $sql = file_get_contents($filePath);
            if (empty(trim($sql))) {
                echo "<div class='item'><span class='skip'>⏭️ $filename — vacía</span></div>";
                $countSkipped++;
                continue;
            }

            try {
                $pdo->exec($sql);
                $stmt = $pdo->prepare("INSERT INTO `_migrations` (migration) VALUES (?)");
                $stmt->execute([$filename]);
                echo "<div class='item'><span class='success'>✅ $filename — ejecutada con éxito</span></div>";
                $countExecuted++;
            } catch (PDOException $e) {
                echo "<div class='item'><span class='error'>❌ $filename — ERROR: " . htmlspecialchars($e->getMessage()) . "</span></div>";
                $countErrors++;
            }
        }

        echo "<div class='summary'>";
        echo "<strong>Resumen:</strong><br>";
        echo "✅ Ejecutadas: $countExecuted<br>";
        echo "⏭️ Omitidas: $countSkipped<br>";
        if ($countErrors > 0) echo "❌ Errores: $countErrors<br>";
        echo "</div>";
    }

} catch (PDOException $e) {
    echo "<div class='item'><span class='error'>❌ Error de conexión a la BD: " . htmlspecialchars($e->getMessage()) . "</span></div>";
}

echo "</body></html>";
