<?php
require_once __DIR__ . '/db.php';

/**
 * Registro central de clientes (tenants) multi-tenant.
 * Cada cliente tiene su propia base de datos, prompt, knowledge y panel.
 * La tabla `tenants` vive en la BD base (la del .env).
 */

function getAllTenants() {
    try {
        $pdo = getBaseDB();
        return $pdo->query("SELECT * FROM tenants ORDER BY nombre ASC")->fetchAll();
    } catch (Exception $e) {
        logger("ERROR listando tenants (¿tabla no creada?): " . $e->getMessage());
        return [];
    }
}

function getTenantByPhoneId($phoneNumberId) {
    if (!$phoneNumberId) return null;
    $pdo = getBaseDB();
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE phone_number_id = ?");
    $stmt->execute([$phoneNumberId]);
    $tenant = $stmt->fetch();
    return $tenant ?: null;
}

function getTenantByIgAccountId($igAccountId) {
    if (!$igAccountId) return null;
    $pdo = getBaseDB();
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE ig_account_id = ?");
    $stmt->execute([$igAccountId]);
    $tenant = $stmt->fetch();
    return $tenant ?: null;
}

function getTenantBySlug($slug) {
    if (!$slug) return null;
    $pdo = getBaseDB();
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE slug = ?");
    $stmt->execute([$slug]);
    $tenant = $stmt->fetch();
    return $tenant ?: null;
}

/**
 * Esquema completo de una BD de tenant (y de la BD base).
 */
function installTenantSchema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        wa_id VARCHAR(50),
        role ENUM('user', 'assistant', 'system'),
        content TEXT,
        image_data LONGTEXT,
        message_id VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
        wa_id VARCHAR(50) PRIMARY KEY,
        qualification_status ENUM('nuevo', 'calificado', 'descalificado') DEFAULT 'nuevo',
        disqualify_reason TEXT,
        nombre VARCHAR(255),
        negocio VARCHAR(255),
        resumen TEXT,
        solicitud TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255),
        description TEXT,
        category VARCHAR(100) DEFAULT NULL,
        price DECIMAL(10,2),
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_chunks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_file VARCHAR(255),
        content TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FULLTEXT (content)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(100) PRIMARY KEY,
        `value` TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(20) NOT NULL,
        wa_id VARCHAR(50) NOT NULL,
        contact_phone VARCHAR(50) DEFAULT NULL,
        items TEXT,
        type ENUM('delivery','pickup','sala') DEFAULT 'delivery',
        delivery_address TEXT,
        delivery_zone VARCHAR(100),
        delivery_cost DECIMAL(10,2) DEFAULT 0.00,
        total DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('nuevo','aprobado','en_verificacion','pagado','en_camino','entregado','cancelado') DEFAULT 'nuevo',
        payment_method VARCHAR(50),
        payment_ref VARCHAR(150),
        admin_note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY (wa_id),
        KEY (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Columna contact_phone para BD ya creadas (idempotente).
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN contact_phone VARCHAR(50) DEFAULT NULL AFTER wa_id");
    } catch (Exception $e) {
        // Ya existe: ignorar
    }

    // Columna delivery_cost para BD ya creadas (idempotente).
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_cost DECIMAL(10,2) DEFAULT 0.00 AFTER delivery_zone");
    } catch (Exception $e) {
        // Ya existe: ignorar
    }

    // Comprobantes de pago adjuntos (imagen base64 + análisis IA) para BD ya creadas.
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_image LONGTEXT DEFAULT NULL AFTER payment_ref");
    } catch (Exception $e) {
        // Ya existe: ignorar
    }
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_analysis TEXT DEFAULT NULL AFTER payment_image");
    } catch (Exception $e) {
        // Ya existe: ignorar
    }

    // Imágenes enviadas por el cliente visibles en el chat del dashboard.
    try {
        $pdo->exec("ALTER TABLE messages ADD COLUMN image_data LONGTEXT DEFAULT NULL AFTER content");
    } catch (Exception $e) {
        // Ya existe: ignorar
    }
}

