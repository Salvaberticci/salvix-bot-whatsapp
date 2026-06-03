<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/knowledge.php';
require_once __DIR__ . '/whatsapp.php';
session_start();

// 1. Autenticación Simple
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === ($_ENV['ADMIN_USER'] ?? getenv('ADMIN_USER')) && $_POST['password'] === ($_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD'))) {
        $_SESSION['admin'] = true;
    } else {
        $error = "Credenciales incorrectas";
    }
}

if (!isset($_SESSION['admin'])) {
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
                width: 56px;
                height: 56px;
                background: linear-gradient(135deg, #D12424, #E03030);
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 16px;
                font-size: 22px;
                font-weight: 700;
                color: #FFFFFF;
                box-shadow: 0 8px 24px rgba(209, 36, 36, 0.3);
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
                background: rgba(239, 68, 68, 0.1);
                border: 1px solid rgba(239, 68, 68, 0.2);
                color: #fca5a5;
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
                <div class="login-logo">S</div>
                <h1>Salvix Admin</h1>
                <p>Panel de control del bot</p>
            </div>
            <div class="login-card">
                <?php if(isset($error)): ?>
                    <div class="login-error"><?php echo $error; ?></div>
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
    file_put_contents(__DIR__ . '/prompts/system.md', $newPrompt);
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
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [['role' => 'user', 'content' => $metaPrompt]],
            'temperature' => 0.5
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . ($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY'))
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        $generatedPrompt = $data['choices'][0]['message']['content'] ?? '';
        
        if ($generatedPrompt) {
            file_put_contents(__DIR__ . '/prompts/system.md', trim($generatedPrompt));
            $success_msg = "Instrucciones generadas con IA correctamente.";
        } else {
            $error_msg = "No se pudo generar el prompt. Revisa los logs.";
        }
    }
}

// 2.1 Lógica de Guardado de APIs (.env)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_api'])) {
    $envPath = __DIR__ . '/.env';
    $envContent = file_get_contents($envPath);
    
    $keysToUpdate = [
        'WHATSAPP_API_TOKEN' => $_POST['wa_token'],
        'WHATSAPP_PHONE_NUMBER_ID' => $_POST['wa_phone_id'],
        'OPENAI_API_KEY' => $_POST['groq_key'],
        'OPENAI_MODEL' => $_POST['text_model']
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
    
    file_put_contents($envPath, $envContent);
    $success_msg = "Credenciales de API actualizadas.";
}

// 2.2 Lógica de Archivos de Conocimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    $target_dir = __DIR__ . '/knowledge/';
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
    @unlink(__DIR__ . '/knowledge/' . $file);
    $success_msg = "Archivo eliminado.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_knowledge'])) {
    $chunks = indexKnowledge();
    $success_msg = "Cerebro sincronizado. Se han creado $chunks fragmentos de conocimiento.";
}

// 2.3 Lógica de Inventario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_inventory'])) {
    $item_name = $_POST['item_name'];
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $stock = $_POST['stock'] ?? 0;
    
    $pdo = getDB();
    if (!empty($_POST['item_id'])) {
        $stmt = $pdo->prepare("UPDATE inventory SET item_name=?, description=?, price=?, stock=? WHERE id=?");
        $stmt->execute([$item_name, $description, $price, $stock, $_POST['item_id']]);
        $success_msg = "Producto actualizado.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO inventory (item_name, description, price, stock) VALUES (?, ?, ?, ?)");
        $stmt->execute([$item_name, $description, $price, $stock]);
        $success_msg = "Producto añadido al inventario.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_inventory'])) {
    $id = $_POST['item_id'];
    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE id=?");
    $stmt->execute([$id]);
    $success_msg = "Producto eliminado.";
}

// 2.4 Lógica de Envío de Respuesta Manual desde Admin
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

