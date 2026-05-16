<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/knowledge.php';
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
    <head><title>Salvix Admin - Login</title><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: sans-serif; background: #f4f5f2; display: grid; place-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 300px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #151716; color: white; border: none; border-radius: 6px; cursor: pointer; }
    </style>
    </head>
    <body>
        <div class="login-card">
            <h2>Salvix Admin</h2>
            <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Usuario" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit" name="login">Entrar</button>
            </form>
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
            $success_msg = "¡Instrucciones generadas mágicamente con IA!";
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
    
    if($fileType == "txt" || $fileType == "csv" || $fileType == "md") {
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
    $success_msg = "¡Cerebro sincronizado! Se han creado $chunks fragmentos de conocimiento.";
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

// 3. Lógica del Dashboard
$pdo = getDB();
$prompt_content = @file_get_contents(__DIR__ . '/prompts/system.md') ?: "";

// Contar métricas
$totalMsgs = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$totalLeads = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$qualifiedLeads = $pdo->query("SELECT COUNT(*) FROM leads WHERE qualification_status = 'calificado'")->fetchColumn();

// Listar hilos de conversación
$threads = $pdo->query("SELECT wa_id, MAX(created_at) as last_msg FROM messages GROUP BY wa_id ORDER BY last_msg DESC LIMIT 50")->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Salvix Admin - PHP</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { --bg: #f4f5f2; --ink: #151716; --muted: #68706c; --primary: #176b5b; }
        body { margin: 0; font-family: system-ui, sans-serif; background: var(--bg); color: var(--ink); display: flex; height: 100vh; }
        aside { width: 240px; background: #fbfcfa; border-right: 1px solid #dde2dc; padding: 20px; box-sizing: border-box; }
        main { flex: 1; padding: 30px; overflow-y: auto; }
        .card { background: white; padding: 20px; border-radius: 8px; border: 1px solid #dde2dc; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi { font-size: 24px; font-weight: bold; }
        .label { color: var(--muted); font-size: 13px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; }
        th { background: #fafbf9; color: var(--muted); font-size: 12px; }
        .btn { padding: 8px 16px; background: var(--ink); color: white; text-decoration: none; border-radius: 6px; font-size: 13px; border:none; cursor:pointer; }
        .btn.secondary { background: #eee; color: var(--ink); }
        .badge { padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: bold; background: #eee; }
        textarea { width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 15px; font-family: monospace; font-size: 14px; box-sizing: border-box; }
    </style>
</head>
<body>
    <aside>
        <h2>Salvix</h2>
        <p class="label">Panel PHP</p>
        <hr>
        <nav>
            <p><a href="admin.php" style="color:var(--ink); text-decoration:none; font-weight:bold;">📊 Dashboard</a></p>
            <p><a href="?view=leads" style="color:var(--ink); text-decoration:none;">👥 Leads Calificados</a></p>
            <p><a href="?view=inventory" style="color:var(--ink); text-decoration:none;">📦 Inventario</a></p>
            <p><a href="?view=knowledge" style="color:var(--ink); text-decoration:none;">📚 Base de Conocimientos</a></p>
            <p><a href="?view=logs" style="color:var(--ink); text-decoration:none;">📋 Logs de Sistema</a></p>
            <p><a href="?view=config" style="color:var(--ink); text-decoration:none;">⚙️ Configuración (Bot)</a></p>
            <p><a href="?view=api" style="color:var(--ink); text-decoration:none;">🔑 APIs y Tokens</a></p>
            <p><a href="health.php" target="_blank" style="color:var(--muted); text-decoration:none;">🏥 Estado Salud</a></p>
            <hr>
            <p><a href="?logout=1" style="color:red; text-decoration:none;">🚪 Salir</a></p>
        </nav>
    </aside>
    <main>
        <?php if (isset($_GET['view']) && $_GET['view'] === 'config'): ?>
            <h1>⚙️ Configuración del Bot</h1>
            
            <div class="card" style="background:#f4fbf9; border:1px solid var(--primary);">
                <h3>✨ Generador de Instrucciones Automático</h3>
                <p class="label">Escribe de qué trata tu negocio y la IA redactará las reglas técnicas por ti.</p>
                <form method="POST">
                    <textarea name="company_info" rows="3" placeholder="Ej: Somos una clínica odontológica llamada 'Sonrisa Sana'. Atendemos de Lunes a Viernes de 8am a 6pm. Queremos que el bot sea muy amable y pida el DNI para agendar." style="margin-bottom:10px;"></textarea>
                    <button type="submit" name="generate_prompt" class="btn" style="background:var(--primary);">Generar con IA</button>
                </form>
            </div>

            <div class="card">
                <h3>Instrucciones del Sistema (Prompt Manual)</h3>
                <p class="label">Aquí puedes editar manualmente el comportamiento del bot.</p>
                <?php if(isset($success_msg)) echo "<p style='color:green'>$success_msg</p>"; ?>
                <?php if(isset($error_msg)) echo "<p style='color:red'>$error_msg</p>"; ?>
                <form method="POST">
                    <textarea name="system_prompt" rows="15"><?php echo htmlspecialchars($prompt_content); ?></textarea>
                    <div style="margin-top:15px">
                        <button type="submit" name="save_config" class="btn">Guardar Instrucciones</button>
                    </div>
                </form>
                </form>
            </div>
        <?php elseif (isset($_GET['view']) && $_GET['view'] === 'knowledge'): 
            $files = array_diff(scandir(__DIR__ . '/knowledge'), array('.', '..', '.htaccess'));
            ?>
            <h1>📚 Base de Conocimientos</h1>
            <div class="card">
                <h3>Subir Documento (.txt, .csv, .md)</h3>
                <p class="label">El bot usará la información de estos archivos para responder a los clientes.</p>
                <?php if(isset($success_msg)) echo "<p style='color:green'>$success_msg</p>"; ?>
                <?php if(isset($error_msg)) echo "<p style='color:red'>$error_msg</p>"; ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="knowledge_file" accept=".txt,.csv,.md" required style="margin-bottom:15px;">
                    <br>
                    <button type="submit" name="upload_file" class="btn">Subir Archivo</button>
                </form>
                <hr>
                <form method="POST">
                    <button type="submit" name="sync_knowledge" class="btn" style="background:var(--primary); width:100%;">⚡ Sincronizar Cerebro (Indexar todo)</button>
                    <p class="label" style="text-align:center; margin-top:5px;">Pulsa este botón después de subir o borrar archivos para que el bot se actualice.</p>
                </form>
            </div>
            
            <div class="card">
                <h3>Archivos Actuales</h3>
                <table>
                    <thead><tr><th>Archivo</th><th>Acción</th></tr></thead>
                    <tbody>
                        <?php foreach ($files as $f): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($f); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="file_name" value="<?php echo htmlspecialchars($f); ?>">
                                    <button type="submit" name="delete_file" class="btn" style="background:#f44336;">Borrar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($files)) echo "<tr><td colspan='2'>No hay archivos subidos.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (isset($_GET['view']) && $_GET['view'] === 'inventory'): 
            $inventory = $pdo->query("SELECT * FROM inventory ORDER BY id DESC")->fetchAll();
            ?>
            <h1>📦 Inventario de Productos</h1>
            <div class="card">
                <h3>Añadir / Editar Producto</h3>
                <p class="label">El bot podrá ofrecer estos productos y leer sus precios en tiempo real.</p>
                <?php if(isset($success_msg)) echo "<p style='color:green'>$success_msg</p>"; ?>
                <form method="POST">
                    <input type="hidden" name="item_id" id="inv_id" value="">
                    
                    <label class="label">Nombre del Producto/Servicio</label>
                    <input type="text" name="item_name" id="inv_name" required style="width:100%; padding:10px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">
                    
                    <label class="label">Descripción breve</label>
                    <textarea name="description" id="inv_desc" rows="2" style="margin-bottom:15px;"></textarea>
                    
                    <div style="display:flex; gap:15px;">
                        <div style="flex:1;">
                            <label class="label">Precio ($)</label>
                            <input type="number" step="0.01" name="price" id="inv_price" value="0.00" style="width:100%; padding:10px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">
                        </div>
                        <div style="flex:1;">
                            <label class="label">Stock</label>
                            <input type="number" name="stock" id="inv_stock" value="0" style="width:100%; padding:10px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">
                        </div>
                    </div>
                    
                    <button type="submit" name="save_inventory" class="btn" style="background:var(--primary);">Guardar Producto</button>
                    <button type="button" class="btn secondary" onclick="document.getElementById('inv_id').value=''; document.getElementById('inv_name').value=''; document.getElementById('inv_desc').value=''; document.getElementById('inv_price').value='0.00'; document.getElementById('inv_stock').value='0';">Limpiar Formulario</button>
                </form>
            </div>
            
            <div class="card">
                <h3>Lista de Productos</h3>
                <table>
                    <thead><tr><th>ID</th><th>Nombre / Descripción</th><th>Precio</th><th>Stock</th><th>Acción</th></tr></thead>
                    <tbody>
                        <?php foreach ($inventory as $i): ?>
                        <tr>
                            <td><?php echo $i['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($i['item_name']); ?></strong><br>
                                <small style="color:var(--muted)"><?php echo htmlspecialchars($i['description']); ?></small>
                            </td>
                            <td>$<?php echo number_format($i['price'], 2); ?></td>
                            <td><?php echo $i['stock']; ?></td>
                            <td>
                                <button type="button" class="btn secondary" onclick="document.getElementById('inv_id').value='<?php echo $i['id']; ?>'; document.getElementById('inv_name').value='<?php echo addslashes(htmlspecialchars($i['item_name'])); ?>'; document.getElementById('inv_desc').value='<?php echo addslashes(htmlspecialchars($i['description'])); ?>'; document.getElementById('inv_price').value='<?php echo $i['price']; ?>'; document.getElementById('inv_stock').value='<?php echo $i['stock']; ?>';">Editar</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas borrar este producto?');">
                                    <input type="hidden" name="item_id" value="<?php echo $i['id']; ?>">
                                    <button type="submit" name="delete_inventory" class="btn" style="background:#f44336;">Borrar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($inventory)) echo "<tr><td colspan='5'>No hay productos registrados.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (isset($_GET['view']) && $_GET['view'] === 'logs'): 
            $logs = @file_get_contents(__DIR__ . '/debug.log') ?: "No hay registros aún.";
            $logLines = array_reverse(explode("\n", trim($logs)));
            $lastLogs = array_slice($logLines, 0, 50);
            ?>
            <h1>📋 Logs de Sistema</h1>
            <div class="card">
                <h3>Últimos 50 eventos</h3>
                <p class="label">Historial de depuración (Meta, Groq y errores).</p>
                <div style="background:#1e1e1e; color:#d4d4d4; padding:15px; border-radius:8px; font-family:monospace; font-size:12px; height:500px; overflow-y:auto; line-height:1.6;">
                    <?php foreach ($lastLogs as $line): 
                        if (empty($line)) continue;
                        $color = "#d4d4d4";
                        if (strpos($line, 'ERROR') !== false) $color = "#f44336";
                        if (strpos($line, 'ÉXITO') !== false) $color = "#4caf50";
                        if (strpos($line, 'GROQ') !== false) $color = "#2196f3";
                    ?>
                        <div style="color:<?php echo $color; ?>; border-bottom:1px solid #333; padding:4px 0;">
                            <?php echo htmlspecialchars($line); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:15px">
                    <a href="?view=logs" class="btn">Refrescar Logs</a>
                </div>
            </div>
        <?php elseif (isset($_GET['view']) && $_GET['view'] === 'leads'): 
            $allLeads = $pdo->query("SELECT * FROM leads ORDER BY created_at DESC")->fetchAll();
            ?>
            <h1>👥 Gestión de Leads</h1>
            <div class="card">
                <h3>Prospectos Detectados</h3>
                <p class="label">Lista de usuarios calificados por la IA.</p>
                <table>
                    <thead>
                        <tr><th>WhatsApp</th><th>Nombre/Negocio</th><th>Resumen Conversación</th><th>Solicitud Cliente</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allLeads as $l): ?>
                        <tr>
                            <td><?php echo $l['wa_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($l['nombre'] ?: 'Sin nombre'); ?></strong><br>
                                <small style="color:var(--muted)"><?php echo htmlspecialchars($l['negocio'] ?: 'Sin negocio'); ?></small>
                            </td>
                            <td style="font-size: 13px; max-width: 250px;"><?php echo htmlspecialchars($l['resumen'] ?: 'N/A'); ?></td>
                            <td style="font-size: 13px; max-width: 250px; color: var(--primary);">
                                <strong><?php echo htmlspecialchars($l['solicitud'] ?: 'N/A'); ?></strong>
                            </td>
                            <td>
                                <span class="badge" style="background: <?php echo $l['qualification_status'] === 'calificado' ? '#d4edda' : '#fff3cd'; ?>; color: <?php echo $l['qualification_status'] === 'calificado' ? '#155724' : '#856404'; ?>">
                                    <?php echo strtoupper($l['qualification_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (isset($_GET['view']) && $_GET['view'] === 'api'): ?>
            <h1>🔑 APIs y Credenciales</h1>
            <div class="card">
                <h3>Tokens de Conexión</h3>
                <p class="label">Configura las llaves maestras de WhatsApp y Groq.</p>
                <?php if(isset($success_msg)) echo "<p style='color:green'>$success_msg</p>"; ?>
                <form method="POST">
                    <label class="label">WhatsApp API Token</label>
                    <input type="text" name="wa_token" value="<?php echo htmlspecialchars($_ENV['WHATSAPP_API_TOKEN'] ?? getenv('WHATSAPP_API_TOKEN')); ?>" style="width:100%; padding:10px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">
                    
                    <label class="label">WhatsApp Phone Number ID</label>
                    <input type="text" name="wa_phone_id" value="<?php echo htmlspecialchars($_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? getenv('WHATSAPP_PHONE_NUMBER_ID')); ?>" style="width:100%; padding:10px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">
                    
                    <hr style="border:0; border-top:1px solid #eee; margin:20px 0;">
                    
                    <label class="label">Groq API Key (gs_...)</label>
                    <input type="text" name="groq_key" value="<?php echo htmlspecialchars($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY')); ?>" style="width:100%; padding:10px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">
                    
                    <label class="label">Modelo de Texto Principal</label>
                    <input type="text" name="text_model" value="<?php echo htmlspecialchars($_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL')); ?>" style="width:100%; padding:10px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">

                    <div style="margin-top:15px">
                        <button type="submit" name="save_api" class="btn">Guardar Credenciales</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <h1>📊 Dashboard</h1>
            <div class="grid">
                <div class="card"><div class="label">Mensajes</div><div class="kpi"><?php echo $totalMsgs; ?></div></div>
                <div class="card"><div class="label">Leads Totales</div><div class="kpi"><?php echo $totalLeads; ?></div></div>
                <div class="card"><div class="label">Calificados</div><div class="kpi" style="color:var(--primary)"><?php echo $qualifiedLeads; ?></div></div>
            </div>

            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h3>Conversaciones Recientes</h3>
                    <form method="GET" style="display:flex; gap:10px;">
                        <input type="text" name="search" placeholder="Buscar por ID..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="padding:6px; border:1px solid #ddd; border-radius:4px;">
                        <button type="submit" class="btn">🔍</button>
                    </form>
                </div>
                <table>
                    <thead>
                        <tr><th>WA_ID</th><th>Última Actividad</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
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

                        foreach ($threads as $t): ?>
                        <tr>
                            <td><?php echo $t['wa_id']; ?></td>
                            <td><?php echo $t['last_msg']; ?></td>
                            <td><a href="?chat=<?php echo $t['wa_id']; ?>" class="btn secondary">Ver Chat</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (isset($_GET['chat'])): 
                $chatId = $_GET['chat'];
                $messages = $pdo->prepare("SELECT * FROM messages WHERE wa_id = ? ORDER BY created_at ASC LIMIT 50");
                $messages->execute([$chatId]);
                ?>
                <div class="card" id="chat-view">
                    <h3>Chat con <?php echo htmlspecialchars($chatId); ?></h3>
                    <div style="height: 400px; overflow-y: scroll; background: #f9f9f9; padding: 15px; border-radius: 8px;">
                        <?php foreach ($messages as $m): 
                            $isMedia = (strpos($m['content'], '[Imagen]') !== false || strpos($m['content'], '[Audio') !== false);
                        ?>
                            <div style="margin-bottom: 15px; text-align: <?php echo $m['role'] === 'user' ? 'left' : 'right'; ?>">
                                <div style="display: inline-block; padding: 10px; border-radius: 8px; 
                                    background: <?php echo $m['role'] === 'user' ? ($isMedia ? '#fff3cd' : '#eee') : '#176b5b'; ?>; 
                                    color: <?php echo $m['role'] === 'user' ? '#000' : '#fff'; ?>; 
                                    border: <?php echo $isMedia ? '1px solid #ffeeba' : 'none'; ?>;
                                    max-width: 80%; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    
                                    <?php if ($isMedia): ?>
                                        <small style="display:block; margin-bottom:5px; opacity:0.7;">📎 Multimedia</small>
                                    <?php endif; ?>

                                    <?php echo nl2br(htmlspecialchars($m['content'])); ?>
                                </div>
                                <div style="font-size:10px; color:var(--muted); margin-top:4px;">
                                    <?php echo date('H:i', strtotime($m['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
    </main>
</body>
</html>
