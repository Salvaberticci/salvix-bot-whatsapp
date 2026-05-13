<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/leads.php';

/**
 * Webhook principal para WhatsApp en PHP
 */

// 1. Verificación del Webhook (Handshake de Meta)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && $token === WA_VERIFY_TOKEN) {
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// 2. Procesamiento de Mensajes (POST)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    exit;
}

// Extraer información básica del payload de WhatsApp
$entry = $data['entry'][0] ?? null;
$changes = $entry['changes'][0] ?? null;
$value = $changes['value'] ?? null;
$message = $value['messages'][0] ?? null;

if ($message && isset($message['text']['body'])) {
    $wa_id = $message['from'];
    $text = $message['text']['body'];
    $msg_id = $message['id'];

    try {
        $pdo = getDB();

        // 1. Guardar mensaje del usuario en la base de datos
        $stmt = $pdo->prepare("INSERT INTO messages (wa_id, role, content, message_id) VALUES (?, 'user', ?, ?)");
        $stmt->execute([$wa_id, $text, $msg_id]);

        // 2. Obtener historial reciente para dar contexto a la IA
        $stmt = $pdo->prepare("SELECT role, content FROM messages WHERE wa_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$wa_id]);
        $history = array_reverse($stmt->fetchAll());

        // 3. Generar respuesta con Groq
        $reply = completeChat($text, $history);

        // 4. Procesar Leads y limpiar marcadores
        processLeads($wa_id, $reply, $history);
        $cleanReply = cleanReply($reply);

        // 5. Enviar respuesta limpia por WhatsApp
        sendWhatsAppText($wa_id, $cleanReply);

        // 6. Guardar respuesta del bot en la base de datos
        $stmt = $pdo->prepare("INSERT INTO messages (wa_id, role, content) VALUES (?, 'assistant', ?)");
        $stmt->execute([$wa_id, $cleanReply]);

    } catch (Exception $e) {
        error_log("Error en Webhook: " . $e->getMessage());
    }
}

// Meta requiere siempre un 200 OK para no reintentar el mensaje
http_response_code(200);
echo "OK";