// 2.5 Lógica de Perfil de WhatsApp
function getWhatsAppProfile() {
    $url = "https://graph.facebook.com/v25.0/" . WA_PHONE_ID . "/whatsapp_business_profile?fields=about,description,email,websites,profile_picture_url";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http === 200) {
        $data = json_decode($resp, true);
        return $data['data'][0] ?? null;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_about'])) {
    $about = trim($_POST['about_text'] ?? '');
    if ($about) {
        $url = "https://graph.facebook.com/v25.0/" . WA_PHONE_ID . "/whatsapp_business_profile";
        $payload = json_encode(['about' => $about]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . WA_TOKEN
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http === 200) {
            $success_msg = "Estado/About actualizado correctamente.";
        } else {
            $error_msg = "Error al actualizar. Código HTTP: $http";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_profile_pic'])) {
    if (!empty($_FILES['profile_pic']['tmp_name'])) {
        $file = $_FILES['profile_pic']['tmp_name'];
        $filename = basename($_FILES['profile_pic']['name']);
        $mime = mime_content_type($file);

        // 1. Subir imagen a Meta como media
        $url = "https://graph.facebook.com/v25.0/" . WA_PHONE_ID . "/media";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'messaging_product' => 'whatsapp',
            'file' => new CURLFile($file, $mime, $filename),
            'type' => $mime
        ]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http === 200) {
            $mediaData = json_decode($resp, true);
            $mediaId = $mediaData['id'] ?? null;
            if ($mediaId) {
                // 2. Asignar como foto de perfil
                $url2 = "https://graph.facebook.com/v25.0/" . WA_PHONE_ID . "/whatsapp_business_profile";
                $payload2 = json_encode(['profile_picture_handle' => $mediaId]);
                $ch2 = curl_init($url2);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, $payload2);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . WA_TOKEN
                ]);
                $resp2 = curl_exec($ch2);
                $http2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                if ($http2 === 200) {
                    $success_msg = "Foto de perfil actualizada correctamente.";
                } else {
                    $error_msg = "Error al asignar foto. Código HTTP: $http2";
                }
            } else {
                $error_msg = "No se obtuvo ID de la imagen subida.";
            }
        } else {
            $error_msg = "Error al subir imagen. Código HTTP: $http";
        }
    }
}

$waProfile = getWhatsAppProfile();

// 2.6 Lógica de Eliminar Conversación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_chat'])) {
    $chatId = $_POST['chat_id'] ?? '';
    if ($chatId) {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM messages WHERE wa_id = ?");
        $stmt->execute([$chatId]);
        $success_msg = "Conversación con $chatId eliminada.";
    }
}

// 3. Lógica del Dashboard
$pdo = getDB();
$prompt_content = @file_get_contents(__DIR__ . '/prompts/system.md') ?: "";

// Contar métricas
$totalMsgs = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$totalLeads = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$qualifiedLeads = $pdo->query("SELECT COUNT(*) FROM leads WHERE qualification_status = 'calificado'")->fetchColumn();

// Listar hilos de conversación
$threads = $pdo->query("SELECT wa_id, MAX(created_at) as last_msg FROM messages GROUP BY wa_id ORDER BY last_msg DESC LIMIT 50")->fetchAll();

