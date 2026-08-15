<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tenants.php';

try {
    $pdo = getDB();

    // 1. Esquema completo (mensajes, leads, inventario, knowledge, settings)
    installTenantSchema($pdo);

    // 2. Registro central de clientes (multi-tenant)
    installBaseSchema($pdo);

    echo "<h2 style='color:green'>¡Base de datos de Salvix lista con éxito!</h2>";
    echo "<p>Ya puedes cerrar esta pestaña y volver al panel de admin.</p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>Error al configurar la base de datos:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}