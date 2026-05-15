<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/leads.php';

/**
 * Webhook principal para WhatsApp en PHP
 */

// Cazador de errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $date = date('Y-m-d H:i:s');
        file_put_contents(__DIR__ . '/debug.log', "[$date] FATAL ERROR: {$error['message']} en {$error['file']}:{$error['line']}\n", FILE_APPEND);
    }
});

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

// LOGGER RAW: Esto guardará TODO lo que llegue, sin filtros.
if ($input) {
    logger("RAW INPUT: " . $input);
} else {
    // Si no hay input pero es un POST, podría ser un error de servidor
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        logger("POST recibido pero VACÍO. IP: " . $_SERVER['REMOTE_ADDR']);
    }
}

if (!$data) {
    exit;
}

// Extraer información básica del payload de WhatsApp
$entry = $data['entry'][0] ?? null;
$changes = $entry['changes'][0] ?? null;
$value = $changes['value'] ?? null;
$message = $value['messages'][0] ?? null;

if ($message) {
    $wa_id = $message['from'];
    $msg_id = $message['id'];
    $type = $message['type'];
    $text = "";
    $reply = null;
    $history = [];

    try {
        logger("CONECTANDO A DB...");
        $pdo = getDB();
        logger("DB CONECTADA.");

        // A. PROCESAR SEGÚN EL TIPO DE MENSAJE
        if ($type === 'text') {
            $text = $message['text']['body'];
        } 
        elseif ($type === 'image') {
            $mediaId = $message['image']['id'];
            $caption = $message['image']['caption'] ?? "Analiza esta imagen y responde al usuario";
            $tmpFile = downloadMetaMedia($mediaId);
            if ($tmpFile) {
                logger("Procesando imagen descargada: Obteniendo historial...");
                // Obtenemos historial previo
                $stmt = $pdo->prepare("SELECT role, content FROM messages WHERE wa_id = ? ORDER BY created_at DESC LIMIT 5");
                $stmt->execute([$wa_id]);
                $history = array_reverse($stmt->fetchAll());
                
                logger("Historial obtenido. Llamando a analyzeImage...");
                // El modelo de visión genera la respuesta FINAL directamente
                $reply = analyzeImage($tmpFile, $caption, $history);
                logger("Respuesta de visión obtenida.");
                $text = "[Imagen]: " . $caption; // Texto que guardaremos en DB
                @unlink($tmpFile);
            } else {
                $reply = "Lo siento, no pude procesar tu imagen. Es posible que el token de conexión haya caducado. Por favor, contacta al administrador.";
                $text = "[Error descarga imagen]";
            }
        } 
        elseif ($type === 'audio') {
            $mediaId = $message['audio']['id'];
            $tmpFile = downloadMetaMedia($mediaId);
            if ($tmpFile) {
                $transcript = transcribeAudio($tmpFile);
                $text = "[Audio transcrito]: " . ($transcript ?: "No se pudo entender el audio.");
                @unlink($tmpFile); // Borrar temporal
            } else {
                $reply = "Lo siento, no pude procesar tu mensaje de voz. El token podría estar vencido.";
                $text = "[Error descarga audio]";
            }
        }

        if (!$text) exit;

        // B. FLUJO NORMAL DE RESPUESTA
        // 1. Guardar mensaje del usuario
        $stmt = $pdo->prepare("INSERT INTO messages (wa_id, role, content, message_id) VALUES (?, 'user', ?, ?)");
        $stmt->execute([$wa_id, $text, $msg_id]);

        // 2. Generar respuesta (Groq) - Solo si no se generó ya por visión o audio
        if (!isset($reply) || empty($reply)) {
            // Obtener historial
            $stmt = $pdo->prepare("SELECT role, content FROM messages WHERE wa_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$wa_id]);
            $history = array_reverse($stmt->fetchAll());
            logger("LLAMANDO A IA (Groq)...");
            $reply = completeChat($text, $history);
            logger("IA RESPONDIÓ.");
        }

        // 3. Procesar Leads y limpiar
        processLeads($wa_id, $reply, $history);
        $cleanReply = cleanReply($reply);

        // 5. Enviar a WhatsApp
        logger("PREPARANDO ENVÍO A $wa_id: $cleanReply");
        sendWhatsAppText($wa_id, $cleanReply);

        // 6. Guardar respuesta del bot
        $stmt = $pdo->prepare("INSERT INTO messages (wa_id, role, content) VALUES (?, 'assistant', ?)");
        $stmt->execute([$wa_id, $cleanReply]);

    } catch (Exception $e) {
        error_log("Error en Webhook Multimedia: " . $e->getMessage());
    }
}

// Meta requiere siempre un 200 OK para no reintentar el mensaje
http_response_code(200);
echo "OK";
