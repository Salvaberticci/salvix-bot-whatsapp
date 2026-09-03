<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/knowledge.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/tenants.php';
require_once __DIR__ . '/orders.php';
session_start();

// MULTI-TENANT: si hay ?tenant=slug, el panel opera sobre ese cliente.
// Sin parámetro, es el panel base / super admin.
$tenantSlug = $_GET['tenant'] ?? '';
$tenant = $tenantSlug ? getTenantBySlug($tenantSlug) : null;
if ($tenant) {
    $GLOBALS['TENANT'] = $tenant;
}
$sessionKey = $tenant ? 'admin_' . $tenant['slug'] : 'admin';
$isTenantAdmin = $tenant !== null;

// Prefijos para enlaces que conservan el tenant activo (evita logout al navegar)
$tenantQs = $tenant ? '?tenant=' . urlencode($tenant['slug']) : '';
$viewQs = $tenant ? '?tenant=' . urlencode($tenant['slug']) . '&' : '?';

// 1. Autenticación Simple
if (isset($_GET['logout'])) {
    session_destroy();
    $qs = $tenantSlug ? "?tenant=$tenantSlug" : '';
    header("Location: admin.php$qs");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $ok = false;
    if ($tenant) {
        $ok = ($_POST['username'] === $tenant['admin_user'] && $_POST['password'] === $tenant['admin_pass']);
    } else {
        $ok = ($_POST['username'] === ($_ENV['ADMIN_USER'] ?? getenv('ADMIN_USER')) && $_POST['password'] === ($_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD')));
    }
    if ($ok) {
        $_SESSION[$sessionKey] = true;
    } else {
        $error = "Credenciales incorrectas";
    }
}

$tenantsList = getAllTenants();

if (!isset($_SESSION[$sessionKey])) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <title>Salvix Admin - Login</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Inter', system-ui, sans-serif;
                background: #000000;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                overflow: hidden;
            }
            body::before {
                content: '';
                position: absolute;
                width: 600px;
                height: 600px;
                background: radial-gradient(circle, rgba(209, 36, 36, 0.08) 0%, transparent 70%);
                top: -200px;
                right: -200px;
                pointer-events: none;
            }
            body::after {
                content: '';
                position: absolute;
                width: 500px;
                height: 500px;
                background: radial-gradient(circle, rgba(138, 138, 138, 0.04) 0%, transparent 70%);
                bottom: -200px;
                left: -200px;
                pointer-events: none;
            }
            .login-container {
                position: relative;
                z-index: 1;
                width: 100%;
                max-width: 420px;
                padding: 20px;
            }
            .login-header {
                text-align: center;
                margin-bottom: 32px;
            }
            .login-logo {
                width: 280px;
                height: 280px;
                margin: 0 auto 16px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-header h1 {
                color: #FFFFFF;
                font-size: 22px;
                font-weight: 600;
                letter-spacing: -0.3px;
            }
            .login-header p {
                color: #8A8A8A;
                font-size: 14px;
                margin-top: 6px;
            }
            .login-card {
                background: rgba(13, 13, 13, 0.9);
                backdrop-filter: blur(20px);
                border: 1px solid rgba(42, 42, 42, 0.6);
                border-radius: 20px;
                padding: 32px;
                box-shadow: 0 24px 80px rgba(0, 0, 0, 0.4);
            }
            .login-error {
                background: rgba(209, 36, 36, 0.1);
                border: 1px solid rgba(209, 36, 36, 0.2);
                color: #fecaca;
                padding: 12px 16px;
                border-radius: 10px;
                font-size: 13px;
                margin-bottom: 20px;
                text-align: center;
            }
            .form-group {
                margin-bottom: 20px;
            }
            .form-group label {
                display: block;
                color: #8A8A8A;
                font-size: 12px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 8px;
            }
            .form-group input {
                width: 100%;
                padding: 14px 16px;
                background: rgba(0, 0, 0, 0.6);
                border: 1px solid #2a2a2a;
                border-radius: 12px;
                color: #FFFFFF;
                font-size: 15px;
                font-family: 'Inter', sans-serif;
                transition: all 0.2s ease;
                outline: none;
            }
            .form-group input:focus {
                border-color: #D12424;
                box-shadow: 0 0 0 3px rgba(209, 36, 36, 0.15);
            }
            .form-group input::placeholder {
                color: #555555;
            }
            .login-btn {
                width: 100%;
                padding: 14px;
                background: linear-gradient(135deg, #D12424, #E03030);
                border: none;
                border-radius: 12px;
                color: #FFFFFF;
                font-family: 'Inter', sans-serif;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .login-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 8px 24px rgba(209, 36, 36, 0.3);
            }
            .login-btn:active {
                transform: translateY(0);
            }
            @media (max-width: 480px) {
                .login-card { padding: 24px; }
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo"><img src="img/logo.png" alt="Salvix" style="width:100%;height:100%;object-fit:contain;"></div>
                <h1>Salvix Admin</h1>
                <p>Panel de control del bot</p>
            </div>
            <div class="login-card">
                <?php if(isset($error)): ?>
                    <div class="login-error"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if($tenant): ?>
                    <div class="login-error" style="margin-bottom:20px;">
                        Accediendo al panel de: <strong><?php echo htmlspecialchars($tenant['nombre']); ?></strong>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="username">Usuario</label>
                        <input type="text" id="username" name="username" placeholder="Ingresa tu usuario" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" placeholder="Ingresa tu contraseña" required>
                    </div>
                    <button type="submit" name="login" class="login-btn">Entrar al panel</button>
                </form>
                <?php if(!$tenant && !empty($tenantsList)): ?>
                    <div style="margin-top:24px; padding-top:20px; border-top:1px solid rgba(42,42,42,0.6);">
                        <p style="color:#8A8A8A; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; font-weight:600; margin-bottom:12px;">Acceso por cliente</p>
                        <?php foreach($tenantsList as $t): ?>
                            <a href="admin.php?tenant=<?php echo urlencode($t['slug']); ?>" class="login-btn" style="display:block; text-align:center; margin-bottom:8px; font-size:13px; padding:11px; text-decoration:none;">
                                <?php echo htmlspecialchars($t['nombre']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 2. Lógica de Guardado de Instrucciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $newPrompt = $_POST['system_prompt'] ?? '';
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES ('system_prompt', ?) ON DUPLICATE KEY UPDATE `value` = ?");
    $stmt->execute([$newPrompt, $newPrompt]);
    $success_msg = "Instrucciones actualizadas con éxito.";
}

// 2.0 Lógica de Auto-Prompt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_prompt'])) {
    $info = $_POST['company_info'] ?? '';
    if (!empty($info)) {
        $url = GROQ_BASE_URL . '/chat/completions';
        $metaPrompt = "Eres un Experto Prompt Engineer. Escribe un 'System Prompt' excelente para un Bot de WhatsApp de ventas/atención al cliente, basado en esta info:
        
        Información de la empresa: $info
        
        Reglas para el prompt:
        1. Debe ser claro y directo.
        2. Define el rol del bot (ej: Eres el asistente virtual de...).
        3. Instrucciones sobre qué hacer si no sabe la respuesta (ofrecer contacto humano).
        4. Debe incluir el marcador [[ACTION_LINK]] cuando el usuario demuestre alta intención de compra o quiera agendar.
        5. Debe incluir el marcador [[DESCALIFICADO]] si el usuario dice no tener presupuesto o no le interesa.
        
        Responde ÚNICAMENTE con el texto del prompt final, sin introducciones ni comentarios.";
        
        $payload = [
            'model' => GROQ_MODEL,
            'messages' => [['role' => 'user', 'content' => $metaPrompt]],
            'temperature' => 0.5
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        $generatedPrompt = $data['choices'][0]['message']['content'] ?? '';
        
        if ($generatedPrompt) {
            $pdo = getDB();
            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES ('system_prompt', ?) ON DUPLICATE KEY UPDATE `value` = ?");
            $stmt->execute([trim($generatedPrompt), trim($generatedPrompt)]);
            $success_msg = "Instrucciones generadas con IA correctamente.";
        } else {
            $error_msg = "No se pudo generar el prompt. Revisa los logs.";
        }
    }
}

// 2.1 Lógica de Guardado de APIs (.env) — solo super admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_api']) && !$tenant) {
    $envPath = __DIR__ . '/.env';
    $envContent = file_get_contents($envPath);
    
    $keysToUpdate = [
        'WHATSAPP_API_TOKEN' => $_POST['wa_token'],
        'WHATSAPP_PHONE_NUMBER_ID' => $_POST['wa_phone_id'],
        'GROQ_API_KEY' => $_POST['groq_key'],
        'GROQ_MODEL' => $_POST['text_model']
    ];

    foreach ($keysToUpdate as $key => $value) {
        $pattern = "/^" . preg_quote($key) . "=.*/m";
        $replacement = $key . "=" . $value;
        if (preg_match($pattern, $envContent)) {
            $envContent = preg_replace($pattern, $replacement, $envContent);
        } else {
            $envContent .= "\n" . $replacement;
        }
    }

    // Limpiar variables viejas de OpenAI para evitar confusión
    foreach (['OPENAI_API_KEY', 'OPENAI_MODEL', 'OPENAI_BASE_URL'] as $oldKey) {
        $envContent = preg_replace("/^" . preg_quote($oldKey) . "=.*\n/m", "", $envContent);
    }
    
    file_put_contents($envPath, $envContent);
    $success_msg = "Credenciales de API actualizadas.";
}

// 2.2 Lógica de Archivos de Conocimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    $target_dir = knowledgeDir() . '/';
    $target_file = $target_dir . basename($_FILES["knowledge_file"]["name"]);
    $fileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
    
    if($fileType == "txt" || $fileType == "csv" || $fileType == "md" || $fileType == "docx") {
        if (move_uploaded_file($_FILES["knowledge_file"]["tmp_name"], $target_file)) {
            $success_msg = "Archivo subido correctamente.";
        } else {
            $error_msg = "Hubo un error subiendo tu archivo.";
        }
    } else {
        $error_msg = "Solo se permiten archivos TXT, CSV o MD.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $file = basename($_POST['file_name']);
    @unlink(knowledgeDir() . '/' . $file);
    $success_msg = "Archivo eliminado.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_knowledge'])) {
    $chunks = indexKnowledge();
    $success_msg = "Cerebro sincronizado. Se han creado $chunks fragmentos de conocimiento.";
}

// 2.3 Lógica de Menú
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_inventory'])) {
    $item_name = $_POST['item_name'];
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? '';
    $price = $_POST['price'] ?? 0;
    $active = isset($_POST['active']) ? 1 : 0;
    
    $pdo = getDB();
    if (!empty($_POST['item_id'])) {
        $stmt = $pdo->prepare("UPDATE inventory SET item_name=?, description=?, category=?, price=?, active=? WHERE id=?");
        $stmt->execute([$item_name, $description, $category, $price, $active, $_POST['item_id']]);
        $success_msg = "Producto actualizado.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO inventory (item_name, description, category, price, active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$item_name, $description, $category, $price, $active]);
        $success_msg = "Producto añadido al menú.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_inventory'])) {
    $id = $_POST['item_id'];
    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE id=?");
    $stmt->execute([$id]);
    $success_msg = "Producto eliminado.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_inventory_active'])) {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE inventory SET active = NOT active WHERE id = ?");
    $stmt->execute([$_POST['item_id']]);
    $success_msg = "Estado del producto actualizado.";
}

// 2.35 Lógica de Pedidos (restaurante)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment_methods'])) {
    $methods = [
        'pago_movil' => [
            'banco'     => trim($_POST['pm_banco'] ?? ''),
            'telefono'  => trim($_POST['pm_telefono'] ?? ''),
            'documento' => trim($_POST['pm_documento'] ?? ''),
            'titular'   => trim($_POST['pm_titular'] ?? ''),
        ],
        'transferencia' => [
            'banco'   => trim($_POST['tr_banco'] ?? ''),
            'cuenta'  => trim($_POST['tr_cuenta'] ?? ''),
            'titular' => trim($_POST['tr_titular'] ?? ''),
        ],
    ];
    savePaymentMethods(getDB(), $methods);
    $success_msg = "Métodos de pago actualizados. El sistema los mostrará al cliente al aprobar pedidos.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_order'])) {
    try {
        $success_msg = approveOrder(getDB(), (int)$_POST['order_id'], $_POST['delivery_cost'] ?? 0, (isset($_POST['order_total']) && trim($_POST['order_total']) !== '') ? $_POST['order_total'] : null);
    } catch (Exception $e) {
        $error_msg = "Error al aprobar: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_paid'])) {
    try {
        $success_msg = confirmOrderPaid(getDB(), (int)$_POST['order_id'], trim($_POST['payment_method']));
    } catch (Exception $e) {
        $error_msg = "Error al confirmar pago: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_payment'])) {
    try {
        $success_msg = rejectPayment(getDB(), (int)$_POST['order_id']);
    } catch (Exception $e) {
        $error_msg = "Error al rechazar comprobante: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_on_way'])) {
    try {
        $success_msg = setOrderOnWay(getDB(), (int)$_POST['order_id']);
    } catch (Exception $e) {
        $error_msg = "Error al marcar en camino: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_delivered'])) {
    try {
        $success_msg = setOrderDelivered(getDB(), (int)$_POST['order_id']);
    } catch (Exception $e) {
        $error_msg = "Error al marcar entregado: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    try {
        $success_msg = cancelOrder(getDB(), (int)$_POST['order_id']);
    } catch (Exception $e) {
        $error_msg = "Error al cancelar: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    try {
        $success_msg = deleteOrder(getDB(), (int)$_POST['order_id']);
    } catch (Exception $e) {
        $error_msg = "Error al eliminar: " . $e->getMessage();
    }
}

// 2.4 Lógica de Limpieza de Conversación (reset para que el bot atienda como primera vez)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_chat'])) {
    try {
        $chatId = trim($_POST['chat_id'] ?? '');
        if ($chatId === '') throw new Exception("Falta el número del chat.");
        $pdo = getDB();
        $del = $pdo->prepare("DELETE FROM messages WHERE wa_id = ?");
        $del->execute([$chatId]);
        $delO = $pdo->prepare("DELETE FROM orders WHERE wa_id = ? AND status = 'nuevo'");
        $delO->execute([$chatId]);
        $success_msg = "Conversación de $chatId limpiada (" . $del->rowCount() . " mensajes, " . $delO->rowCount() . " pedido(s) nuevo(s) eliminado(s)). El bot la atenderá como primera vez.";
    } catch (Exception $e) {
        $error_msg = "Error al limpiar: " . $e->getMessage();
    }
}

// 2.5 Lógica de Envío de Respuesta Manual desde Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $chatId = $_POST['chat_id'] ?? '';
    $replyText = trim($_POST['reply_text'] ?? '');
    if ($chatId && $replyText) {
        $pdo = getDB();
        $sent = sendWhatsAppText($chatId, $replyText);
        if ($sent) {
            $stmt = $pdo->prepare("INSERT INTO messages (wa_id, role, content) VALUES (?, 'assistant', ?)");
            $stmt->execute([$chatId, $replyText]);
            $success_msg = "Respuesta enviada a $chatId";
        } else {
            $error_msg = "Error al enviar el mensaje. Revisa los logs.";
        }
    }
}

// 2.5 Lógica de Eliminar Conversación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_chat'])) {
    $chatId = $_POST['chat_id'] ?? '';
    if ($chatId) {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM messages WHERE wa_id = ?");
        $stmt->execute([$chatId]);
        $success_msg = "Conversación con $chatId eliminada.";
    }
}

// 2.6 Lógica de Clientes (solo super admin, panel base)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tenant']) && !$tenant) {
    try {
        $cfg = [
            'slug'           => trim($_POST['tenant_slug'] ?? ''),
            'nombre'         => trim($_POST['tenant_nombre'] ?? ''),
            'phone_number_id'=> trim($_POST['tenant_phone_id'] ?? ''),
            'waba_id'        => trim($_POST['tenant_waba_id'] ?? ''),
            'db_host'        => trim($_POST['tenant_db_host'] ?? '') ?: 'localhost',
            'db_name'        => trim($_POST['tenant_db_name'] ?? ''),
            'db_user'        => trim($_POST['tenant_db_user'] ?? ''),
            'db_pass'        => $_POST['tenant_db_pass'] ?? '',
            'admin_user'     => trim($_POST['tenant_admin_user'] ?? ''),
            'admin_pass'     => $_POST['tenant_admin_pass'] ?? '',
            'cta_url'        => trim($_POST['tenant_cta_url'] ?? ''),
            'wa_token'       => trim($_POST['tenant_wa_token'] ?? ''),
        ];
        if (!$cfg['slug'] || !$cfg['phone_number_id']) {
            throw new Exception("El slug y el phone_number_id son obligatorios.");
        }
        $resolved = installTenant($cfg);
        $pdo = getBaseDB();
        $stmt = $pdo->prepare("INSERT INTO tenants (slug, nombre, phone_number_id, waba_id, db_host, db_name, db_user, db_pass, admin_user, admin_pass, cta_url, wa_token)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), phone_number_id = VALUES(phone_number_id), waba_id = VALUES(waba_id), db_host = VALUES(db_host), db_name = VALUES(db_name), db_user = VALUES(db_user), db_pass = VALUES(db_pass), admin_user = VALUES(admin_user), admin_pass = VALUES(admin_pass), cta_url = VALUES(cta_url), wa_token = VALUES(wa_token)");
        $stmt->execute([$resolved['slug'], $resolved['nombre'], $resolved['phone_number_id'], $resolved['waba_id'], $resolved['db_host'], $resolved['db_name'], $resolved['db_user'], $resolved['db_pass'], $resolved['admin_user'], $resolved['admin_pass'], $resolved['cta_url'], $resolved['wa_token']]);
        $success_msg = "Cliente '{$resolved['nombre']}' registrado (BD: {$resolved['db_name']}).";
    } catch (Exception $e) {
        $error_msg = "Error al crear el cliente: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tenant']) && !$tenant) {
    $slug = trim($_POST['tenant_slug'] ?? '');
    if ($slug) {
        $pdo = getBaseDB();
        $stmt = $pdo->prepare("DELETE FROM tenants WHERE slug = ?");
        $stmt->execute([$slug]);
        $dir = __DIR__ . '/knowledge/' . $slug;
        if (is_dir($dir)) {
            array_map('unlink', glob($dir . '/*'));
            @rmdir($dir);
        }
        $success_msg = "Cliente '$slug' eliminado del registro (la base de datos se conserva).";
    }
}

// 3. Lógica del Dashboard
$pdo = getDB();
$stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'system_prompt'");
$stmt->execute();
$prompt_content = $stmt->fetchColumn() ?: @file_get_contents(__DIR__ . '/prompts/system.example.md') ?: "";

// Contar métricas
$totalMsgs = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$totalOrders = 0;
$pendingOrders = 0;
try {
    $totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('nuevo','aprobado','en_verificacion')")->fetchColumn();
} catch (Exception $e) {}
$totalNewOrders = 0;
try {
    $totalNewOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'nuevo'")->fetchColumn();
} catch (Exception $e) {}

// Listar hilos de conversación
$threads = $pdo->query("SELECT wa_id, MAX(created_at) as last_msg FROM messages GROUP BY wa_id ORDER BY last_msg DESC LIMIT 50")->fetchAll();

$currentView = $_GET['view'] ?? 'dashboard';

// Los clientes (tenants) no pueden ver la configuración global de APIs
if ($tenant && $currentView === 'api') {
    $currentView = 'dashboard';
}
if (!$tenant && $currentView === 'clientes') {
    // Sin más validación: vista de gestión de clientes
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Salvix Admin</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #000000;
            --surface: #0d0d0d;
            --surface-2: #141414;
            --surface-3: #1a1a1a;
            --border: #2a2a2a;
            --border-light: #3a3a3a;
            --accent: #D12424;
            --accent-hover: #E03030;
            --accent-muted: rgba(209, 36, 36, 0.12);
            --text: #FFFFFF;
            --text-2: #CCCCCC;
            --text-3: #8A8A8A;
            --text-4: #555555;
            --danger: #D12424;
            --success: #4ade80;
            --info: #8A8A8A;
            --sidebar-width: 260px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 100;
            transition: transform 0.3s ease;
        }
        .sidebar-brand {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border);
        }
        .sidebar-logo {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            border-radius: 12px;
            overflow: hidden;
        }
        .sidebar-brand-text h2 {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .sidebar-brand-text span {
            font-size: 11px;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }
        .sidebar-section {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-4);
            padding: 16px 12px 8px;
            font-weight: 600;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            color: var(--text-2);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s ease;
            margin-bottom: 2px;
        }
        .nav-item:hover {
            background: var(--surface-2);
            color: var(--text);
        }
        .nav-item.active {
            background: var(--accent-muted);
            color: var(--accent);
        }
        .nav-item .nav-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            opacity: 0.7;
        }
        .nav-item.active .nav-icon { opacity: 1; }
        .nav-item .nav-badge {
            margin-left: auto;
            background: var(--accent-muted);
            color: var(--accent);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 999px;
        }
        .sidebar-footer {
            padding: 16px 12px;
            border-top: 1px solid var(--border);
        }

        /* ===== MAIN ===== */
        .main {
            margin-left: var(--sidebar-width);
            flex: 1;
            min-height: 100vh;
        }
        .main-header {
            padding: 24px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .main-header h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .main-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .main-content {
            padding: 32px;
        }

        /* ===== COMPONENTS ===== */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            transition: border-color 0.2s ease;
        }
        .card:hover {
            border-color: var(--border-light);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .card-header h3 {
            font-size: 15px;
            font-weight: 600;
        }
        .card-header .label {
            font-size: 12px;
            color: var(--text-3);
        }
        .card-glow {
            border-color: rgba(209, 36, 36, 0.2);
            box-shadow: 0 0 40px rgba(209, 36, 36, 0.05);
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .kpi-card:hover::before { opacity: 1; }
        .kpi-card:hover { border-color: var(--border-light); transform: translateY(-2px); }
        .kpi-label {
            font-size: 12px;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 500;
        }
        .kpi-value {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-top: 8px;
        }
        .kpi-value.accent { color: var(--accent); }

        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            text-align: left;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-4);
            border-bottom: 1px solid var(--border);
        }
        tbody td {
            padding: 12px 16px;
            font-size: 14px;
            border-bottom: 1px solid rgba(42, 42, 58, 0.5);
            color: var(--text-2);
        }
        tbody tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }
        tbody tr:last-child td { border-bottom: none; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            line-height: 1;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            color: #FFFFFF;
        }
        .btn-primary:hover {
            box-shadow: 0 4px 16px rgba(209, 36, 36, 0.3);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: var(--surface-3);
            color: var(--text-2);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover {
            background: var(--border);
            color: var(--text);
        }
        .btn-danger {
            background: rgba(209, 36, 36, 0.12);
            color: var(--danger);
            border: 1px solid rgba(209, 36, 36, 0.2);
        }
        .btn-danger:hover {
            background: rgba(209, 36, 36, 0.2);
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 8px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .badge-success {
            background: rgba(74, 222, 128, 0.1);
            color: var(--success);
            border: 1px solid rgba(74, 222, 128, 0.2);
        }
        .badge-warning {
            background: rgba(251, 191, 36, 0.1);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.2);
        }
        .badge-info {
            background: rgba(138, 138, 138, 0.1);
            color: var(--info);
            border: 1px solid rgba(138, 138, 138, 0.2);
        }
        .badge-danger {
            background: rgba(209, 36, 36, 0.1);
            color: var(--danger);
            border: 1px solid rgba(209, 36, 36, 0.2);
        }

        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-3);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px;
            background: rgba(12, 12, 18, 0.5);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
            outline: none;
        }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(209, 36, 36, 0.12);
        }
        .form-control::placeholder { color: var(--text-4); }
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='%238a8692'%3E%3Cpath d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: rgba(74, 222, 128, 0.08);
            border: 1px solid rgba(74, 222, 128, 0.15);
            color: var(--success);
        }
        .alert-error {
            background: rgba(209, 36, 36, 0.08);
            border: 1px solid rgba(209, 36, 36, 0.15);
            color: var(--danger);
        }

        /* Chat */
        .chat-container {
            height: 480px;
            overflow-y: auto;
            padding: 16px;
            background: rgba(12, 12, 18, 0.4);
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        .chat-container::-webkit-scrollbar { width: 6px; }
        .chat-container::-webkit-scrollbar-track { background: transparent; }
        .chat-container::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        .chat-msg {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
        }
        .chat-msg.user { align-items: flex-start; }
        .chat-msg.assistant { align-items: flex-end; }
        .chat-bubble {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            word-break: break-word;
        }
        .chat-msg.user .chat-bubble {
            background: var(--surface-3);
            color: var(--text);
            border-bottom-left-radius: 4px;
        }
        .chat-msg.assistant .chat-bubble {
            background: linear-gradient(135deg, rgba(209, 36, 36, 0.15), rgba(209, 36, 36, 0.06));
            color: var(--text);
            border: 1px solid rgba(209, 36, 36, 0.15);
            border-bottom-right-radius: 4px;
        }
        .chat-bubble.media {
            background: rgba(251, 191, 36, 0.08);
            border: 1px solid rgba(251, 191, 36, 0.15);
        }
        .chat-time {
            font-size: 10px;
            color: var(--text-4);
            margin-top: 4px;
            padding: 0 4px;
        }

        /* Logs */
        .log-viewer {
            background: #0a0a0f;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            height: 500px;
            overflow-y: auto;
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 12px;
            line-height: 1.8;
        }
        .log-viewer::-webkit-scrollbar { width: 6px; }
        .log-viewer::-webkit-scrollbar-track { background: transparent; }
        .log-viewer::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        .log-line {
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            white-space: pre-wrap;
            word-break: break-all;
        }
        .log-line.error { color: var(--danger); }
        .log-line.success { color: var(--success); }
        .log-line.info { color: var(--info); }
        .log-line.warn { color: #fbbf24; }

        /* File list */
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: rgba(12, 12, 18, 0.3);
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 8px;
            transition: all 0.15s ease;
        }
        .file-item:hover { border-color: var(--border-light); }
        .file-item .file-name {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-2);
        }

        /* Emoji/icon fallback styling */
        .icon-lg { font-size: 24px; line-height: 1; }

        /* Mobile toggle */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-2);
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 99;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-4);
        }
        .empty-state .icon { font-size: 48px; margin-bottom: 16px; opacity: 0.3; }
        .empty-state h4 { font-size: 16px; color: var(--text-3); margin-bottom: 8px; }
        .empty-state p { font-size: 13px; }

        /* Input file custom */
        input[type="file"]::file-selector-button {
            padding: 8px 16px;
            border-radius: 8px;
            background: var(--surface-3);
            border: 1px solid var(--border);
            color: var(--text-2);
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            cursor: pointer;
            margin-right: 12px;
            transition: all 0.15s ease;
        }
        input[type="file"]::file-selector-button:hover {
            background: var(--border);
            color: var(--text);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .sidebar-overlay.open {
                display: block;
            }
            .sidebar-toggle {
                display: block;
            }
            .main {
                margin-left: 0;
            }
            .main-header {
                padding: 16px 20px;
            }
            .main-content {
                padding: 20px;
            }
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo"><img src="img/logo.png" alt="Salvix" style="width:100%;height:100%;object-fit:contain;"></div>
            <div class="sidebar-brand-text">
                <h2><?php echo $tenant ? htmlspecialchars($tenant['nombre']) : 'Salvix'; ?></h2>
                <span><?php echo $tenant ? 'Cliente: ' . htmlspecialchars($tenant['slug']) : 'Admin Panel'; ?></span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <?php if(!$tenant): ?>
            <div class="sidebar-section">Plataforma</div>
            <a href="?view=clientes" class="nav-item <?php echo $currentView === 'clientes' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Clientes
                <?php if(!empty($tenantsList)): ?>
                    <span class="nav-badge"><?php echo count($tenantsList); ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <div class="sidebar-section">General</div>
            <a href="admin.php<?php echo $tenantQs; ?>" class="nav-item <?php echo $currentView === 'dashboard' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>

            <div class="sidebar-section">Negocio</div>
            <a href="admin.php<?php echo $viewQs; ?>view=inventory" class="nav-item <?php echo $currentView === 'inventory' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                Menú
            </a>
            <a href="admin.php<?php echo $viewQs; ?>view=knowledge" class="nav-item <?php echo $currentView === 'knowledge' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                Conocimiento
            </a>
            <a href="admin.php<?php echo $viewQs; ?>view=pedidos" class="nav-item <?php echo $currentView === 'pedidos' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                Pedidos
                <?php if($totalNewOrders > 0): ?>
                    <span class="nav-badge"><?php echo $totalNewOrders; ?></span>
                <?php endif; ?>
            </a>

            <div class="sidebar-section">Configuración</div>
            <?php if($tenant): ?>
            <a href="admin.php<?php echo $viewQs; ?>view=channels" class="nav-item <?php echo $currentView === 'channels' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                Canales
            </a>
            <?php endif; ?>
            <a href="admin.php<?php echo $viewQs; ?>view=payments" class="nav-item <?php echo $currentView === 'payments' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                Métodos de Pago
            </a>
            <a href="admin.php<?php echo $viewQs; ?>view=config" class="nav-item <?php echo $currentView === 'config' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Bot Config
            </a>
            <?php if(!$tenant): ?>
            <a href="?view=api" class="nav-item <?php echo $currentView === 'api' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4-8-10V5l8-3 8 3v7c0 6-8 10-8 10z"/></svg>
                APIs & Tokens
            </a>
            <?php endif; ?>

            <div class="sidebar-section">Sistema</div>
            <a href="admin.php<?php echo $viewQs; ?>view=logs" class="nav-item <?php echo $currentView === 'logs' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Logs
            </a>
            <a href="health.php" target="_blank" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                Health Check
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="admin.php<?php echo $viewQs; ?>logout=1" class="nav-item" style="color: var(--danger);">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Cerrar Sesión
            </a>
        </div>
    </aside>

    <!-- ===== MAIN ===== -->
    <div class="main">
        <div class="main-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
                <h1>
                    <?php
                    $titles = [
                        'dashboard' => 'Dashboard',
                        'clientes' => 'Clientes',
                        'inventory' => 'Menú',
                        'knowledge' => 'Base de Conocimiento',
                        'config' => 'Configuración del Bot',
                        'api' => 'APIs & Credenciales',
                        'logs' => 'Logs del Sistema',
                        'pedidos' => 'Pedidos',
                    ];
                    echo $titles[$currentView] ?? 'Dashboard';
                    ?>
                </h1>
            </div>
            <div class="main-header-actions">
                <span style="font-size:13px; color:var(--text-4);"><?php echo date('d M Y'); ?></span>
            </div>
        </div>

        <div class="main-content">

            <?php if(isset($success_msg)): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if(isset($error_msg)): ?>
                <div class="alert alert-error"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <?php if ($currentView === 'config'): ?>

                <!-- ===== CONFIG VIEW ===== -->
                <div class="card card-glow">
                    <div class="card-header">
                        <div>
                            <h3>Generador Automático de Instrucciones</h3>
                            <p class="label">Describe tu negocio y la IA redactará las reglas del bot automáticamente</p>
                        </div>
                        <span class="badge badge-info">IA</span>
                    </div>
                    <form method="POST">
                        <div class="form-group">
                            <label>Información del negocio</label>
                            <textarea class="form-control" name="company_info" rows="3" placeholder="Ej: Somos una clínica odontológica llamada 'Sonrisa Sana'. Atendemos de Lunes a Viernes de 8am a 6pm. Queremos que el bot sea muy amable y pida el DNI para agendar."></textarea>
                        </div>
                        <button type="submit" name="generate_prompt" class="btn btn-primary">Generar con IA</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Instrucciones del Sistema</h3>
                            <p class="label">Edita manualmente el comportamiento y personalidad del bot</p>
                        </div>
                    </div>
                    <form method="POST">
                        <div class="form-group">
                            <textarea class="form-control" name="system_prompt" rows="16" style="font-family:'SF Mono','Fira Code',monospace; font-size:13px;"><?php echo htmlspecialchars($prompt_content); ?></textarea>
                        </div>
                        <button type="submit" name="save_config" class="btn btn-primary">Guardar Instrucciones</button>
                    </form>
                </div>

            <?php elseif ($currentView === 'channels'): 

                $APP_ID = '884874344543876';
                $WHATSAPP_CONFIG_ID = '891103050469599';
                $hasWhatsApp = !empty($tenant['phone_number_id']);
                $hasInstagram = !empty($tenant['ig_account_id']);
                ?>
                <!-- ===== CHANNELS VIEW ===== -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Canales de Contacto</h3>
                            <p class="label">Conecta WhatsApp e Instagram para que el bot atienda en ambos canales</p>
                        </div>
                    </div>

                    <!-- WhatsApp -->
                    <div style="padding:20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:28px;">📱</span>
                            <div>
                                <strong style="color:var(--text);">WhatsApp</strong>
                                <?php if($hasWhatsApp): ?>
                                    <br><span style="font-size:12px; color:var(--text-3);">Conectado: <?php echo htmlspecialchars($tenant['phone_number_id']); ?></span>
                                <?php else: ?>
                                    <br><span style="font-size:12px; color:var(--text-3);">No conectado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <?php if($hasWhatsApp): ?>
                                <span class="badge badge-success">Activo</span>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary" onclick="connectWhatsApp()">Conectar</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Instagram -->
                    <div style="padding:20px; display:flex; align-items:center; justify-content:space-between;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:28px;">📸</span>
                            <div>
                                <strong style="color:var(--text);">Instagram</strong>
                                <?php if($hasInstagram): ?>
                                    <br><span style="font-size:12px; color:var(--text-3);">Conectado: <?php echo htmlspecialchars($tenant['ig_account_id']); ?></span>
                                <?php else: ?>
                                    <br><span style="font-size:12px; color:var(--text-3);">No conectado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <?php if($hasInstagram): ?>
                                <span class="badge badge-success">Activo</span>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary" onclick="connectInstagram()">Conectar</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Facebook SDK -->
                <div id="fb-root"></div>
                <script async defer crossorigin="anonymous" src="https://connect.facebook.net/es_LA/sdk.js#xfbml=1&version=v21.0&autoLogAppEvents=1"></script>
                <script>
                window.fbAsyncInit = function() {
                    FB.init({ appId: '<?php echo $APP_ID; ?>', cookie: true, xfbml: true, version: 'v21.0' });
                };

                function connectWhatsApp() {
                    FB.login(function(response) {
                        if (response.authResponse) {
                           exchangeCode('whatsapp', response.authResponse.code);
                        }
                    }, {
                        config_id: '<?php echo $WHATSAPP_CONFIG_ID; ?>',
                        response_type: 'code',
                        override_default_response_type: true,
                        extras: { features: 'qr', session_info_version: '3' }
                    });
                }

                function connectInstagram() {
                    FB.login(function(response) {
                        if (response.authResponse) {
                           exchangeCode('instagram', response.authResponse.code);
                        }
                    }, {
                        config_id: '<?php echo $WHATSAPP_CONFIG_ID; ?>',
                        response_type: 'code',
                        override_default_response_type: true,
                        extras: { features: 'qr', session_info_version: '3' }
                    });
                }

                function exchangeCode(platform, code) {
                    fetch('meta-exchange.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ code: code, platform: platform, tenant_id: <?php echo $tenant['id']; ?> })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            alert('¡' + (platform === 'whatsapp' ? 'WhatsApp' : 'Instagram') + ' conectado exitosamente!');
                            location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'No se pudo conectar'));
                        }
                    })
                    .catch(err => alert('Error de conexión: ' + err));
                }
                </script>

            <?php elseif ($currentView === 'payments'):
                $methods = getPaymentMethods($pdo);
                $pm = $methods['pago_movil'] ?? [];
                $tr = $methods['transferencia'] ?? [];
                ?>
                <!-- ===== PAYMENTS VIEW ===== -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Métodos de Pago</h3>
                            <p class="label">Estos datos se muestran al cliente cuando apruebas un pedido y cuando pida los datos de pago</p>
                        </div>
                    </div>
                    <form method="POST">
                        <h4 style="color:var(--text); margin-bottom:16px;">📱 Pago Móvil</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Banco</label>
                                <input type="text" class="form-control" name="pm_banco" value="<?php echo htmlspecialchars($pm['banco'] ?? ''); ?>" placeholder="ej: Banesco">
                            </div>
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="text" class="form-control" name="pm_telefono" value="<?php echo htmlspecialchars($pm['telefono'] ?? ''); ?>" placeholder="ej: 04121234567">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Documento</label>
                                <input type="text" class="form-control" name="pm_documento" value="<?php echo htmlspecialchars($pm['documento'] ?? ''); ?>" placeholder="ej: V-12345678">
                            </div>
                            <div class="form-group">
                                <label>Titular</label>
                                <input type="text" class="form-control" name="pm_titular" value="<?php echo htmlspecialchars($pm['titular'] ?? ''); ?>" placeholder="Nombre del titular">
                            </div>
                        </div>

                        <hr style="border:0; border-top:1px solid var(--border); margin:24px 0;">

                        <h4 style="color:var(--text); margin-bottom:16px;">🏦 Transferencia Bancaria</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Banco</label>
                                <input type="text" class="form-control" name="tr_banco" value="<?php echo htmlspecialchars($tr['banco'] ?? ''); ?>" placeholder="ej: Mercantil">
                            </div>
                            <div class="form-group">
                                <label>Número de Cuenta</label>
                                <input type="text" class="form-control" name="tr_cuenta" value="<?php echo htmlspecialchars($tr['cuenta'] ?? ''); ?>" placeholder="ej: 0104-1234-56-78901234">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Titular</label>
                            <input type="text" class="form-control" name="tr_titular" value="<?php echo htmlspecialchars($tr['titular'] ?? ''); ?>" placeholder="Nombre del titular">
                        </div>

                        <div style="margin-top:20px;">
                            <button type="submit" name="save_payment_methods" class="btn btn-primary">Guardar Métodos de Pago</button>
                        </div>
                    </form>
                </div>

                <div class="card" style="background:var(--bg);">
                    <div style="padding:16px 20px;">
                        <p style="margin:0; font-size:13px; color:var(--text-3);">
                            <strong>¿Cómo funciona?</strong> Cuando un cliente pida los datos de pago por el chat, el bot le pasará automáticamente esta información. También se incluye al aprobar un pedido.
                        </p>
                    </div>
                </div>

            <?php elseif ($currentView === 'knowledge'): 
                $kd = knowledgeDir();
                $files = is_dir($kd) ? array_diff(scandir($kd), array('.', '..', '.htaccess')) : [];
                ?>
                <!-- ===== KNOWLEDGE VIEW ===== -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Subir Documento</h3>
                            <p class="label">Archivos .txt, .csv, .md — el bot usará esta información para responder</p>
                        </div>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <input type="file" name="knowledge_file" accept=".txt,.csv,.md,.docx" class="form-control" style="padding:10px 14px;">
                        </div>
                        <button type="submit" name="upload_file" class="btn btn-primary">Subir Archivo</button>
                    </form>
                    <div style="margin-top:16px;">
                        <form method="POST">
                            <button type="submit" name="sync_knowledge" class="btn btn-primary" style="width:100%;">
                                Sincronizar Cerebro (Indexar todo)
                            </button>
                            <p style="font-size:11px; color:var(--text-4); text-align:center; margin-top:8px;">
                                Pulsa después de subir o borrar archivos para que el bot se actualice
                            </p>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Archivos Actuales</h3>
                    </div>
                    <?php if (!empty($files)): ?>
                        <?php foreach ($files as $f): ?>
                            <div class="file-item">
                                <div class="file-name">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <?php echo htmlspecialchars($f); ?>
                                </div>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este archivo?');">
                                    <input type="hidden" name="file_name" value="<?php echo htmlspecialchars($f); ?>">
                                    <button type="submit" name="delete_file" class="btn btn-danger btn-sm">Eliminar</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="icon">📄</div>
                            <h4>No hay archivos</h4>
                            <p>Sube documentos para que el bot tenga conocimiento de tu negocio</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($currentView === 'inventory'): 
                $inventory = $pdo->query("SELECT * FROM inventory ORDER BY id DESC")->fetchAll();
                ?>
                <!-- ===== INVENTORY VIEW ===== -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Menú</h3>
                            <p class="label">El bot ofrecerá estos productos a los clientes. Activa/desactiva lo que quieras mostrar.</p>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="item_id" id="inv_id" value="">

                        <div class="form-group">
                            <label>Nombre del producto</label>
                            <input type="text" class="form-control" name="item_name" id="inv_name" placeholder="Ej: Perro caliente especial" required>
                        </div>

                        <div class="form-group">
                            <label>Descripción</label>
                            <textarea class="form-control" name="description" id="inv_desc" rows="2" placeholder="Describe brevemente el producto"></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Categoría</label>
                                <input type="text" class="form-control" name="category" id="inv_cat" placeholder="Ej: Fuertes, Bebidas, Postres">
                            </div>
                            <div class="form-group">
                                <label>Precio ($)</label>
                                <input type="number" step="0.01" class="form-control" name="price" id="inv_price" value="0.00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" name="active" id="inv_active" checked style="width:18px; height:18px;">
                                Visible en el menú del bot
                            </label>
                        </div>

                        <div style="display:flex; gap:10px;">
                            <button type="submit" name="save_inventory" class="btn btn-primary">Guardar Producto</button>
                            <button type="button" class="btn btn-secondary" onclick="clearInventoryForm()">Limpiar</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Lista de Productos</h3>
                    </div>
                    <?php if (!empty($inventory)): ?>
                        <div style="overflow-x:auto;">
                            <table>
                                <thead>
                                    <tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Activo</th><th>Acción</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory as $i): ?>
                                    <tr>
                                        <td>
                                            <strong style="color:var(--text);"><?php echo htmlspecialchars($i['item_name']); ?></strong>
                                            <?php if($i['description']): ?>
                                                <br><span style="font-size:12px; color:var(--text-3);"><?php echo htmlspecialchars($i['description']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($i['category']): ?>
                                                <span class="badge badge-info"><?php echo htmlspecialchars($i['category']); ?></span>
                                            <?php else: ?>
                                                <span style="color:var(--text-3);">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-weight:600; color:var(--accent);">$<?php echo number_format($i['price'], 2); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="item_id" value="<?php echo $i['id']; ?>">
                                                <button type="submit" name="toggle_inventory_active" class="badge <?php echo $i['active'] ? 'badge-success' : 'badge-warning'; ?>" style="border:none; cursor:pointer;">
                                                    <?php echo $i['active'] ? 'Activo' : 'Inactivo'; ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:6px;">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="editInventory(<?php echo $i['id']; ?>, '<?php echo addslashes(htmlspecialchars($i['item_name'])); ?>', '<?php echo addslashes(htmlspecialchars($i['description'])); ?>', '<?php echo addslashes(htmlspecialchars($i['category'])); ?>', '<?php echo $i['price']; ?>', '<?php echo $i['active']; ?>')">Editar</button>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este producto?');">
                                                    <input type="hidden" name="item_id" value="<?php echo $i['id']; ?>">
                                                    <button type="submit" name="delete_inventory" class="btn btn-danger btn-sm">Eliminar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="icon">🍽️</div>
                            <h4>Menú vacío</h4>
                            <p>Agrega productos para que el bot pueda ofrecerlos a los clientes</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($currentView === 'logs'): 
                $logs = @file_get_contents(__DIR__ . '/debug.log') ?: "";
                $logLines = array_reverse(explode("\n", trim($logs)));
                if ($tenant) {
                    $tag = "[tenant: {$tenant['slug']}]";
                    $logLines = array_values(array_filter($logLines, function($line) use ($tag) {
                        return strpos($line, $tag) !== false;
                    }));
                }
                $lastLogs = array_slice($logLines, 0, 50);
                if (empty($lastLogs)) {
                    $lastLogs = [($tenant ? "No hay registros para este cliente aún." : "No hay registros aún.")];
                }
                ?>
                <!-- ===== LOGS VIEW ===== -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Últimos 50 eventos</h3>
                            <p class="label">Historial de depuración (Meta, Groq y errores del sistema)</p>
                        </div>
                        <a href="admin.php<?php echo $viewQs; ?>view=logs" class="btn btn-secondary btn-sm">⟳ Refrescar</a>
                    </div>
                    <div class="log-viewer">
                        <?php foreach ($lastLogs as $line): 
                            if (empty($line)) continue;
                            $cls = "log-line";
                            if (strpos($line, 'ERROR') !== false) $cls .= " error";
                            elseif (strpos($line, 'ÉXITO') !== false) $cls .= " success";
                            elseif (strpos($line, 'GROQ') !== false || strpos($line, 'IA') !== false) $cls .= " info";
                            elseif (strpos($line, 'FATAL') !== false) $cls .= " error";
                        ?>
                            <div class="<?php echo $cls; ?>"><?php echo htmlspecialchars($line); ?></div>
                        <?php endforeach; ?>
                        <?php if (empty($lastLogs)): ?>
                            <div style="color:var(--text-4); text-align:center; padding:40px;">No hay logs disponibles</div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($currentView === 'clientes'): ?>
                <!-- ===== CLIENTES VIEW (super admin) ===== -->
                <div class="card card-glow">
                    <div class="card-header">
                        <div>
                            <h3>Registrar Nuevo Cliente</h3>
                            <p class="label">Cada cliente = su número de WhatsApp, su base de datos, su prompt y su knowledge</p>
                        </div>
                        <span class="badge badge-warning">Webhook compartido</span>
                    </div>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Slug (identificador único)</label>
                                <input type="text" class="form-control" name="tenant_slug" placeholder="restaurante_x" style="font-family:monospace; font-size:13px;" required>
                            </div>
                            <div class="form-group">
                                <label>Nombre del negocio</label>
                                <input type="text" class="form-control" name="tenant_nombre" placeholder="Restaurante X" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone Number ID (Meta)</label>
                                <input type="text" class="form-control" name="tenant_phone_id" placeholder="ej: 105389103276100" style="font-family:monospace; font-size:13px;" required>
                            </div>
                            <div class="form-group">
                                <label>WABA ID (opcional)</label>
                                <input type="text" class="form-control" name="tenant_waba_id" placeholder="ej: 105389103276100" style="font-family:monospace; font-size:13px;">
                            </div>
                        </div>

                        <hr style="border:0; border-top:1px solid var(--border); margin:20px 0;">

                        <div class="form-row">
                            <div class="form-group">
                                <label>Host BD</label>
                                <input type="text" class="form-control" name="tenant_db_host" value="localhost">
                            </div>
                            <div class="form-group">
                                <label>Nombre BD (vacío = salvix_&lt;slug&gt;)</label>
                                <input type="text" class="form-control" name="tenant_db_name" placeholder="salvix_restaurante_x" style="font-family:monospace; font-size:13px;">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Usuario BD</label>
                                <input type="text" class="form-control" name="tenant_db_user" style="font-family:monospace; font-size:13px;" required>
                            </div>
                            <div class="form-group">
                                <label>Contraseña BD</label>
                                <input type="password" class="form-control" name="tenant_db_pass" style="font-family:monospace; font-size:13px;">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Usuario del panel</label>
                                <input type="text" class="form-control" name="tenant_admin_user" value="admin" required>
                            </div>
                            <div class="form-group">
                                <label>Contraseña del panel</label>
                                <input type="text" class="form-control" name="tenant_admin_pass" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Enlace CTA (agendamiento / WhatsApp)</label>
                                <input type="text" class="form-control" name="tenant_cta_url" placeholder="https://wa.me/584121234567">
                            </div>
                            <div class="form-group">
                                <label>Token WhatsApp propio (opcional)</label>
                                <input type="text" class="form-control" name="tenant_wa_token" placeholder="En blanco = token global" style="font-family:monospace; font-size:13px;">
                            </div>
                        </div>
                        <button type="submit" name="save_tenant" class="btn btn-primary">Crear e Instalar Cliente</button>
                        <p style="font-size:11px; color:var(--text-4); margin-top:10px;">
                            Crea la base de datos (requiere privilegios de CREATE), instala el esquema, siembra el prompt y crea la carpeta knowledge/&lt;slug&gt;/.
                        </p>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Clientes Registrados</h3>
                        <span class="badge badge-info"><?php echo count($tenantsList); ?> clientes</span>
                    </div>
                    <?php if (!empty($tenantsList)): ?>
                        <div style="overflow-x:auto;">
                            <table>
                                <thead>
                                    <tr><th>Cliente</th><th>Phone ID</th><th>BD</th><th>Panel</th><th>Acción</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tenantsList as $t): ?>
                                    <tr>
                                        <td>
                                            <strong style="color:var(--text);"><?php echo htmlspecialchars($t['nombre']); ?></strong>
                                            <br><span style="font-size:12px; color:var(--text-3); font-family:monospace;"><?php echo htmlspecialchars($t['slug']); ?></span>
                                        </td>
                                        <td style="font-family:monospace; font-size:13px;"><?php echo htmlspecialchars($t['phone_number_id']); ?></td>
                                        <td style="font-size:13px;"><?php echo htmlspecialchars($t['db_name']); ?></td>
                                        <td>
                                            <a href="admin.php?tenant=<?php echo urlencode($t['slug']); ?>" class="btn btn-primary btn-sm">Entrar</a>
                                        </td>
                                        <td>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar el cliente <?php echo addslashes($t['nombre']); ?> del registro? La base de datos se conserva.');">
                                                <input type="hidden" name="tenant_slug" value="<?php echo htmlspecialchars($t['slug']); ?>">
                                                <button type="submit" name="delete_tenant" class="btn btn-danger btn-sm">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="icon">🏢</div>
                            <h4>No hay clientes registrados</h4>
                            <p>Registra tu primer cliente arriba: todos comparten el mismo webhook de Meta</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($currentView === 'api'): ?>
                <!-- ===== API VIEW ===== -->
                <div class="card card-glow">
                    <div class="card-header">
                        <div>
                            <h3>Credenciales de Conexión</h3>
                            <p class="label">Llaves maestras para WhatsApp Cloud API y Groq</p>
                        </div>
                        <span class="badge badge-warning">⚠️ Sensible</span>
                    </div>
                    <form method="POST">
                        <div class="form-group">
                            <label>WhatsApp API Token</label>
                            <input type="text" class="form-control" name="wa_token" value="<?php echo htmlspecialchars($_ENV['WHATSAPP_API_TOKEN'] ?? getenv('WHATSAPP_API_TOKEN')); ?>" style="font-family:monospace; font-size:13px;">
                        </div>
                        <div class="form-group">
                            <label>WhatsApp Phone Number ID</label>
                            <input type="text" class="form-control" name="wa_phone_id" value="<?php echo htmlspecialchars($_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? getenv('WHATSAPP_PHONE_NUMBER_ID')); ?>">
                        </div>

                        <hr style="border:0; border-top:1px solid var(--border); margin:24px 0;">

                        <div class="form-group">
                            <label>Groq API Key</label>
                            <input type="text" class="form-control" name="groq_key" value="<?php echo htmlspecialchars($_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY'))); ?>" style="font-family:monospace; font-size:13px;">
                        </div>
                        <div class="form-group">
                            <label>Modelo de Texto (Groq)</label>
                            <input type="text" class="form-control" name="text_model" value="<?php echo htmlspecialchars($_ENV['GROQ_MODEL'] ?? getenv('GROQ_MODEL') ?: ($_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL'))); ?>">
                        </div>

                        <button type="submit" name="save_api" class="btn btn-primary">Guardar Credenciales</button>
                    </form>
                </div>

            <?php elseif ($currentView === 'pedidos'): 
                $orders = $pdo->query("SELECT * FROM orders ORDER BY FIELD(status,'nuevo','en_verificacion','aprobado','pagado','en_camino','entregado','cancelado'), id DESC")->fetchAll();
                ?>
                <!-- ===== PEDIDOS VIEW ===== -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Pedidos de Delivery</h3>
                            <p class="label">Revisa, aprueba y confirma los pedidos que llegan por el chat</p>
                        </div>
                        <span class="badge badge-info"><?php echo count($orders); ?> pedidos</span>
                    </div>
                    <?php if (!empty($orders)): ?>
                        <div style="overflow-x:auto;">
                            <table>
                                <thead>
                                    <tr><th>N°</th><th>Cliente</th><th>Detalle</th><th>Dirección</th><th>Total</th><th>Estado</th><th>Acciones</th></tr>
                                </thead>
                                <tbody id="orders-tbody">
                                    <?php foreach ($orders as $o): 
                                        $itemsLines = orderItemsLines($o);
                                        $badgeClass = match($o['status']) {
                                            'pagado', 'entregado' => 'badge-success',
                                            'nuevo', 'en_verificacion' => 'badge-warning',
                                            'en_camino' => 'badge-info',
                                            'cancelado' => 'badge-danger',
                                            default => 'badge-info',
                                        };
                                        $pmPreset = 'Pago Móvil';
                                        $pa = !empty($o['payment_analysis']) ? json_decode($o['payment_analysis'], true) : null;
                                        if (is_array($pa)) {
                                            if (($pa['type'] ?? '') === 'transferencia') $pmPreset = 'Transferencia';
                                            if (($pa['type'] ?? '') === 'efectivo') $pmPreset = 'Efectivo';
                                        }
                                    ?>
                                    <tr>
                                        <td style="font-family:monospace; font-size:13px; font-weight:600; color:var(--accent);"><?php echo htmlspecialchars($o['order_number']); ?></td>
                                        <td style="font-family:monospace; font-size:13px;">
                                            <?php echo htmlspecialchars($o['wa_id']); ?>
                                            <?php if(!empty($o['contact_phone']) && $o['contact_phone'] !== $o['wa_id']): ?>
                                                <br><span style="font-size:11px; color:var(--text-3);">📞 <?php echo htmlspecialchars($o['contact_phone']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:13px; max-width:220px;">
                                            <?php foreach ($itemsLines as $line): ?>
                                                <div><?php echo htmlspecialchars($line); ?></div>
                                            <?php endforeach; ?>
                                            <?php if($o['delivery_zone']): ?><span style="font-size:11px; color:var(--text-3);">Zona: <?php echo htmlspecialchars($o['delivery_zone']); ?></span><?php endif; ?>
                                            <?php if($o['payment_method']): ?><br><span style="font-size:11px; color:var(--success);">Pago: <?php echo htmlspecialchars($o['payment_method']); ?></span><?php endif; ?>
                                            <?php if(!empty($o['payment_image'])): ?>
                                                <div style="margin-top:8px; border:1px solid var(--border); border-radius:8px; padding:6px; background:var(--surface-3); display:inline-block;">
                                                    <img src="<?php echo htmlspecialchars($o['payment_image']); ?>" alt="Comprobante" title="Ver comprobante" style="max-width:140px; max-height:110px; border-radius:6px; cursor:pointer; display:block;" onclick="openImage(this.src)">
                                                    <?php if(is_array($pa)): ?>
                                                        <div style="font-size:11px; margin-top:4px;">
                                                            <?php if(!empty($pa['is_payment'])): ?>
                                                                <span class="badge badge-success">Pago analizado</span>
                                                                <?php if(!empty($pa['bank'])): ?><div style="color:var(--text);">🏦 <?php echo htmlspecialchars($pa['bank']); ?></div><?php endif; ?>
                                                                <?php if(!empty($pa['amount'])): ?><div style="color:var(--accent); font-weight:600;">💰 <?php echo htmlspecialchars($pa['amount']); ?></div><?php endif; ?>
                                                                <?php if(!empty($pa['reference'])): ?><div style="color:var(--text-3);">REF: <?php echo htmlspecialchars($pa['reference']); ?></div><?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge badge-danger">⚠️ No parece pago</span>
                                                                <?php if(!empty($pa['reason'])): ?><div style="color:var(--text-3);"><?php echo htmlspecialchars($pa['reason']); ?></div><?php endif; ?>
                                                            <?php endif; ?>
                                                            <?php if(!empty($pa['summary'])): ?><div style="color:var(--text-3);"><?php echo htmlspecialchars($pa['summary']); ?></div><?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:13px; max-width:180px;"><?php echo htmlspecialchars($o['delivery_address'] ?: '—'); ?></td>
                                        <td style="font-weight:600; color:var(--accent);"><?php echo $o['total'] > 0 ? '$' . number_format($o['total'], 2, ',', '.') : '—'; ?></td>
                                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo getOrderStatusLabel($o['status']); ?></span></td>
                                        <td>
                                            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                                                <a href="admin.php<?php echo $viewQs; ?>chat=<?php echo $o['wa_id']; ?>" class="btn btn-secondary btn-sm">Chat</a>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="showOrderDetail(<?php echo (int)$o['id']; ?>)">Detalle</button>
                                                <?php if($o['status'] === 'nuevo'): ?>
                                                    <?php
                                                        $oIncomplete = empty(trim($o['delivery_address'] ?? '')) || empty(trim($o['contact_phone'] ?? ''));
                                                        $oMissing = [];
                                                        if (empty(trim($o['delivery_address'] ?? ''))) $oMissing[] = 'dirección';
                                                        if (empty(trim($o['contact_phone'] ?? ''))) $oMissing[] = 'número de contacto';
                                                    ?>
                                                    <?php if(!$oIncomplete): ?>
                                                        <?php
                                                            $oSubtotal = computeOrderSubtotal($pdo, $o);
                                                            $oCanAuto = ($oSubtotal !== null);
                                                        ?>
                                                        <form method="POST" style="display:inline-flex; gap:4px; align-items:center;" onsubmit="return confirm('¿Aprobar el pedido <?php echo htmlspecialchars($o['order_number']); ?> y enviar el total al cliente?');">
                                                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                                            <input type="number" step="0.01" min="0" name="delivery_cost" placeholder="Delivery $" <?php echo $oCanAuto ? 'required' : ''; ?> title="Costo del delivery" style="width:90px; padding:6px 8px; border-radius:8px; background:var(--surface-3); border:1px solid var(--border); color:var(--text); font-size:12px; font-family:'Inter',sans-serif;">
                                                            <?php if($oCanAuto): ?>
                                                                <span style="font-size:12px; color:var(--text-3); white-space:nowrap;" id="totPrev_<?php echo (int)$o['id']; ?>" data-subtotal="<?php echo $oSubtotal; ?>">Total: $<?php echo number_format($oSubtotal, 2, ',', '.'); ?> + delivery</span>
                                                            <?php else: ?>
                                                                <input type="number" step="0.01" min="0.01" name="order_total" placeholder="Total $" required title="No se pudo calcular: ingresa el total manual" style="width:90px; padding:6px 8px; border-radius:8px; background:var(--surface-3); border:1px solid var(--border); color:var(--text); font-size:12px; font-family:'Inter',sans-serif;">
                                                            <?php endif; ?>
                                                            <button type="submit" name="approve_order" class="btn btn-primary btn-sm">Aprobar</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning" title="Falta <?php echo implode(' y ', $oMissing); ?>">Falta <?php echo implode(' y ', $oMissing); ?></span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if(in_array($o['status'], ['aprobado','en_verificacion'])): ?>
                                                    <?php if(empty($o['payment_image']) && empty(trim($o['payment_ref'] ?? ''))): ?>
                                                        <div style="font-size:11px; color:#f59e0b; margin-bottom:4px;">⚠️ Sin comprobante — espera la imagen del cliente</div>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display:inline-flex; gap:4px; align-items:center;" onsubmit="return confirm('¿Confirmar el pago del pedido <?php echo htmlspecialchars($o['order_number']); ?>?');">
                                                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                                        <select name="payment_method" required style="padding:6px 8px; border-radius:8px; background:var(--surface-3); border:1px solid var(--border); color:var(--text); font-size:12px; font-family:'Inter',sans-serif;">
                                                            <option value="Pago Móvil" <?php echo $pmPreset === 'Pago Móvil' ? 'selected' : ''; ?>>Pago Móvil</option>
                                                            <option value="Transferencia" <?php echo $pmPreset === 'Transferencia' ? 'selected' : ''; ?>>Transferencia</option>
                                                            <option value="Efectivo" <?php echo $pmPreset === 'Efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                                                        </select>
                                                        <button type="submit" name="confirm_paid" class="btn btn-primary btn-sm">Confirmar Pago</button>
                                                    </form>
                                                    <?php if($o['status'] === 'en_verificacion' && !empty($o['payment_image'])): ?>
                                                        <div style="font-size:11px; color:#22c55e; margin-bottom:4px;">✅ Comprobante recibido</div>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Rechazar el comprobante del pedido <?php echo htmlspecialchars($o['order_number']); ?>? El pedido volverá a aprobado y se notificará al cliente.');">
                                                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                                            <button type="submit" name="reject_payment" class="btn btn-danger btn-sm">Rechazar Comprobante</button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if($o['status'] === 'pagado'): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Marcar el pedido <?php echo htmlspecialchars($o['order_number']); ?> como en camino y notificar al cliente?');">
                                                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                                        <button type="submit" name="mark_on_way" class="btn btn-primary btn-sm">En Camino</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if($o['status'] === 'en_camino'): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Marcar el pedido <?php echo htmlspecialchars($o['order_number']); ?> como entregado y notificar al cliente?');">
                                                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                                        <button type="submit" name="mark_delivered" class="btn btn-secondary btn-sm">Entregado</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if(!in_array($o['status'], ['entregado','cancelado'])): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Cancelar el pedido <?php echo htmlspecialchars($o['order_number']); ?>? Se notificará al cliente.');">
                                                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                                        <button type="submit" name="cancel_order" class="btn btn-danger btn-sm">Cancelar</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar permanentemente el pedido <?php echo htmlspecialchars($o['order_number']); ?>? Esta acción no se puede deshacer.');">
                                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                                    <button type="submit" name="delete_order" class="btn btn-danger btn-sm" style="background:var(--surface-3); color:var(--danger,#e74c3c); border-color:var(--danger,#e74c3c);">Eliminar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="icon">🛵</div>
                            <h4>No hay pedidos aún</h4>
                            <p>Cuando un cliente pida por el chat, el pedido aparecerá aquí para que lo apruebes</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ===== MODAL DETALLE DE PEDIDO ===== -->
                <style>
                    .modal-overlay{position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1000; display:none; align-items:center; justify-content:center; padding:20px;}
                    .modal-overlay.open{display:flex;}
                    .modal-box{background:var(--surface); border:1px solid var(--border); border-radius:14px; max-width:580px; width:100%; max-height:90vh; overflow:auto; box-shadow:0 20px 50px rgba(0,0,0,.35);}
                    .modal-header{display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid var(--border); position:sticky; top:0; background:var(--surface);}
                    .modal-header h3{margin:0; font-size:16px;}
                    .modal-close{background:var(--surface-3); border:1px solid var(--border); color:var(--text); width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:16px; line-height:1;}
                    .modal-body{padding:20px;}
                    .om-grid{display:grid; grid-template-columns:1fr 1fr; gap:10px 16px; margin-bottom:14px;}
                    .om-field{font-size:13px;}
                    .om-field .k{color:var(--text-3); font-size:11px; display:block; margin-bottom:2px;}
                    .om-field .v{font-weight:600; word-break:break-word;}
                    .om-section{font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text-3); border-bottom:1px solid var(--border); padding-bottom:6px; margin:18px 0 10px;}
                    .om-comprobante{border:1px solid var(--border); border-radius:10px; padding:10px; background:var(--surface-3);}
                    .om-comprobante img{max-width:100%; max-height:300px; border-radius:8px; cursor:pointer; display:block; margin-bottom:8px;}
                </style>
                <div class="modal-overlay" id="orderModal" onclick="if(event.target===this)closeOrderModal()">
                    <div class="modal-box">
                        <div class="modal-header">
                            <h3 id="omTitle">Detalle</h3>
                            <button type="button" class="modal-close" onclick="closeOrderModal()">✕</button>
                        </div>
                        <div class="modal-body" id="omBody"></div>
                    </div>
                </div>
                <script>
                    var orderData = <?php
                        $orderJson = [];
                        foreach ($orders as $o) {
                            $pa = !empty($o['payment_analysis']) ? json_decode($o['payment_analysis'], true) : null;
                            $badgeClass = match($o['status']) {
                                'pagado', 'entregado' => 'badge-success',
                                'nuevo', 'en_verificacion' => 'badge-warning',
                                'en_camino' => 'badge-info',
                                'cancelado' => 'badge-danger',
                                default => 'badge-info',
                            };
                            $orderJson[] = [
                                'id' => (int)$o['id'],
                                'order_number' => $o['order_number'],
                                'status_label' => getOrderStatusLabel($o['status']),
                                'badge_class' => $badgeClass,
                                'created_at' => $o['created_at'],
                                'wa_id' => $o['wa_id'],
                                'contact_phone' => $o['contact_phone'] ?: $o['wa_id'],
                                'items' => orderItemsLines($o),
                                'type' => $o['type'],
                                'address' => $o['delivery_address'],
                                'zone' => $o['delivery_zone'],
                                'total' => $o['total'] > 0 ? '$' . number_format($o['total'], 2, ',', '.') : '—',
                                'delivery_cost' => ($o['delivery_cost'] ?? 0) > 0 ? '$' . number_format((float)$o['delivery_cost'], 2, ',', '.') : '—',
                                'payment_method' => $o['payment_method'],
                                'payment_ref' => $o['payment_ref'],
                                'payment_image' => $o['payment_image'],
                                'analysis' => is_array($pa) ? $pa : null,
                                'admin_note' => $o['admin_note'] ?? '',
                            ];
                        }
                        echo json_encode($orderJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    ?>;
                    function esc(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
                    function showOrderDetail(id){
                        var o = null;
                        for (var i = 0; i < orderData.length; i++) { if (orderData[i].id === id) { o = orderData[i]; break; } }
                        if (!o) return;
                        var items = (o.items || []).map(function(it){ return '<div>' + esc(it) + '</div>'; }).join('');
                        var h = '';
                        h += '<div style="margin-bottom:14px;"><span class="badge ' + esc(o.badge_class) + '">' + esc(o.status_label) + '</span> <span style="color:var(--text-3); font-size:12px;">' + esc(o.created_at) + '</span></div>';
                        h += '<div class="om-section">Cliente</div>';
                        h += '<div class="om-grid">';
                        h += '<div class="om-field"><span class="k">WhatsApp</span><span class="v">' + esc(o.wa_id) + '</span></div>';
                        h += '<div class="om-field"><span class="k">Teléfono contacto</span><span class="v">' + esc(o.contact_phone) + '</span></div>';
                        h += '</div>';
                        h += '<div class="om-section">Pedido</div>';
                        h += '<div class="om-grid">';
                        h += '<div class="om-field"><span class="k">Tipo</span><span class="v">' + esc(o.type) + '</span></div>';
                        h += '<div class="om-field"><span class="k">Delivery</span><span class="v">' + esc(o.delivery_cost || '—') + '</span></div>';
                        h += '<div class="om-field"><span class="k">Total</span><span class="v" style="color:var(--accent);">' + esc(o.total) + '</span></div>';
                        h += '</div>';
                        if (o.admin_note) h += '<div style="font-size:12px; color:var(--text-3); margin-bottom:12px;">Nota: ' + esc(o.admin_note) + '</div>';
                        h += '<div style="font-size:13px; margin-bottom:12px;">' + items + '</div>';
                        h += '<div class="om-grid">';
                        h += '<div class="om-field" style="grid-column:1/-1;"><span class="k">Dirección</span><span class="v">' + esc(o.address || '—') + '</span></div>';
                        h += '<div class="om-field"><span class="k">Zona</span><span class="v">' + esc(o.zone || '—') + '</span></div>';
                        h += '<div class="om-field"><span class="k">Método de pago</span><span class="v">' + esc(o.payment_method || '—') + '</span></div>';
                        if (o.payment_ref) h += '<div class="om-field"><span class="k">Referencia</span><span class="v">' + esc(o.payment_ref) + '</span></div>';
                        h += '</div>';
                        h += '<div class="om-section">Comprobante de Pago</div>';
                        if (o.payment_image) {
                            h += '<div class="om-comprobante">';
                            h += '<img src="' + esc(o.payment_image) + '" alt="Comprobante" onclick="openImage(this.src)">';
                            if (o.analysis) {
                                var a = o.analysis;
                                h += (a.is_payment ? '<span class="badge badge-success">Pago analizado</span>' : '<span class="badge badge-danger">⚠️ No parece pago</span>');
                                if (a.type) h += '<div style="font-size:12px; margin-top:6px; color:var(--text-3);">Tipo: ' + esc(a.type) + '</div>';
                                if (a.bank) h += '<div style="font-size:13px; margin-top:4px;">🏦 ' + esc(a.bank) + '</div>';
                                if (a.amount) h += '<div style="font-size:14px; font-weight:600; color:var(--accent);">💰 ' + esc(a.amount) + '</div>';
                                if (a.reference) h += '<div style="font-size:12px; color:var(--text-3);">REF: ' + esc(a.reference) + '</div>';
                                if (a.date) h += '<div style="font-size:12px; color:var(--text-3);">Fecha: ' + esc(a.date) + '</div>';
                                if (a.summary) h += '<div style="font-size:12px; margin-top:6px; color:var(--text-3);">' + esc(a.summary) + '</div>';
                                if (!a.is_payment && a.reason) h += '<div style="font-size:12px; margin-top:4px; color:var(--danger, #e74c3c);">' + esc(a.reason) + '</div>';
                            } else {
                                h += '<div style="font-size:12px; color:var(--text-3); margin-top:6px;">Sin análisis de la IA.</div>';
                            }
                            h += '</div>';
                        } else {
                            h += '<div style="font-size:13px; color:var(--text-3);">Sin comprobante adjunto.</div>';
                        }
                        document.getElementById('omTitle').textContent = 'Pedido ' + o.order_number;
                        document.getElementById('omBody').innerHTML = h;
                        document.getElementById('orderModal').classList.add('open');
                    }
                    function closeOrderModal(){ document.getElementById('orderModal').classList.remove('open'); }
                    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeOrderModal(); });
                </script>

            <?php else: ?>
                <!-- ===== DASHBOARD VIEW ===== -->
                <div class="kpi-grid" id="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-label">Mensajes Procesados</div>
                        <div class="kpi-value" id="kpi-msgs"><?php echo $totalMsgs; ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Pedidos</div>
                        <div class="kpi-value" id="kpi-orders"><?php echo $totalOrders; ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Pendientes</div>
                        <div class="kpi-value accent" id="kpi-pending"><?php echo $pendingOrders; ?></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Conversaciones Recientes</h3>
                            <p class="label">Últimas conversaciones con clientes</p>
                        </div>
                        <form method="GET" style="display:flex; gap:8px;">
                            <input type="text" class="form-control" name="search" placeholder="Buscar por ID..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="width:200px; padding:8px 12px; font-size:13px;">
                            <button type="submit" class="btn btn-secondary btn-sm">Buscar</button>
                        </form>
                    </div>
                    <?php 
                    $search = $_GET['search'] ?? '';
                    $query = "SELECT m.wa_id, m.content as last_msg, m.created_at FROM messages m INNER JOIN (SELECT wa_id, MAX(id) as max_id FROM messages GROUP BY wa_id) latest ON m.id = latest.max_id";
                    if ($search) {
                        $query .= " WHERE m.wa_id LIKE :search ";
                    }
                    $query .= " ORDER BY m.created_at DESC LIMIT 50";
                    
                    $stmt = $pdo->prepare($query);
                    if ($search) {
                        $stmt->bindValue(':search', "%$search%");
                    }
                    $stmt->execute();
                    $threads = $stmt->fetchAll();
                    ?>
                    <?php if (!empty($threads)): ?>
                        <div style="overflow-x:auto;">
                            <table>
                                <thead>
                                    <tr><th>WA ID</th><th>Último Mensaje</th><th>Acción</th></tr>
                                </thead>
                                <tbody id="threads-tbody">
                                    <?php foreach ($threads as $t): ?>
                                    <tr>
                                        <td style="font-family:monospace; font-size:13px;"><?php echo $t['wa_id']; ?></td>
                                        <td>
                                            <div style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text-2);"><?php echo htmlspecialchars(mb_substr($t['last_msg'], 0, 60)); ?></div>
                                            <div style="font-size:11px; color:var(--text-3);"><?php echo date('d M H:i', strtotime($t['created_at'])); ?></div>
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:6px;">
                                                <a href="admin.php<?php echo $viewQs; ?>chat=<?php echo $t['wa_id']; ?>" class="btn btn-secondary btn-sm">Ver Chat</a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar toda la conversación con <?php echo addslashes($t['wa_id']); ?>?');">
                                                    <input type="hidden" name="chat_id" value="<?php echo $t['wa_id']; ?>">
                                                    <button type="submit" name="delete_chat" class="btn btn-danger btn-sm">Eliminar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="icon">💬</div>
                            <h4>No hay conversaciones</h4>
                            <p>Los mensajes aparecerán aquí cuando los clientes interactúen con el bot</p>
                        </div>
                    <?php endif; ?>
                </div>

                

                <?php if (isset($_GET['chat'])): 
                    $chatId = $_GET['chat'];
                    $messages = $pdo->prepare("SELECT * FROM messages WHERE wa_id = ? ORDER BY created_at ASC LIMIT 50");
                    $messages->execute([$chatId]);
                    ?>
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3>Chat: <span style="font-family:monospace; color:var(--accent);"><?php echo htmlspecialchars($chatId); ?></span></h3>
                                <p class="label">Historial de conversación</p>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <form method="POST" onsubmit="return confirm('¿Limpiar toda la conversación de este número? El bot la atenderá como si fuera la primera vez. Se eliminan mensajes y pedidos nuevos pendientes.');">
                                    <input type="hidden" name="chat_id" value="<?php echo htmlspecialchars($chatId); ?>">
                                    <button type="submit" name="clear_chat" class="btn btn-danger btn-sm">Limpiar conversación</button>
                                </form>
                                <a href="admin.php<?php echo $tenantSlug ? '?tenant=' . urlencode($tenantSlug) : ''; ?>" class="btn btn-secondary btn-sm">← Volver</a>
                            </div>
                        </div>
                        <div class="chat-container" id="chatContainer">
                            <?php foreach ($messages as $m): 
                                $isMedia = (strpos($m['content'], '[Imagen]') !== false || strpos($m['content'], '[Audio') !== false);
                            ?>
                                <div class="chat-msg <?php echo $m['role']; ?>">
                                    <div class="chat-bubble <?php echo $isMedia ? 'media' : ''; ?>">
                                        <?php if (!empty($m['image_data'])): ?>
                                            <img src="<?php echo htmlspecialchars($m['image_data']); ?>" alt="Imagen del cliente" title="Ver imagen" style="max-width:240px; max-height:240px; border-radius:10px; display:block; cursor:pointer; margin-bottom:6px;" onclick="openImage(this.src)">
                                        <?php endif; ?>
                                        <?php if ($isMedia): ?>
                                            <span style="opacity:0.6; font-size:11px; display:block; margin-bottom:4px;">📎 Multimedia</span>
                                        <?php endif; ?>
                                        <?php echo nl2br(htmlspecialchars($m['content'])); ?>
                                    </div>
                                    <div class="chat-time"><?php echo date('H:i', strtotime($m['created_at'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form method="POST" style="display:flex; gap:10px; margin-top:16px; align-items:flex-end;">
                            <input type="hidden" name="chat_id" value="<?php echo htmlspecialchars($chatId); ?>">
                            <div style="flex:1; position:relative;">
                                <textarea name="reply_text" id="replyInput" rows="2" class="form-control" placeholder="Escribe tu respuesta y envía..." style="resize:none; min-height:44px; padding-right:48px;" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.querySelector('[name=send_reply]').click();}"></textarea>
                            </div>
                            <button type="submit" name="send_reply" class="btn btn-primary" style="height:44px; flex-shrink:0;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            </button>
                        </form>
                    </div>
                    <script>
                        var container = document.getElementById('chatContainer');
                        if (container) container.scrollTop = container.scrollHeight;
                        var input = document.getElementById('replyInput');
                        if (input) input.focus();
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .lightbox-overlay{position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:2000; display:none; align-items:center; justify-content:center; padding:20px;}
        .lightbox-overlay.open{display:flex;}
    </style>
    <div class="lightbox-overlay" id="imageLightbox" onclick="if(event.target===this)closeLightbox()">
        <img id="lightboxImg" alt="Imagen" style="max-width:92vw; max-height:92vh; border-radius:10px; box-shadow:0 10px 40px rgba(0,0,0,.6);">
        <button type="button" class="modal-close" style="position:fixed; top:16px; right:16px;" onclick="closeLightbox()">✕</button>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }

        function openImage(src) {
            document.getElementById('lightboxImg').src = src;
            document.getElementById('imageLightbox').classList.add('open');
        }
        function closeLightbox() {
            document.getElementById('imageLightbox').classList.remove('open');
            document.getElementById('lightboxImg').src = '';
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeLightbox();
        });

        function clearInventoryForm() {
            document.getElementById('inv_id').value = '';
            document.getElementById('inv_name').value = '';
            document.getElementById('inv_desc').value = '';
            document.getElementById('inv_cat').value = '';
            document.getElementById('inv_price').value = '0.00';
            document.getElementById('inv_active').checked = true;
        }

        function editInventory(id, name, desc, cat, price, active) {
            document.getElementById('inv_id').value = id;
            document.getElementById('inv_name').value = name;
            document.getElementById('inv_desc').value = desc;
            document.getElementById('inv_cat').value = cat;
            document.getElementById('inv_price').value = price;
            document.getElementById('inv_active').checked = (active === '1');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Cerrar sidebar al hacer clic en un enlace en mobile
        document.querySelectorAll('.nav-item').forEach(function(el) {
            el.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });

        // Preview del total al escribir el delivery
        document.querySelectorAll('input[name=delivery_cost]').forEach(function(inp) {
            inp.addEventListener('input', function() {
                var prev = document.getElementById('totPrev_' + inp.closest('form').querySelector('input[name=order_id]').value);
                if (!prev) return;
                var sub = parseFloat(prev.getAttribute('data-subtotal')) || 0;
                var d = parseFloat(inp.value) || 0;
                prev.textContent = 'Total: $' + (sub + d).toFixed(2).replace('.', ',');
            });
        });

        // ===== REAL-TIME POLLING =====
        function startPolling(section, interval, callback, extra) {
            extra = extra || '';
            var since = 0;
            var busy = false;
            function poll() {
                if (busy) return;
                busy = true;
                fetch('api_data.php?section=' + section + '&since=' + since + extra)
                    .then(function(res) {
                        busy = false;
                        if (res.status === 304) return null;
                        if (!res.ok) return null;
                        return res.json();
                    })
                    .then(function(data) {
                        if (data && data.ts) {
                            since = data.ts;
                            callback(data);
                        }
                    })
                    .catch(function() { busy = false; });
            }
            poll();
            setInterval(poll, interval);
        }

        function escapeHtml(s) {
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(s));
            return d.innerHTML;
        }

        // Dashboard: KPIs + threads
        if (document.getElementById('kpi-grid')) {
            startPolling('dashboard', 10000, function(data) {
                var el;
                el = document.getElementById('kpi-msgs'); if (el) el.textContent = data.totalMsgs;
                el = document.getElementById('kpi-orders'); if (el) el.textContent = data.totalOrders;
                el = document.getElementById('kpi-pending'); if (el) el.textContent = data.pendingOrders;

                var tbody = document.getElementById('threads-tbody');
                if (tbody && data.threads) {
                    var html = '';
                    data.threads.forEach(function(t) {
                        var preview = t.last_msg.length > 60 ? t.last_msg.substring(0, 60) + '...' : t.last_msg;
                        var d = new Date(t.created_at.replace(' ', 'T'));
                        var month = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][d.getMonth()];
                        var hour = d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
                        var ts = d.getDate() + ' ' + month + ' ' + hour;
                        html += '<tr>'
                            + '<td style="font-family:monospace; font-size:13px;">' + escapeHtml(t.wa_id) + '</td>'
                            + '<td><div style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text-2);">' + escapeHtml(preview) + '</div>'
                            + '<div style="font-size:11px; color:var(--text-3);">' + ts + '</div></td>'
                            + '<td><div style="display:flex; gap:6px;">'
                            + '<a href="admin.php<?php echo $viewQs; ?>chat=' + encodeURIComponent(t.wa_id) + '" class="btn btn-secondary btn-sm">Ver Chat</a>'
                            + '</div></td></tr>';
                    });
                    tbody.innerHTML = html;
                }
            });
        }

        // Pedidos
        if (document.getElementById('orders-tbody')) {
            startPolling('orders', 10000, function(data) {
                var tbody = document.getElementById('orders-tbody');
                if (!tbody || !data.html) return;
                tbody.innerHTML = data.html;
                // Re-attach delivery cost preview listeners
                document.querySelectorAll('input[name=delivery_cost]').forEach(function(inp) {
                    inp.removeEventListener('input', inp._handler);
                    inp._handler = function() {
                        var prev = document.getElementById('totPrev_' + inp.closest('form').querySelector('input[name=order_id]').value);
                        if (!prev) return;
                        var sub = parseFloat(prev.getAttribute('data-subtotal')) || 0;
                        var d = parseFloat(inp.value) || 0;
                        prev.textContent = 'Total: $' + (sub + d).toFixed(2).replace('.', ',');
                    };
                    inp.addEventListener('input', inp._handler);
                });
            });
        }

        // Chat
        if (document.getElementById('chatContainer')) {
            var chatWaId = '<?php echo addslashes($_GET["chat"] ?? ""); ?>';
            if (chatWaId) {
                startPolling('chat', 3000, function(data) {
                    var container = document.getElementById('chatContainer');
                    if (!container || !data.messages) return;
                    var existing = container.children.length;
                    if (data.messages.length <= existing) return;

                    for (var i = existing; i < data.messages.length; i++) {
                        var m = data.messages[i];
                        var isMedia = (m.content && (m.content.indexOf('[Imagen]') !== -1 || m.content.indexOf('[Audio') !== -1));
                        var div = document.createElement('div');
                        div.className = 'chat-msg ' + m.role;
                        var bubble = '<div class="chat-bubble' + (isMedia ? ' media' : '') + '">';
                        if (m.image_data) bubble += '<img src="' + m.image_data + '" style="max-width:240px; max-height:240px; border-radius:10px; display:block; cursor:pointer; margin-bottom:6px;" onclick="openImage(this.src)">';
                        if (isMedia) bubble += '<span style="opacity:0.6; font-size:11px; display:block; margin-bottom:4px;">📎 Multimedia</span>';
                        bubble += escapeHtml(m.content || '');
                        bubble += '</div>';
                        var time = m.created_at ? new Date(m.created_at).toLocaleTimeString('es-VE', {hour:'2-digit', minute:'2-digit'}) : '';
                        div.innerHTML = bubble + '<div class="chat-time">' + time + '</div>';
                        container.appendChild(div);
                    }
                    container.scrollTop = container.scrollHeight;
                }, '&wa_id=' + encodeURIComponent(chatWaId));
            }
        }
    </script>

</body>
</html>
<?php
?>
