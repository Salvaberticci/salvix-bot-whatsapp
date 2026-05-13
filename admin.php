<?php
require_once __DIR__ . '/db.php';
session_start();

// 1. Autenticación Simple
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === getenv('ADMIN_USER') && $_POST['password'] === getenv('ADMIN_PASSWORD')) {
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
            <div class="card">
                <h3>Instrucciones del Sistema (Prompt)</h3>
                <p class="label">Aquí defines cómo debe comportarse el bot, su personalidad y sus reglas.</p>
                <?php if(isset($success_msg)) echo "<p style='color:green'>$success_msg</p>"; ?>
                <form method="POST">
                    <textarea name="system_prompt" rows="15"><?php echo htmlspecialchars($prompt_content); ?></textarea>
                    <div style="margin-top:15px">
                        <button type="submit" name="save_config" class="btn">Guardar Instrucciones</button>
                    </div>
                </form>
                </form>
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
                        <tr><th>WhatsApp</th><th>Nombre</th><th>Negocio</th><th>Estado</th><th>Fecha</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allLeads as $l): ?>
                        <tr>
                            <td><?php echo $l['wa_id']; ?></td>
                            <td><?php echo htmlspecialchars($l['nombre'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($l['negocio'] ?: 'N/A'); ?></td>
                            <td>
                                <span class="badge" style="background: <?php echo $l['qualification_status'] === 'calificado' ? '#d4edda' : '#fff3cd'; ?>; color: <?php echo $l['qualification_status'] === 'calificado' ? '#155724' : '#856404'; ?>">
                                    <?php echo strtoupper($l['qualification_status']); ?>
                                </span>
                            </td>
                            <td><?php echo $l['created_at']; ?></td>
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
                    <input type="text" name="wa_token" value="<?php echo htmlspecialchars(getenv('WHATSAPP_API_TOKEN')); ?>" style="width:100%; padding:10px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">
                    
                    <label class="label">WhatsApp Phone Number ID</label>
                    <input type="text" name="wa_phone_id" value="<?php echo htmlspecialchars(getenv('WHATSAPP_PHONE_NUMBER_ID')); ?>" style="width:100%; padding:10px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">
                    
                    <hr style="border:0; border-top:1px solid #eee; margin:20px 0;">
                    
                    <label class="label">Groq API Key (gs_...)</label>
                    <input type="text" name="groq_key" value="<?php echo htmlspecialchars(getenv('OPENAI_API_KEY')); ?>" style="width:100%; padding:10px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">
                    
                    <label class="label">Modelo de Texto Principal</label>
                    <input type="text" name="text_model" value="<?php echo htmlspecialchars(getenv('OPENAI_MODEL')); ?>" style="width:100%; padding:10px; margin-bottom:15px; border-radius:6px; border:1px solid #ddd;">

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
