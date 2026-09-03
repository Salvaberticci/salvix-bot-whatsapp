<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tenants.php';
require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/instagram.php';
require_once __DIR__ . '/leads.php';
require_once __DIR__ . '/orders.php';

/**
 * Webhook principal para WhatsApp en PHP
 */

/**
 * Divide un mensaje largo en fragmentos naturales para simular escritura humana.
 * Cada fragmento tiene un máximo de ~200 caracteres y se corta por oraciones o saltos de línea.
 */
function splitMessage($text) {
    $maxLen = 1000;
    $text = trim($text);
    if (strlen($text) <= $maxLen) {
        return [$text];
    }

    $chunks = [];
    $parts = preg_split('/\n\n+/', $text);

    foreach ($parts as $part) {
        $part = trim($part);
        if (!$part) continue;
        if (strlen($part) <= $maxLen) {
            $chunks[] = $part;
        } else {
            $sentences = preg_split('/(?<=[.!?])\s+/', $part);
            $buffer = '';
            foreach ($sentences as $s) {
                $s = trim($s);
                if (!$s) continue;
                if (strlen($buffer . ' ' . $s) <= $maxLen) {
                    $buffer = trim($buffer . ' ' . $s);
                } else {
                    if ($buffer) $chunks[] = $buffer;
                    $buffer = $s;
                }
            }
            if ($buffer) $chunks[] = $buffer;
        }
    }

    if (empty($chunks)) return [$text];

    if (count($chunks) > 1) {
        $merged = implode("\n\n", $chunks);
        if (strlen($merged) <= $maxLen) {
            return [$merged];
        }
    }

    return $chunks;
}

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

// Detectar plataforma
$platform = $data['object'] ?? 'unknown';