$currentView = $_GET['view'] ?? 'dashboard';
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
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 16px;
            color: #FFFFFF;
            flex-shrink: 0;
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
            background: rgba(239, 68, 68, 0.12);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.2);
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
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.15);
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
            <div class="sidebar-logo">S</div>
            <div class="sidebar-brand-text">
                <h2>Salvix</h2>
                <span>Admin Panel</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section">General</div>
            <a href="admin.php" class="nav-item <?php echo $currentView === 'dashboard' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <a href="?view=leads" class="nav-item <?php echo $currentView === 'leads' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Leads
                <?php if($totalLeads > 0): ?>
                    <span class="nav-badge"><?php echo $totalLeads; ?></span>
                <?php endif; ?>
            </a>

            <div class="sidebar-section">Negocio</div>
            <a href="?view=inventory" class="nav-item <?php echo $currentView === 'inventory' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                Inventario
            </a>
            <a href="?view=knowledge" class="nav-item <?php echo $currentView === 'knowledge' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                Conocimiento
            </a>

            <div class="sidebar-section">Configuración</div>
            <a href="?view=config" class="nav-item <?php echo $currentView === 'config' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Bot Config
            </a>
            <a href="?view=api" class="nav-item <?php echo $currentView === 'api' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4-8-10V5l8-3 8 3v7c0 6-8 10-8 10z"/></svg>
                APIs & Tokens
            </a>

            <div class="sidebar-section">Sistema</div>
            <a href="?view=logs" class="nav-item <?php echo $currentView === 'logs' ? 'active' : ''; ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Logs
            </a>
            <a href="health.php" target="_blank" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                Health Check
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="?logout=1" class="nav-item" style="color: var(--danger);">
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
                        'leads' => 'Leads & Prospectos',
                        'inventory' => 'Inventario',
                        'knowledge' => 'Base de Conocimiento',
                        'config' => 'Configuración del Bot',
                        'api' => 'APIs & Credenciales',
                        'logs' => 'Logs del Sistema',
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

                <!-- ===== WHATSAPP PROFILE ===== -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Perfil de WhatsApp del Bot</h3>
                            <p class="label">Imagen y descripción que ven los clientes al chatear</p>
                        </div>
                        <span class="badge badge-info">WhatsApp</span>
                    </div>
                    <div style="display:flex; gap:24px; flex-wrap:wrap;">
                        <div style="flex-shrink:0;">
                            <div style="width:96px; height:96px; border-radius:50%; border:3px solid var(--border); overflow:hidden; background:var(--surface-3); display:flex; align-items:center; justify-content:center;">
                                <?php if ($waProfile && !empty($waProfile['profile_picture_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($waProfile['profile_picture_url']); ?>" alt="Profile" style="width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <span style="font-size:36px; opacity:0.3;">📷</span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" enctype="multipart/form-data" style="margin-top:10px;">
                                <label class="btn btn-secondary btn-sm" style="cursor:pointer; width:100%; justify-content:center; font-size:11px;">
                                    Cambiar foto
                                    <input type="file" name="profile_pic" accept="image/png,image/jpeg" style="display:none;" onchange="this.form.submit();">
                                </label>
                                <input type="hidden" name="upload_profile_pic" value="1">
                            </form>
                        </div>
                        <div style="flex:1; min-width:200px;">
                            <div style="font-size:11px; color:var(--text-4); text-transform:uppercase; letter-spacing:0.05em; font-weight:500;">Nombre del contacto</div>
                            <div style="font-size:18px; font-weight:600; margin-top:4px; color:var(--text);"><?php echo htmlspecialchars(WA_PHONE_ID); ?></div>
                            <div style="font-size:12px; color:var(--text-3); margin-top:2px;">ID del número</div>

                            <form method="POST" style="margin-top:16px;">
                                <div class="form-group" style="margin-bottom:10px;">
                                    <label>Estado / About</label>
                                    <input type="text" class="form-control" name="about_text" value="<?php echo htmlspecialchars($waProfile['about'] ?? ''); ?>" placeholder="Ej: Respondemos consultas de Lun-Vie 9-18hs" maxlength="139">
                                </div>
                                <button type="submit" name="update_about" class="btn btn-primary btn-sm">Guardar About</button>
                            </form>
                        </div>
                    </div>
                </div>

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

            <?php elseif ($currentView === 'knowledge'): 
                $files = array_diff(scandir(__DIR__ . '/knowledge'), array('.', '..', '.htaccess'));
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
                            <h3>Producto / Servicio</h3>
                            <p class="label">El bot podrá ofrecer estos productos con precios en tiempo real</p>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="item_id" id="inv_id" value="">

                        <div class="form-group">
                            <label>Nombre del producto</label>
                            <input type="text" class="form-control" name="item_name" id="inv_name" placeholder="Ej: Consulta básica" required>
                        </div>

                        <div class="form-group">
                            <label>Descripción</label>
                            <textarea class="form-control" name="description" id="inv_desc" rows="2" placeholder="Describe brevemente el producto o servicio"></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Precio ($)</label>
                                <input type="number" step="0.01" class="form-control" name="price" id="inv_price" value="0.00">
                            </div>
                            <div class="form-group">
                                <label>Stock</label>
                                <input type="number" class="form-control" name="stock" id="inv_stock" value="0">
                            </div>
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
                                    <tr><th>Producto</th><th>Precio</th><th>Stock</th><th>Acción</th></tr>
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
                                        <td style="font-weight:600; color:var(--accent);">$<?php echo number_format($i['price'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php echo $i['stock'] > 0 ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo $i['stock']; ?> uds
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:6px;">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="editInventory(<?php echo $i['id']; ?>, '<?php echo addslashes(htmlspecialchars($i['item_name'])); ?>', '<?php echo addslashes(htmlspecialchars($i['description'])); ?>', '<?php echo $i['price']; ?>', '<?php echo $i['stock']; ?>')">Editar</button>
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
                            <div class="icon">📦</div>
                            <h4>Inventario vacío</h4>
                            <p>Agrega productos para que el bot pueda ofrecerlos a los clientes</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($currentView === 'logs'): 
                $logs = @file_get_contents(__DIR__ . '/debug.log') ?: "No hay registros aún.";
                $logLines = array_reverse(explode("\n", trim($logs)));
                $lastLogs = array_slice($logLines, 0, 50);
                ?>
                <!-- ===== LOGS VIEW ===== -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Últimos 50 eventos</h3>
                            <p class="label">Historial de depuración (Meta, Groq y errores del sistema)</p>
                        </div>
                        <a href="?view=logs" class="btn btn-secondary btn-sm">⟳ Refrescar</a>
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

            <?php elseif ($currentView === 'leads'): 
                $allLeads = $pdo->query("SELECT * FROM leads ORDER BY created_at DESC")->fetchAll();
                ?>
                <!-- ===== LEADS VIEW ===== -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Prospectos Detectados</h3>
                            <p class="label">Usuarios calificados automáticamente por la IA</p>
                        </div>
                        <span class="badge badge-info"><?php echo count($allLeads); ?> leads</span>
                    </div>
                    <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr><th>WhatsApp</th><th>Contacto</th><th>Resumen</th><th>Solicitud</th><th>Estado</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allLeads as $l): ?>
                                <tr>
                                    <td style="font-family:monospace; font-size:13px;"><?php echo $l['wa_id']; ?></td>
                                    <td>
                                        <strong style="color:var(--text);"><?php echo htmlspecialchars($l['nombre'] ?: 'Sin nombre'); ?></strong>
                                        <?php if($l['negocio']): ?>
                                            <br><span style="font-size:12px; color:var(--text-3);"><?php echo htmlspecialchars($l['negocio']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:13px; max-width:200px;"><?php echo htmlspecialchars($l['resumen'] ?: 'N/A'); ?></td>
                                    <td style="font-size:13px; max-width:200px; color:var(--accent);">
                                        <strong><?php echo htmlspecialchars($l['solicitud'] ?: 'N/A'); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $l['qualification_status'] === 'calificado' ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo strtoupper($l['qualification_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($allLeads)): ?>
                                    <tr><td colspan="5">
                                        <div class="empty-state" style="padding:32px;">
                                            <div class="icon">👥</div>
                                            <h4>No hay leads aún</h4>
                                            <p>Cuando la IA califique prospectos, aparecerán aquí</p>
                                        </div>
                                    </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
                            <input type="text" class="form-control" name="groq_key" value="<?php echo htmlspecialchars($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY')); ?>" style="font-family:monospace; font-size:13px;">
                        </div>
                        <div class="form-group">
                            <label>Modelo de Texto</label>
                            <input type="text" class="form-control" name="text_model" value="<?php echo htmlspecialchars($_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL')); ?>">
                        </div>

                        <button type="submit" name="save_api" class="btn btn-primary">Guardar Credenciales</button>
                    </form>
                </div>

            <?php else: ?>
                <!-- ===== DASHBOARD VIEW ===== -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-label">Mensajes Procesados</div>
                        <div class="kpi-value"><?php echo $totalMsgs; ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Leads Totales</div>
                        <div class="kpi-value"><?php echo $totalLeads; ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Calificados</div>
                        <div class="kpi-value accent"><?php echo $qualifiedLeads; ?></div>
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
                    $query = "SELECT wa_id, MAX(created_at) as last_msg FROM messages ";
                    if ($search) {
                        $query .= " WHERE wa_id LIKE :search ";
                    }
                    $query .= " GROUP BY wa_id ORDER BY last_msg DESC LIMIT 50";
                    
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
                                    <tr><th>WA ID</th><th>Última Actividad</th><th>Acción</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($threads as $t): ?>
                                    <tr>
                                        <td style="font-family:monospace; font-size:13px;"><?php echo $t['wa_id']; ?></td>
                                        <td style="color:var(--text-3);"><?php echo $t['last_msg']; ?></td>
                                        <td>
                                            <div style="display:flex; gap:6px;">
                                                <a href="?chat=<?php echo $t['wa_id']; ?>" class="btn btn-secondary btn-sm">Ver Chat</a>
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
                            <a href="admin.php" class="btn btn-secondary btn-sm">← Volver</a>
                        </div>
                        <div class="chat-container" id="chatContainer">
                            <?php foreach ($messages as $m): 
                                $isMedia = (strpos($m['content'], '[Imagen]') !== false || strpos($m['content'], '[Audio') !== false);
                            ?>
                                <div class="chat-msg <?php echo $m['role']; ?>">
                                    <div class="chat-bubble <?php echo $isMedia ? 'media' : ''; ?>">
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

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }

        function clearInventoryForm() {
            document.getElementById('inv_id').value = '';
            document.getElementById('inv_name').value = '';
            document.getElementById('inv_desc').value = '';
            document.getElementById('inv_price').value = '0.00';
            document.getElementById('inv_stock').value = '0';
        }

        function editInventory(id, name, desc, price, stock) {
            document.getElementById('inv_id').value = id;
            document.getElementById('inv_name').value = name;
            document.getElementById('inv_desc').value = desc;
            document.getElementById('inv_price').value = price;
            document.getElementById('inv_stock').value = stock;
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
    </script>

</body>
</html>
<?php
?>
