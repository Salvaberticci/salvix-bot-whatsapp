<?php
require_once __DIR__ . '/config.php';

/**
 * Enviar Mensaje de Prueba (Meta Tech Provider)
 * 
 * Interfaz con un botón que envía un mensaje vía WhatsApp Cloud API.
 * Sirve para grabar el Video 1 del proceso de validación de Meta.
 */

$result = null;
$phone = $_POST['phone'] ?? '';
$message = $_POST['message'] ?? '';
$defaultPhone = $_ENV['TEST_PHONE'] ?? getenv('TEST_PHONE') ?? '';
$defaultMessage = $_ENV['TEST_MESSAGE'] ?? getenv('TEST_MESSAGE') ?? 'Hola {{1}}, tu solicitud se ha procesado con éxito.';

// Estado de configuración para diagnóstico
$cfg = [
    'token'  => !empty(WA_TOKEN)  ? 'OK' : 'FALTA',
    'phone'  => !empty(WA_PHONE_ID) ? 'OK' : 'FALTA',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $phone = preg_replace('/\D/', '', $phone);
    $message = trim($message);

    if (empty($phone) && empty($message)) {
        $result = ['ok' => false, 'text' => '⚠️ Escribe tu número de WhatsApp y un mensaje antes de enviar.'];
    } elseif (empty($phone)) {
        $result = ['ok' => false, 'text' => '⚠️ Escribe tu número de WhatsApp con código de país (ej: 584121234567).'];
    } elseif (empty($message)) {
        $result = ['ok' => false, 'text' => '⚠️ Escribe un mensaje.'];
    } elseif (strlen($phone) < 10) {
        $result = ['ok' => false, 'text' => '⚠️ El número debe incluir código de país (ej: 584121234567).'];
    } elseif ($cfg['token'] !== 'OK' || $cfg['phone'] !== 'OK') {
        $result = ['ok' => false, 'text' => '⚠️ Falta configuración: revisa WHATSAPP_API_TOKEN y WHATSAPP_PHONE_NUMBER_ID en el .env.'];
    } else {
        $url = 'https://graph.facebook.com/v25.0/' . WA_PHONE_ID . '/messages';
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $message]
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
            $result = ['ok' => true, 'text' => '✅ Mensaje enviado correctamente a ' . $phone . '. Revisa WhatsApp Web.'];
        } else {
            $detail = $data['error']['message'] ?? $response;
            $result = ['ok' => false, 'text' => '❌ Error HTTP ' . $httpCode . ': ' . $detail . ($error ? ' | cURL: ' . $error : '')];
        }
        logger("TEST SEND: HTTP $httpCode -> $phone | " . ($result['ok'] ? 'OK' : $result['text']));
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
            max-width: 480px;
            background: rgba(13, 13, 13, 0.92);
            border: 1px solid rgba(42, 42, 42, 0.6);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.4);
        }
        .logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            overflow: hidden;
        }
        .logo img { width: 100%; height: 100%; object-fit: contain; }
        h1 { font-size: 20px; font-weight: 700; text-align: center; letter-spacing: -0.3px; }
        .subtitle { text-align: center; color: #8A8A8A; font-size: 13px; margin: 6px 0 20px; }
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
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            color: #8A8A8A;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }
        .form-group input, .form-group textarea {
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
        .form-group textarea { resize: vertical; min-height: 110px; line-height: 1.6; }
        .form-group input:focus, .form-group textarea:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }
        .btn {
            width: 100%;
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
            <div class="form-group">
                <label>Número de WhatsApp (con código de país)</label>
                <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($phone ?: $defaultPhone); ?>" placeholder="Ej: 584121234567">
            </div>
            <div class="form-group">
                <label>Mensaje</label>
                <textarea name="message" id="message" placeholder="Escribe aquí el mensaje de prueba..."><?php echo htmlspecialchars($message ?: $defaultMessage); ?></textarea>
            </div>
            <button type="submit" name="send_test" class="btn" id="sendBtn">Enviar Mensaje de Prueba</button>
        </form>
    </div>

    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            var phone = document.getElementById('phone').value.trim();
            var msg = document.getElementById('message').value.trim();
            if (!phone || !msg) {
                e.preventDefault();
                alert('⚠️ Escribe tu número de WhatsApp y un mensaje antes de enviar.');
                return;
            }
            var btn = document.getElementById('sendBtn');
            btn.disabled = true;
            btn.textContent = 'Enviando...';
        });
    </script>
</body>
</html>