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

// 2. Lógica del Dashboard
$pdo = getDB();

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
        aside { width: 240px; background: #fbfcfa; border-right: 1px solid #dde2dc; padding: 20px; }
        main { flex: 1; padding: 30px; overflow-y: auto; }
        .card { background: white; padding: 20px; border-radius: 8px; border: 1px solid #dde2dc; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi { font-size: 24px; font-weight: bold; }
        .label { color: var(--muted); font-size: 13px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; }
        th { background: #fafbf9; color: var(--muted); font-size: 12px; }
        .btn { padding: 6px 12px; background: var(--ink); color: white; text-decoration: none; border-radius: 4px; font-size: 13px; }
        .badge { padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: bold; background: #eee; }
        .badge-cal { background: #dff4e8; color: #17643e; }
    </style>
</head>
<body>
    <aside>
        <h2>Salvix</h2>
        <p class="label">Panel PHP</p>
        <hr>
        <nav>
            <p><a href="admin.php" style="color:var(--ink); text-decoration:none; font-weight:bold;">Dashboard</a></p>
            <p><a href="health.php" target="_blank" style="color:var(--muted); text-decoration:none;">Estado Salud</a></p>
            <p><a href="?logout=1" style="color:red; text-decoration:none;">Salir</a></p>
        </nav>
    </aside>
    <main>
        <h1>Dashboard</h1>
        <div class="grid">
            <div class="card"><div class="label">Mensajes</div><div class="kpi"><?php echo $totalMsgs; ?></div></div>
            <div class="card"><div class="label">Leads Totales</div><div class="kpi"><?php echo $totalLeads; ?></div></div>
            <div class="card"><div class="label">Calificados</div><div class="kpi" style="color:var(--primary)"><?php echo $qualifiedLeads; ?></div></div>
        </div>

        <div class="card">
            <h3>Conversaciones Recientes</h3>
            <table>
                <thead>
                    <tr><th>WA_ID</th><th>Última Actividad</th><th>Acción</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($threads as $t): ?>
                    <tr>
                        <td><?php echo $t['wa_id']; ?></td>
                        <td><?php echo $t['last_msg']; ?></td>
                        <td><a href="?chat=<?php echo $t['wa_id']; ?>" class="btn">Ver Chat</a></td>
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
                <div style="height: 300px; overflow-y: scroll; background: #f9f9f9; padding: 15px; border-radius: 8px;">
                    <?php foreach ($messages as $m): ?>
                        <p style="margin: 5px 0; font-size: 14px;">
                            <strong><?php echo $m['role'] === 'user' ? 'Cliente' : 'Bot'; ?>:</strong>
                            <?php echo htmlspecialchars($m['content']); ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