// ===== INSTAGRAM =====
if ($platform === 'instagram') {
    $entry = $data['entry'][0] ?? null;
    $messaging = $entry['messaging'][0] ?? null;

    if (!$messaging) {
        // Evento que no es mensaje (reacciones, postbacks, etc.)
        http_response_code(200);
        echo "OK";
        exit;
    }

    $igAccountId = $entry['id'] ?? null;  // ID de la cuenta IG del negocio
    $senderId = $messaging['sender']['id'] ?? null;  // IGSID del usuario
    $igMessage = $messaging['message'] ?? null;
    $text = $igMessage['text'] ?? null;
    $msgId = $igMessage['mid'] ?? null;

    if (!$senderId || !$text) {
        // Mensaje sin texto o sin remitente (stickers, reacciones, etc.)
        http_response_code(200);
        echo "OK";
        exit;
    }

    logger("RAW INPUT IG: " . $input);

    @set_time_limit(90);

    try {
        // Resolver tenant por ig_account_id
        $pdo = getBaseDB();
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE ig_account_id = ?");
        $stmt->execute([$igAccountId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            logger("MENSAJE IG DE CUENTA SIN TENANT: ig_account_id=$igAccountId de $senderId. Ignorado.");
            http_response_code(200);
            echo "OK";
            exit;
        }
        $GLOBALS['TENANT'] = $tenant;
        logger("TENANT RESUELTO (IG): {$tenant['slug']} para ig_account_id=$igAccountId");

        // Conectar a BD del tenant
        $pdo = getDB();
        logger("DB CONECTADA.");

        // Dedupe por msg_id (si existe)
        if ($msgId) {
            $stmt = $pdo->prepare("SELECT id FROM messages WHERE message_id = ?");
            $stmt->execute([$msgId]);
            if ($stmt->fetch()) {
                logger("MENSAJE IG YA PROCESADO: $msgId");
                http_response_code(200);
                echo "OK";
                exit;
            }
        }

        // Responder 200 rápido
        if (function_exists('fastcgi_finish_request')) {
            ignore_user_abort(true);
            http_response_code(200);
            header('Content-Type: text/plain');
            echo "OK";
            fastcgi_finish_request();
        }

        // Typing indicator
        $igToken = $tenant['ig_token'] ?? '';
        if ($igToken) sendInstagramAction($senderId, 'typing_on', $igToken);

        // Guardar mensaje del usuario
        $stmt = $pdo->prepare("INSERT INTO messages (wa_id, role, content, message_id) VALUES (?, 'user', ?, ?)");
        $stmt->execute([$senderId, $text, $msgId]);

        // Obtener historial
        $stmt = $pdo->prepare("SELECT role, content FROM messages WHERE wa_id = ? ORDER BY created_at DESC LIMIT 6");
        $stmt->execute([$senderId]);
        $history = array_reverse($stmt->fetchAll());

        // Flujo de pedidos (mismo que WhatsApp)
        $orderContext = processOrderFlow($pdo, $senderId, $text, $history);

        // IA
        $usePremium = ($orderContext !== null)
            || (bool)preg_match('/pago|pagar|pagarte|cancelar|cuenta|banco|comprobante|reclamo|queja/i', $text);
        $aiText = $text;
        if ($orderContext) {
            $aiText .= "\n\n[NOTA INTERNA (contexto real del pedido; úsalo para responder con total naturalidad, NO lo menciones ni lo repitas textualmente): " . $orderContext . "]";
        }

        logger("LLAMANDO A IA (Groq) para IG...");
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $usePremiumNow = $usePremium && $attempt <= 2;
            $reply = completeChat($aiText, $history, $usePremiumNow);
            $r = trim((string)$reply);
            $failed = ($r === '' || strpos($r, 'Uy, se me cortó') === 0 || strpos($r, 'No pude obtener') === 0);
            if (!$failed) break;
            if ($attempt < 3) sleep(2);
        }
        logger("IA RESPONDIÓ (IG).");

        // Limpiar respuesta
        $cleanReply = cleanReply($reply);
        $cleanReply = deVoseo($cleanReply);
        if (trim($cleanReply) === '') {
            $cleanReply = "Perdón, me quedé en blanco un segundito. ¿Me repites lo que necesitas?";
        }

        // Enviar (Instagram: sin fragmentos, mensaje único de hasta 2000 chars)
        if ($igToken) {
            sleep(1);
            sendInstagramAction($senderId, 'typing_on', $igToken);
            usleep(800000);
            sendInstagramMessage($senderId, $cleanReply, $igToken);
            sendInstagramAction($senderId, 'typing_off', $igToken);
        }

        // Guardar respuesta
        $stmt = $pdo->prepare("INSERT INTO messages (wa_id, role, content) VALUES (?, 'assistant', ?)");
        $stmt->execute([$senderId, $cleanReply]);

        logger("RESPUESTA IG ENVIADA A $senderId");

    } catch (Exception $e) {
        logger("FATAL ERROR IG: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    }

    http_response_code(200);
    echo "OK";
    exit;
}

// ===== WHATAPP (payload original) =====
// Extraer información básica del payload de WhatsApp
$entry = $data['entry'][0] ?? null;
$changes = $entry['changes'][0] ?? null;
$value = $changes['value'] ?? null;
$message = $value['messages'][0] ?? null;

// MULTI-TENANT: identificar qué cliente (número) envió el mensaje.
// Todos los números de todos los clientes comparten esta misma webhook.
$phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

if ($message) {
    $wa_id = $message['from'];
    $msg_id = $message['id'];
    $type = $message['type'];
    $text = "";
    $reply = null;
    $history = [];

    // Ampliar límite de tiempo para procesar sin cortar
    @set_time_limit(90);

    try {
        // Resolver el tenant por phone_number_id
        $tenant = getTenantByPhoneId($phoneNumberId);
        if (!$tenant) {
            logger("MENSAJE DE NÚMERO SIN TENANT: phone_number_id=$phoneNumberId de $wa_id. Ignorado.");
            http_response_code(200);
            echo "OK";
            exit;
        }
        $GLOBALS['TENANT'] = $tenant;
        logger("TENANT RESUELTO: {$tenant['slug']} ({$tenant['nombre']}) para phone_number_id=$phoneNumberId");

        logger("CONECTANDO A DB...");
        $pdo = getDB();
        logger("DB CONECTADA.");

        // ANTES DE NADA: evitar reintentos de Meta (dedupe por message_id)
        $stmt = $pdo->prepare("SELECT id FROM messages WHERE message_id = ?");
        $stmt->execute([$msg_id]);
        if ($stmt->fetch()) {
            logger("⚠️ MENSAJE YA PROCESADO ANTERIORMENTE (retry de Meta), omitiendo: $msg_id");
            http_response_code(200);
            echo "OK";
            exit;
        }

        // Responder 200 lo antes posible para que Meta NO reintente el webhook,
        // y seguir procesando en segundo plano cuando el servidor lo permita.
        if (function_exists('fastcgi_finish_request')) {
            ignore_user_abort(true);
            http_response_code(200);
            header('Content-Type: text/plain');
            echo "OK";
            fastcgi_finish_request();
        }

        // Mostrar "escribiendo..." de inmediato para todos los tipos de mensaje
        sendAction($wa_id, 'typing_on', $phoneNumberId);

        // Imagen adjunta del cliente (data URI) para mostrarla en el chat del dashboard
        $msgImageData = null;

        // A. PROCESAR SEGÚN EL TIPO DE MENSAJE
        if ($type === 'text') {
            $text = $message['text']['body'];
        } 
        elseif ($type === 'image') {
            $mediaId = $message['image']['id'];
            $caption = $message['image']['caption'] ?? "Analiza esta imagen y responde al usuario";

            // Hook de prueba: si el id viene como "local:archivo.png", usar el archivo de tmp/ (no llama a Meta)
            $tmpFile = null;
            if (strpos($mediaId, 'local:') === 0) {
                $localPath = __DIR__ . '/tmp/' . substr($mediaId, 6);
                if (file_exists($localPath)) {
                    $tmpFile = $localPath;
                    logger("DEBUG: imagen local de prueba: $localPath");
                } else {
                    logger("DEBUG: archivo local no encontrado: $localPath");
                }
            } else {
                $tmpFile = downloadMetaMedia($mediaId);
            }

            if ($tmpFile) {
                // ¿El cliente tiene un pedido aprobado? → la imagen es un comprobante de pago
                $paymentReply = processPaymentImage($pdo, $wa_id, $tmpFile, $caption);
                if ($paymentReply !== null) {
                    logger("COMPROBANTE DE PAGO PROCESADO. Respondiendo: " . mb_substr($paymentReply, 0, 80));
                    $reply = $paymentReply;
                    $text = "[Imagen]: " . ($caption !== "Analiza esta imagen y responde al usuario" ? $caption : "captura de comprobante");
                } else {
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
                }
                $mimeType = function_exists('mime_content_type') ? (mime_content_type($tmpFile) ?: 'image/jpeg') : 'image/jpeg';
                $msgImageData = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($tmpFile));
            } else {
                $reply = "Uy, no alcancé a ver tu imagen. ¿Me la mandas de nuevo?";
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
                $reply = "Uy, no alcancé a escuchar tu nota de voz. ¿Me la mandas de nuevo?";
                $text = "[Error descarga audio]";
            }
        }

        if (!$text) exit;

        // B. FLUJO NORMAL DE RESPUESTA
        // 1. Guardar mensaje del usuario
        $stmt = $pdo->prepare("INSERT INTO messages (wa_id, role, content, image_data, message_id) VALUES (?, 'user', ?, ?, ?)");
        $stmt->execute([$wa_id, $text, $msgImageData, $msg_id]);

        // Liberar el archivo temporal de la imagen (los de prueba local: se conservan)
        if ($msgImageData && strpos($mediaId, 'local:') !== 0) @unlink($tmpFile);

        // 2. Flujo de pedidos (restaurante): actualiza la BD y devuelve contexto real
        //    para que la IA responda con datos, no con guiones preescritos.
        logger("PROCESANDO FLUJO DE PEDIDOS...");
        $stmt = $pdo->prepare("SELECT role, content FROM messages WHERE wa_id = ? ORDER BY created_at DESC LIMIT 6");
        $stmt->execute([$wa_id]);
        $history = array_reverse($stmt->fetchAll());
        $orderContext = processOrderFlow($pdo, $wa_id, $text, $history);

        // 3. Generar respuesta (Groq) - Solo si no se generó ya por visión o audio
        if (!isset($reply) || empty($reply)) {
            // Momento crítico (pedido, pago, reclamo): se usa el modelo premium 120b
            $usePremium = ($orderContext !== null)
                || (bool)preg_match('/pago|pagar|pagarte|cancelar|cuenta|banco|comprobante|reclamo|queja/i', $text);
            // Contexto real del pedido inyectado al modelo para que responda natural
            $aiText = $text;
            if ($orderContext) {
                $aiText .= "\n\n[NOTA INTERNA (contexto real del pedido; úsalo para responder con total naturalidad, NO lo menciones ni lo repitas textualmente): " . $orderContext . "]";
            }
            // Reintentos: premium 120b (2 intentos); si falla, se baja al modelo estándar
            logger("LLAMANDO A IA (Groq)...");
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $usePremiumNow = $usePremium && $attempt <= 2;
                $reply = completeChat($aiText, $history, $usePremiumNow);
                $r = trim((string)$reply);
                $failed = ($r === '' || strpos($r, 'Uy, se me cortó') === 0 || strpos($r, 'No pude obtener') === 0);
                if ($attempt > 1) logger("IA intento $attempt: " . ($failed ? 'fallo' : 'OK'));
                if (!$failed) break;
                if ($attempt < 3) sleep(2);
            }
            logger("IA RESPONDIÓ.");
        }

        // 4. Procesar Leads y limpiar
        logger("PROCESANDO LIMPIEZA DE RESPUESTA...");
        $cleanReply = cleanReply($reply);
        $cleanReply = deVoseo($cleanReply);
        if (trim($cleanReply) === '') {
            $cleanReply = "Perdón, me quedé en blanco un segundito. ¿Me repites lo que necesitas?";
        }

        // 5. Simular escritura humana
        $chunks = splitMessage($cleanReply);
        $totalLen = strlen($cleanReply);
        $chunkCount = count($chunks);
        $initialDelay = $chunkCount === 1
            ? min(round($totalLen * 0.04), 3)
            : min(round($totalLen * 0.03), 2);
        if ($initialDelay < 1) $initialDelay = 1;
        sleep($initialDelay);

        // 6. Enviar a WhatsApp
        $fullLog = '';
        foreach ($chunks as $i => $chunk) {
            if ($i > 0) sleep(rand(1, 2));
            sendAction($wa_id, 'typing_on', $phoneNumberId);
            usleep(rand(300000, 900000));
            logger("ENVIANDO FRAGMENTO " . ($i + 1) . "/" . $chunkCount . " a $wa_id");
            sendWhatsAppText($wa_id, $chunk, $phoneNumberId);
            $fullLog .= ($fullLog ? "\n---\n" : '') . $chunk;
        }
        sendAction($wa_id, 'typing_off', $phoneNumberId);
        logger("RESPUESTA COMPLETA ENVIADA A $wa_id (en $chunkCount mensaje" . ($chunkCount > 1 ? 's' : '') . ")");

        // Guardar la respuesta completa en BD
        $stmt = $pdo->prepare("INSERT INTO messages (wa_id, role, content) VALUES (?, 'assistant', ?)");
        $stmt->execute([$wa_id, $cleanReply]);

    } catch (Exception $e) {
        logger("FATAL ERROR en Webhook: " . $e->getMessage() . " en " . $e->getFile() . " línea " . $e->getLine());
    }
}

// Meta requiere siempre un 200 OK para no reintentar el mensaje
http_response_code(200);
echo "OK";
