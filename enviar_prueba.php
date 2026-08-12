<?php
require_once __DIR__ . '/config.php';

/**
 * Enviar Mensaje de Prueba (Meta Tech Provider)
 * 
 * Un solo botón. Envía la plantilla "hello_world" al número de prueba,
 * igual que el cURL del "Try it out tool" de Meta.
 */

$result = null;
$curlOutput = null;

$cfg = [
    'token'  => !empty(WA_TOKEN)  ? 'OK' : 'FALTA',
    'phone'  => !empty(WA_PHONE_ID) ? 'OK' : 'FALTA',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($cfg['token'] !== 'OK' || $cfg['phone'] !== 'OK') {
        $result = ['ok' => false, 'text' => '⚠️ Falta configuración: revisa WHATSAPP_API_TOKEN y WHATSAPP_PHONE_NUMBER_ID en el .env.'];
    } else {
        $to = '584121731842';
        $url = 'https://graph.facebook.com/v25.0/' . WA_PHONE_ID . '/messages';
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => 'hello_world',
                'language' => ['code' => 'en_US']
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . WA_TOKEN
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode === 200) {
            $result = ['ok' => true, 'text' => '✅ Mensaje enviado correctamente a ' . $to . '. Revisa WhatsApp Web.'];
        } else {
            $detail = $data['error']['message'] ?? $response;
            $result = ['ok' => false, 'text' => '❌ Error HTTP ' . $httpCode . ': ' . $detail . ($error ? ' | cURL: ' . $error : '')];
        }
        logger("TEST SEND: HTTP $httpCode -> $to | " . ($result['ok'] ? 'OK' : $result['text']));

        $payloadJson = str_replace("\n", "\n  ", json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $curlOutput  = "> curl -i -X POST \"https://graph.facebook.com/v25.0/" . WA_PHONE_ID . "/messages\" \\\n";
        $curlOutput .= ">   -H 'Authorization: Bearer EA*******" . substr(WA_TOKEN, -6) . "' \\\n";
        $curlOutput .= ">   -H 'Content-Type: application/json' \\\n";
        $curlOutput .= ">   -d '{\n";
        $curlOutput .= ">     \"messaging_product\": \"whatsapp\",\n";
        $curlOutput .= ">     \"to\": \"" . $to . "\",\n";
        $curlOutput .= ">     \"type\": \"template\",\n";
        $curlOutput .= ">     \"template\": { \"name\": \"hello_world\", \"language\": { \"code\": \"en_US\" } }\n";
        $curlOutput .= ">   }'\n\n";
        $curlOutput .= "HTTP/1.1 " . $httpCode . "\n";
        $curlOutput .= $response !== false ? $response : ('cURL error: ' . $error);
        $curlOutput = trim(preg_replace('/EAA[A-Za-z0-9]+/', 'EA*******' . substr(WA_TOKEN, -6), $curlOutput));
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enviar Mensaje de Prueba - Salvix Wireless IA Agent</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #000000;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        body::before {
            content: '';
            position: fixed;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.08) 0%, transparent 70%);
            top: -200px;
            right: -200px;
            pointer-events: none;
        }
        .card {
            position: relative;
            width: 100%;
            max-width: 560px;
            background: rgba(13, 13, 13, 0.92);
            border: 1px solid rgba(42, 42, 42, 0.6);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.4);
            text-align: center;
        }
        .logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            border-radius: 16px;
            overflow: hidden;
        }
        .logo img { width: 100%; height: 100%; object-fit: contain; }
        h1 { font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
        .subtitle { color: #8A8A8A; font-size: 13px; margin: 6px 0 20px; }
        .config-row {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 24px;
        }
        .pill {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }
        .pill.ok { background: rgba(74, 222, 128, 0.12); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.25); }
        .pill.bad { background: rgba(239, 68, 68, 0.12); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.25); }
        .btn {
            width: 100%;
            max-width: 320px;
            padding: 15px;
            background: linear-gradient(135deg, #38bdf8, #7dd3fc);
            border: none;
            border-radius: 12px;
            color: #000;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(56, 189, 248, 0.3); }
        .btn:disabled { opacity: 0.5; cursor: wait; transform: none; }
        .result {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.6;
            word-break: break-word;
            font-weight: 500;
            text-align: left;
        }
        .result.ok {
            background: rgba(74, 222, 128, 0.1);
            border: 1px solid rgba(74, 222, 128, 0.2);
            color: #4ade80;
        }
        .result.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            font-size: 12px;
        }
        .terminal {
            margin-top: 22px;
            background: #0a0a0f;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            overflow: hidden;
            font-family: 'SF Mono', 'Fira Code', Consolas, monospace;
            font-size: 12px;
            line-height: 1.7;
            text-align: left;
        }
        .terminal-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px 14px;
            background: #141414;
            border-bottom: 1px solid #2a2a2a;
        }
        .terminal-bar .dot { width: 10px; height: 10px; border-radius: 50%; }
        .terminal-bar .dot.r { background: #ef4444; }
        .terminal-bar .dot.y { background: #fbbf24; }
        .terminal-bar .dot.g { background: #4ade80; }
        .terminal-bar .title { margin-left: 8px; color: #8A8A8A; font-size: 11px; }
        .terminal-body {
            padding: 14px 16px;
            color: #4ade80;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 400px;
            overflow-y: auto;
        }
        .terminal-body .cmd { color: #38bdf8; }
        .terminal-body .out { color: #8A8A8A; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo"><img src="img/logo.png" alt="Salvix Wireless IA Agent"></div>
        <h1>Enviar Mensaje de Prueba</h1>
        <p class="subtitle">WhatsApp Cloud API - Meta Tech Provider</p>

        <div class="config-row">
            <span class="pill <?php echo $cfg['token'] === 'OK' ? 'ok' : 'bad'; ?>">Token: <?php echo $cfg['token']; ?></span>
            <span class="pill <?php echo $cfg['phone'] === 'OK' ? 'ok' : 'bad'; ?>">Phone ID: <?php echo $cfg['phone']; ?></span>
        </div>

        <?php if ($result): ?>
            <div class="result <?php echo $result['ok'] ? 'ok' : 'error'; ?>" style="margin-bottom:18px;">
                <?php echo htmlspecialchars($result['text']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="testForm">
            <button type="submit" name="send_test" class="btn" id="sendBtn">Enviar Mensaje de Prueba</button>
        </form>

        <div class="terminal">
            <div class="terminal-bar">
                <span class="dot r"></span><span class="dot y"></span><span class="dot g"></span>
                <span class="title">Terminal - envío via API Cloud (curl)</span>
            </div>
            <div class="terminal-body" id="terminalBody">
                <?php if ($curlOutput !== null): ?>
                    <span class="cmd"><?php echo nl2br($curlOutput); ?></span>
                <?php else: ?>
                    <span class="cmd">$ </span><span class="out">Esperando comando... pulsa "Enviar Mensaje de Prueba".</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('testForm').addEventListener('submit', function() {
            var btn = document.getElementById('sendBtn');
            btn.disabled = true;
            btn.textContent = 'Enviando...';
        });
    </script>
</body>
</html>