/**
 * Tabla `tenants` en la BD base.
 */
function installBaseSchema($pdo) {
    $pdo->exec(file_get_contents(__DIR__ . '/migrations/002_tenants_table.sql'));
}

/**
 * Crea la BD del cliente, instala el esquema, siembra el prompt por defecto
 * y crea la carpeta knowledge/<slug>/.
 * Devuelve el config resuelto (con db_name real) o lanza excepción.
 */
function installTenant($cfg) {
    $slug = trim($cfg['slug']);
    if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
        throw new Exception("El slug solo puede contener letras minúsculas, números y guiones bajos.");
    }

    $dbName = trim($cfg['db_name'] ?? '');
    if (!$dbName) {
        $dbName = 'salvix_' . $slug;
    }

    $host = trim($cfg['db_host'] ?? '') ?: 'localhost';
    $user = trim($cfg['db_user'] ?? '');
    $pass = $cfg['db_pass'] ?? '';
    if (!$user) {
        throw new Exception("El usuario de la base de datos es obligatorio.");
    }

    $pdoOpts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    // 1. Crear la base de datos (requiere privilegios de CREATE).
    // Si el usuario no tiene permisos pero la BD YA existe (pre-creada via cPanel),
    // continuamos; si no existe y no puede crearse, lanzamos error.
    $base = getBaseDB();
    try {
        $base->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    } catch (Exception $e) {
        $exists = $base->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = " . $base->quote($dbName))->fetchColumn();
        if (!$exists) {
            throw new Exception("No se pudo crear la base de datos '$dbName' y no existe pre-creada. Crealas desde cPanel (MySQL Databases) o via API y reintenta. Detalle: " . $e->getMessage());
        }
        logger("BD '$dbName' ya existia (sin permiso de CREATE). Continuando.");
    }

    // 2. Conectar y crear esquema
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass, $pdoOpts);
    installTenantSchema($pdo);

    // 3. Sembrar el prompt por defecto si la BD no tiene uno
    $prompt = $pdo->query("SELECT COUNT(*) FROM settings WHERE `key` = 'system_prompt'")->fetchColumn();
    if (!$prompt) {
        $seed = @file_get_contents(__DIR__ . '/prompts/system.example.md');
        if ($seed) {
            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES ('system_prompt', ?)");
            $stmt->execute([$seed]);
        }
    }

    // 3.1 Sembrar configuración de pedidos si no existe
    foreach (['order_prefix' => 'P', 'payment_methods' => '{}'] as $k => $v) {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE `key` = ?");
        $exists->execute([$k]);
        if (!$exists->fetchColumn()) {
            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?)");
            $stmt->execute([$k, $v]);
        }
    }

    // 4. Carpeta de conocimiento del tenant
    $dir = __DIR__ . '/knowledge/' . $slug;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!file_exists($dir . '/.htaccess') && file_exists(__DIR__ . '/knowledge/.htaccess')) {
        copy(__DIR__ . '/knowledge/.htaccess', $dir . '/.htaccess');
    }

    return [
        'slug'         => $slug,
        'nombre'       => trim($cfg['nombre'] ?? $slug),
        'phone_number_id' => trim($cfg['phone_number_id'] ?? ''),
        'waba_id'      => trim($cfg['waba_id'] ?? ''),
        'db_host'      => $host,
        'db_name'      => $dbName,
        'db_user'      => $user,
        'db_pass'      => $pass,
        'admin_user'   => trim($cfg['admin_user'] ?? 'admin'),
        'admin_pass'   => $cfg['admin_pass'] ?? '',
        'cta_url'      => trim($cfg['cta_url'] ?? ''),
        'wa_token'     => trim($cfg['wa_token'] ?? ''),
    ];
}