<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/whatsapp.php';

if (!function_exists('splitMessage')) {
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
}

/**
 * Módulo de Pedidos para restaurantes.
 * - Captura pedidos de delivery desde la conversación del chat.
 * - Flujo: nuevo -> aprobado -> en_verificacion -> pagado -> en_camino -> entregado (o cancelado).
 * - El staff aprueba y fija el total desde el panel; el sistema envía el mensaje
 *   con el total y los métodos de pago al cliente.
 */

function getSetting($pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return ($v !== false && $v !== null) ? $v : $default;
}

function setSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
    $stmt->execute([$key, $value, $value]);
}

function getPaymentMethods($pdo) {
    $raw = getSetting($pdo, 'payment_methods', '{}');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function savePaymentMethods($pdo, $methods) {
    setSetting($pdo, 'payment_methods', json_encode($methods, JSON_UNESCAPED_UNICODE));
}

function getOrderStatusLabel($status) {
    $labels = [
        'nuevo'           => 'Nuevo',
        'aprobado'        => 'Aprobado',
        'en_verificacion' => 'En verificación de pago',
        'pagado'          => 'Pagado',
        'en_camino'       => 'En camino',
        'entregado'       => 'Entregado',
        'cancelado'       => 'Cancelado',
    ];
    return $labels[$status] ?? $status;
}

function nextOrderNumber($pdo) {
    $prefix = getSetting($pdo, 'order_prefix', 'P');
    $n = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() + 1;
    return $prefix . '-' . str_pad($n, 4, '0', STR_PAD_LEFT);
}

/**
 * Guarda en el historial los mensajes enviados desde el panel (aprobación, pagos, estados),
 * para que el AI tenga contexto completo de la conversación y no repita totales ni métodos.
 */
function saveAssistantMessage($pdo, $waId, $content) {
    try {
        $stmt = $pdo->prepare("INSERT INTO messages (wa_id, role, content) VALUES (?, 'assistant', ?)");
        $stmt->execute([$waId, $content]);
    } catch (Exception $e) {
        logger("WARN guardando mensaje de panel en historial: " . $e->getMessage());
    }
}

/**
 * Corrige voseo rioplatense a español venezolano con "tú" de forma determinista,
 * como red de seguridad sobre la regla del prompt.
 */
function deVoseo($text) {
    $map = [
        '/\bquer[ée]s\b/iu' => 'quieres',
        '/\bpod[ée]s\b/iu'  => 'puedes',
        '/\bten[ée]s\b/iu'  => 'tienes',
        '/\bsab[ée]s\b/iu'  => 'sabes',
        '/\bhac[ée]s\b/iu'  => 'haces',
        '/\bdec[ií]s\b/iu'  => 'dices',
        '/\bpon[ée]s\b/iu'  => 'pones',
        '/\bven[ií]s\b/iu'  => 'vienes',
        '/\bquer[ií]s\b/iu' => 'quieres',
        '/\bpod[ií]s\b/iu'  => 'puedes',
        '/\bsos\b/iu'       => 'eres',
        '/\banot[áa]\b/iu'  => 'anota',
        '/\bpas[áa]\b/iu'   => 'pasa',
        '/\bmand[áa]\b/iu'  => 'manda',
        '/\bmir[áa]\b/iu'   => 'mira',
        '/\besper[áa]\b/iu' => 'espera',
        '/\bhabl[áa]\b/iu'  => 'habla',
        '/\bcont[áa]me\b/iu' => 'cuentame',
        '/\bdec[ií]me\b/iu' => 'dime',
        '/\bped[ií]me\b/iu' => 'pideme',
        '/\bvos\b/iu'       => 'tu',
    ];
    foreach ($map as $pat => $rep) {
        $text = preg_replace($pat, $rep, $text);
    }
    return $text;
}

/**
 * Último pedido abierto del cliente (sin cerrar) para evitar duplicados.
 */
function getOpenOrder($pdo, $wa_id) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE wa_id = ? AND status IN ('nuevo','aprobado','en_verificacion') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$wa_id]);
    $o = $stmt->fetch();
    return $o ?: null;
}

function getOrder($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $o = $stmt->fetch();
    return $o ?: null;
}

/**
 * Detección ligera por palabras clave (evita llamar a la IA en cada mensaje).
 */
function looksLikeOrderMessage($text) {
    return (bool)preg_match('/(?:quiero|quisiera|me gustaria|me das|me traes|me mandas|me envias|necesito|necesitaria|solicito)\b.*\b(pedir|pedido|orden|delivery|domicilio|comida|menu|plato|pizza|hamburguesa|refresco|bebida|jugo|algo de comer|algo de tomar)|perro|hot\s?dog|arepa|empanada|tequeño|tequeno|pastelito|cachapa|patacon|patacón|burrito|taco|sandwich|sándwich|pasta|pollo|carne|pescado|ensalada|cafe|café|jugo|agua|combo|almuerzo|desayuno|cena|plato del dia|plato del día|pedido|pedir|delivery|domicilio|para llevar|me mandan|envian.*pedido|cambia|cambio|modifica|quito|quitar|agrega|agregar|añade|anade|retira|sustituye|reemplaza|me equivoque|equivoque|corrige|actualiza.*(pedido|orden|direccion|domicilio)|(la )?direccion (es|correcta|real|verdadera)|domicilio (correcto|real)|te cambie|olvide el|olvidé el/i', $text);
}

function looksLikePaymentMessage($text) {
    return (bool)preg_match('/pague|pago|pagada|transferencia|comprobante|pago movil|referencia|pantalla.*pago|foto del pago|ya pague|hice el pago|envio el pago|mande el pago/i', $text);
}

function onlyDigits($s) {
    return preg_replace('/[^0-9]/', '', (string)$s);
}

function normalizeName($s) {
    $s = mb_strtolower(trim((string)$s));
    return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
}

function getAvailableProducts($pdo) {
    $names = [];
    foreach ($pdo->query("SELECT item_name FROM inventory WHERE active = 1")->fetchAll() as $r) {
        $n = trim($r['item_name'] ?? '');
        if ($n !== '') $names[] = $n;
    }
    return $names;
}

function matchesProduct($itemName, $products) {
    $n = normalizeName($itemName);
    if ($n === '') return false;
    foreach ($products as $p) {
        $pn = normalizeName($p);
        if ($pn !== '' && (strpos($n, $pn) !== false || strpos($pn, $n) !== false)) return true;
    }
    return false;
}

/**
 * Suma el subtotal de un pedido según los precios del inventario.
 * Devuelve null si algún item no coincide con un producto del inventario.
 */
function computeOrderSubtotal($pdo, $order) {
    $items = json_decode($order['items'] ?? '[]', true);
    if (!is_array($items) || empty($items)) return null;
    $products = [];
    foreach ($pdo->query("SELECT item_name, price FROM inventory WHERE active = 1")->fetchAll() as $r) {
        $products[] = ['name' => trim($r['item_name'] ?? ''), 'price' => (float)($r['price'] ?? 0)];
    }
    $total = 0.0;
    foreach ($items as $it) {
        $name = trim($it['name'] ?? '');
        $qty = max(1, (int)($it['qty'] ?? 1));
        $matched = false;
        foreach ($products as $p) {
            $pn = normalizeName($p['name']);
            $nn = normalizeName($name);
            if ($pn !== '' && (strpos($nn, $pn) !== false || strpos($pn, $nn) !== false)) {
                $total += $p['price'] * $qty;
                $matched = true;
                break;
            }
        }
        if (!$matched) return null;
    }
    return round($total, 2);
}

/**
 * Usa la IA para extraer los datos estructurados del pedido desde la conversación.
 */
function analyzeOrderData($history, $productList = '') {
    $conversationText = "";
    foreach (array_slice($history, -14) as $msg) {
        $role = $msg['role'] === 'user' ? 'Cliente' : 'Asistente';
        $conversationText .= "$role: {$msg['content']}\n";
    }

    $prompt = "Eres un extractor de pedidos de un restaurante. Analiza la conversación y extrae los datos del pedido de delivery.
Responde SOLO con JSON válido con esta forma exacta:
{
  \"is_order\": true|false,
  \"items\": [{\"name\": \"plato\", \"qty\": 1}],
  \"address\": \"dirección de entrega o vacío\",
  \"zone\": \"zona/ciudad o vacío\",
  \"phone\": \"teléfono de contacto del cliente si lo menciona en el mensaje, con solo dígitos, si no vacío\",
  \"notes\": \"notas del pedido o vacío\"
}
Reglas:
- is_order es true SOLO si el cliente pidió al menos un plato/producto que exista en la lista de disponibles (no si solo pregunta por el menú o precios).
- Si el cliente pide algo que NO está en la lista de disponibles, is_order=false y items vacío.
- Si no hay pedido concreto, is_order=false y items vacío.
- No inventes platos: usa solo lo que aparece en la conversación y exista en la lista de disponibles.
- La dirección debe incluir referencias, edificio, piso, zona, etc. tal como la escriba el cliente.
- Extrae SOLO el pedido actual del cliente; no repitas pedidos ya registrados antes.
Productos disponibles:
$productList
Conversación:\n$conversationText";

    $payload = [
        'model' => GROQ_MODEL,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0,
    ];

    $ch = curl_init(GROQ_BASE_URL . '/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        logger("ORDER: error de conexión a IA: $err");
        return null;
    }

    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '{}';
    $parsed = json_decode($content, true);
    if (!is_array($parsed)) {
        logger("ORDER: respuesta IA no parseable: " . substr($content, 0, 200));
        return null;
    }
    return $parsed;
}

/**
 * Usa la IA para interpretar una corrección/cambio sobre un pedido ya registrado.
 * Devuelve la lista COMPLETA de items corregida + dirección/teléfono si cambian.
 */
function analyzeOrderChange($text, $currentItems, $productList = '') {
    $currentText = json_encode(is_array($currentItems) ? $currentItems : [], JSON_UNESCAPED_UNICODE);

    $prompt = "Eres el gestor de pedidos de un restaurante. El cliente quiere MODIFICAR su pedido ya registrado.
Pedido actual (lista de items): $currentText
Mensaje del cliente: \"$text\"

Responde SOLO con JSON válido con esta forma exacta:
{
  \"type\": \"modify_order\" o \"not_order\",
  \"items\": [{\"name\": \"plato\", \"qty\": 1}],
  \"address\": \"dirección nueva si la menciona o corrige, si no vacío\",
  \"zone\": \"zona nueva si la menciona o corrige, si no vacío\",
  \"phone\": \"teléfono de contacto nuevo si lo menciona (solo dígitos), si no vacío\"
}

Reglas:
- type=modify_order SOLO si el cliente pidió un cambio concreto: agregar/quitá un producto, cambiar un producto por otro, corregir la dirección o corregir el teléfono. Si solo habla de otra cosa, usa not_order.
- items debe ser la LISTA COMPLETA del pedido DESPUÉS de aplicar el cambio (agrega, quita o sustituye según el mensaje).
- Si el cliente no cambió productos, items debe ser el mismo pedido actual.
- No inventes platos: usa solo lo que aparece en el pedido actual o en el mensaje.
- Si el cliente agrega un producto que NO está en la lista de disponibles, no lo agregues: mantén los items actuales.
- Si corrige la dirección pero no los productos, mantén los items actuales y pon la dirección nueva.
Productos disponibles:
$productList
Mensaje:\n$text";

    $payload = [
        'model' => GROQ_MODEL,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0,
    ];

    $ch = curl_init(GROQ_BASE_URL . '/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        logger("ORDER: error de conexión a IA (cambio): $err");
        return null;
    }

    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '{}';
    $parsed = json_decode($content, true);
    if (!is_array($parsed)) {
        logger("ORDER: respuesta IA de cambio no parseable: " . substr($content, 0, 200));
        return null;
    }
    return $parsed;
}

/**
 * Redimensiona una imagen a máx 768px en su lado mayor y la convierte a JPEG
 * para reducir el consumo de tokens del modelo de visión. Devuelve la ruta a usar.
 */
function resizeImageForAI($filePath) {
    if (!function_exists('imagecreatefromstring')) return $filePath;
    $data = @file_get_contents($filePath);
    if ($data === false) return $filePath;
    $img = @imagecreatefromstring($data);
    if (!$img) return $filePath;
    $w = imagesx($img);
    $h = imagesy($img);
    $maxDim = 768;
    if (max($w, $h) <= $maxDim) {
        imagedestroy($img);
        return $filePath;
    }
    $ratio = $maxDim / max($w, $h);
    $nw = max(1, (int)round($w * $ratio));
    $nh = max(1, (int)round($h * $ratio));
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $out = $filePath . '.resized.jpg';
    imagejpeg($dst, $out, 80);
    imagedestroy($img);
    imagedestroy($dst);
    return $out;
}

/**
 * Valida con el modelo de visión si una imagen es realmente un comprobante de pago.
 * Devuelve array JSON o null si falló la llamada a la IA.
 */
function analyzePaymentImage($filePath) {
    $resized = resizeImageForAI($filePath);
    $imageData = base64_encode(file_get_contents($resized));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $resized);
    finfo_close($finfo);
    if (!$mimeType) $mimeType = 'image/jpeg';
    if ($resized !== $filePath) @unlink($resized);

    $prompt = "Eres un validador de comprobantes de pago de un restaurante. Analiza la imagen y decide si es REALMENTE una captura de pago (pantalla de banca en línea, app bancaria o recibo de transferencia/pago móvil).
Responde SOLO con JSON válido con esta forma exacta:
{
  \"is_payment\": true|false,
  \"type\": \"pago_movil\" | \"transferencia\" | \"efectivo\" | \"otro\",
  \"bank\": \"banco si se ve, si no vacío\",
  \"amount\": \"monto si se ve, si no vacío\",
  \"reference\": \"número de referencia/operación si se ve, si no vacío\",
  \"date\": \"fecha si se ve, si no vacío\",
  \"summary\": \"una frase corta describiendo lo que se ve\",
  \"reason\": \"motivo por el cual NO parece un pago (vacío si sí lo es)\"
}
Reglas estrictas:
- is_payment=true SOLO si se ve claramente una operación de pago: pantalla de banco o app con monto, banco o referencia; o un recibo de transferencia aprobada.
- Capturas de chats, fotos personales, memes, pantallas de otra cosa, imágenes borrosas o sin datos bancarios → is_payment=false.
- No inventes datos: si un campo no se ve, déjalo vacío.";

    $payload = [
        'model' => VISION_MODEL,
        'messages' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => ['url' => "data:$mimeType;base64,$imageData"]]
            ]
        ]],
        'temperature' => 0,
        'max_tokens' => 1500,
    ];

    $ch = curl_init(GROQ_BASE_URL . '/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        logger("ORDER: error en análisis de comprobante (visión): HTTP $httpCode | $err | " . substr($response, 0, 200));
        return null;
    }

    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '{}';
    $parsed = parseJsonFromContent($content);
    if (!is_array($parsed)) {
        logger("ORDER: análisis de comprobante no parseable: " . substr($content, 0, 300));
        return null;
    }
    return $parsed;
}

/**
 * Extrae un JSON válido del texto de la IA (tolera bloques ```, tags <think> y texto suelto).
 */
function parseJsonFromContent($content) {
    $content = (string)$content;
    // Quitar bloques de código y tags de razonamiento
    $content = preg_replace('/```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/```/', '', $content);
    $content = preg_replace('/<think>.*?<\/think>/is', '', $content);
    // Quitar texto antes del primer { y después del último }
    $start = strpos($content, '{');
    $end = strrpos($content, '}');
    if ($start === false || $end === false || $end <= $start) return null;
    $json = substr($content, $start, $end - $start + 1);
    $parsed = json_decode($json, true);
    return is_array($parsed) ? $parsed : null;
}

/**
 * Procesa una imagen enviada por un cliente con pedido aprobado/en_verificación:
 * valida el comprobante, lo adjunta al pedido y pasa a en_verificación.
 * Devuelve el mensaje para el cliente, o null si no aplica (flujo normal de imagen).
 */
function processPaymentImage($pdo, $wa_id, $filePath, $caption = '') {
    $open = getOpenOrder($pdo, $wa_id);
    if (!$open || !in_array($open['status'], ['aprobado', 'en_verificacion'])) {
        return null;
    }

    $analysis = analyzePaymentImage($filePath);
    if (!$analysis) {
        $analysis = [
            'is_payment' => true,
            'type' => 'otro',
            'summary' => 'Imagen recibida (el análisis IA no estuvo disponible). Se requiere revisión manual.',
            'reason' => 'error_ia',
        ];
    }
    $isPayment = !empty($analysis['is_payment']);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    if (!$mimeType) $mimeType = 'image/jpeg';
    $dataUri = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($filePath));

    $ref = trim($analysis['reference'] ?? '');
    if ($ref === '' && $caption !== '') $ref = mb_substr(trim($caption), 0, 150);

    $stmt = $pdo->prepare("UPDATE orders SET status='en_verificacion', payment_image=?, payment_analysis=?, payment_ref=? WHERE id=?");
    $stmt->execute([$dataUri, json_encode($analysis, JSON_UNESCAPED_UNICODE), $ref, $open['id']]);
    logger("ORDER: comprobante recibido para {$open['order_number']} de $wa_id. IA: " . json_encode($analysis));

    if ($isPayment) {
        return "¡Recibido, gracias! Tu comprobante ya está en revisión, te confirmo en un momentico.";
    }
    return "Gracias por la imagen, pero no alcanzo a leer bien los datos del pago (monto, banco o referencia). Si es tu comprobante, ¿podrías enviarlo de nuevo con mejor enfoque? Igual lo reviso ya.";
}

/**
 * Acción del panel: rechazar el comprobante → el pedido vuelve a 'aprobado'
 * y se notifica al cliente para que reenvíe la captura.
 */
function rejectPayment($pdo, $orderId) {
    $order = getOrder($pdo, $orderId);
    if (!$order || $order['status'] !== 'en_verificacion') {
        throw new Exception("El pedido no existe o no está en verificación.");
    }
    $stmt = $pdo->prepare("UPDATE orders SET status='aprobado' WHERE id=?");
    $stmt->execute([$orderId]);
    $msgRej = "Hola, disculpa, no me cuadró el comprobante que enviaste: no logro ver bien el monto, el banco o la referencia. ¿Podrías enviarlo de nuevo? Con eso te dejo todo listo. ¡Gracias!";
    $sent = sendWhatsAppText($order['wa_id'], $msgRej);
    saveAssistantMessage($pdo, $order['wa_id'], $msgRej);
    logger("ORDER: comprobante de {$order['order_number']} RECHAZADO (vuelve a aprobado). Notificado: " . ($sent ? 'OK' : 'FALLO'));
    return "Comprobante rechazado y pedido devuelto a 'aprobado'. Cliente notificado.";
}

/**
 * Flujo principal invocado desde el webhook tras procesar cada mensaje.
 * Devuelve un texto opcional para anexar a la respuesta del bot (confirmaciones de cambios).
 */
/**
 * Flujo de pedidos: muta la BD según el mensaje del cliente y devuelve un CONTEXTO
 * (descripción de lo ocurrido) para que la IA responda con datos reales y con sus
 * propias palabras. Devuelve null si no hubo acción sobre pedidos.
 */
function processOrderFlow($pdo, $wa_id, $text, $history) {
    try {
        // 0) Cancelar pedidos abiertos con 6h de inactividad del cliente
        expireStaleOrders($pdo, 6);

        // A) ¿El cliente confirmó un pago (comprobante) para un pedido aprobado?
        if (looksLikePaymentMessage($text)) {
            $open = getOpenOrder($pdo, $wa_id);
            if ($open && $open['status'] === 'aprobado') {
                $ref = mb_substr(trim($text), 0, 150);
                $stmt = $pdo->prepare("UPDATE orders SET status='en_verificacion', payment_ref=? WHERE id=?");
                $stmt->execute([$ref, $open['id']]);
                logger("ORDER: pago en verificación para {$open['order_number']} de $wa_id. Ref: $ref");
                return "El cliente acaba de enviar un mensaje de pago para el pedido {$open['order_number']} (referencia: $ref) y el pedido pasó a verificación. Agradece el mensaje con naturalidad y dile que lo estás revisando y le confirmas en un momentico.";
            }
        }

        // B) ¿El cliente hizo un pedido, quiere modificarlo o da datos de entrega?
        $open = getOpenOrder($pdo, $wa_id);

        // B0) ¿El cliente cancela el pedido? Se cancela en BD y la IA responde natural.
        if ($open && in_array($open['status'], ['nuevo', 'aprobado']) && looksLikeCancellation($text)) {
            logger("ORDER: cancelación pedida por chat de $wa_id (pedido {$open['order_number']}).");
            cancelOrder($pdo, $open['id'], false, 'Cancelado por el cliente');
            return "El cliente acaba de cancelar su pedido ({$open['order_number']}). Reacciona con total naturalidad y sin insistir: no ofrezcas alternativas para que compre ni hagas preguntas de seguimiento. Si luego quiere pedir de nuevo, será una conversación nueva.";
        }

        $isFollowUp = (bool)($open && $open['status'] === 'nuevo' && (
            preg_match('/(direccion|domicilio|calle|avenida|\bav\.|\bedif\.|edificio|torre|casa|urbanizacion|sector|zona|colonia|piso|local|frente|al lado|cerca de|detras|hotel|quinta|referencia|numero de contacto|telefono de contacto|mi telefono|mi numero|contactarme|contactar|el mismo|el mio|el mío|mi numero|mi número|ese mismo|este mismo|este es|es este|si, este|si este|ese es|yo mismo|si, ese|si ese|ese numero|ese número|este numero|este número|numero de la casa|telefono de la casa)/i', $text)
            || preg_match('/\d{7,}/', $text)
        ));

        if (looksLikeOrderMessage($text) || $isFollowUp) {
            $products = getAvailableProducts($pdo);
            $productList = implode("\n", $products);

            // B1) Ya existe pedido abierto → interpretar corrección/cambio o datos de entrega
            // (se permite modificar mientras esté 'nuevo' o 'aprobado', es decir antes del pago)
            if ($open && in_array($open['status'], ['nuevo', 'aprobado'])) {
                $wasAprobado = ($open['status'] === 'aprobado');
                // Solo delivery: si el cliente pide para llevar o en sala, la IA lo orienta (no se confirma como delivery)
                if (preg_match('/para llevar|en sala|en el restaurante|para comer alla|para comer allí|comer alla|comer allí|en mesa/i', $text)) {
                    return '';
                }
                $currentItems = json_decode($open['items'] ?? '[]', true);
                if (!is_array($currentItems)) $currentItems = [];
                $change = analyzeOrderChange($text, $currentItems, $productList);
                $sets = [];
                $params = [];
                $phoneGiven = false;

                if ($change && ($change['type'] ?? '') === 'modify_order') {
                    $newItems = $change['items'] ?? null;
                    $addr = trim($change['address'] ?? '');
                    $zone = trim($change['zone'] ?? '');
                    $phone = onlyDigits($change['phone'] ?? '');

                    // Si el cambio deja el pedido sin items, se cancela el pedido completo
                    if (is_array($newItems) && empty($newItems) && !empty($currentItems)) {
                        logger("ORDER: $wa_id dejó el pedido {$open['order_number']} sin items. Se cancela.");
                        cancelOrder($pdo, $open['id'], false, 'Cancelado por el cliente (sin items)');
                        return "El cliente acaba de dejar su pedido sin productos, así que quedó cancelado. Reacciona con total naturalidad, sin insistir ni ofrecer alternativas.";
                    }

                    if (is_array($newItems) && json_encode($newItems, JSON_UNESCAPED_UNICODE) !== json_encode($currentItems, JSON_UNESCAPED_UNICODE)) {
                        $sets[] = 'items=?';
                        $params[] = json_encode($newItems, JSON_UNESCAPED_UNICODE);
                    }
                    if ($addr !== '' && $addr !== trim($open['delivery_address'] ?? '')) { $sets[] = 'delivery_address=?'; $params[] = $addr; }
                    if ($zone !== '' && $zone !== trim($open['delivery_zone'] ?? '')) { $sets[] = 'delivery_zone=?'; $params[] = $zone; }
                    if ($phone !== '' && $phone !== onlyDigits($open['contact_phone'] ?? '')) { $sets[] = 'contact_phone=?'; $params[] = $phone; $phoneGiven = true; }
                }

                // El cliente confirmó que el número de contacto es el mismo del chat
                if (!$phoneGiven && preg_match('/el mismo|el mio|el mío|mi numero|mi número|ese mismo|este mismo|este es|es este|si, este|si este|ese es|yo mismo|si, ese|si ese|ese numero|ese número|este numero|este número|numero de la casa|telefono de la casa/i', $text)) {
                    $sets[] = 'contact_phone=?';
                    $params[] = $wa_id;
                    $phoneGiven = true;
                }

                if (!empty($sets)) {
                    // Si el pedido ya estaba aprobado (se le pasó el total), cualquier cambio
                    // lo regresa a 'nuevo' para que el panel vuelva a pedir el delivery y el total.
                    if ($wasAprobado) {
                        $sets[] = 'status=?';
                        $params[] = 'nuevo';
                        $sets[] = 'total=?';
                        $params[] = 0.00;
                        $sets[] = 'delivery_cost=?';
                        $params[] = 0.00;
                    }
                    $params[] = $open['id'];
                    $sql = "UPDATE orders SET " . implode(', ', $sets) . " WHERE id=?";
                    $pdo->prepare($sql)->execute($params);
                    logger("ORDER: pedido {$open['order_number']} de $wa_id MODIFICADO" . ($wasAprobado ? ' (vuelve a nuevo para re-calcular total)' : '') . ": " . implode(', ', $sets));

                    $updated = getOrder($pdo, $open['id']);
                    $hasAddr = !empty(trim($updated['delivery_address'] ?? ''));
                    $hasPhone = !empty(trim($updated['contact_phone'] ?? ''));
                    $missing = [];
                    if (!$hasAddr) $missing[] = "la dirección de entrega";
                    if (!$hasPhone) $missing[] = "un número de contacto (explícale que es para que el delivery le avise cuando esté llegando)";
                    $detail = "items: " . implode('; ', orderItemsLines($updated));
                    if (!empty($updated['delivery_address'])) $detail .= "; dirección: {$updated['delivery_address']}";
                    if (!empty($updated['contact_phone'])) $detail .= "; teléfono: {$updated['contact_phone']}";
                    $ctx = "El pedido del cliente fue actualizado justo ahora";
                    if ($wasAprobado) $ctx .= " (había recibido el total, así que quedó pendiente de recalcular; si el cliente menciona el total, dile que se lo confirmas en un momentico)";
                    $ctx .= ". Estado actual: $detail. ";
                    if (!empty($missing)) {
                        $ctx .= "Falta lo siguiente y debes pedirlo con naturalidad, una sola cosa por mensaje: " . implode(' y ', $missing) . ".";
                    } else {
                        $ctx .= "El pedido quedó completo: confírmale con tus propias palabras qué quedó anotado (breve, sin repetir datos ya confirmados antes) y dile que le avisas con el total en un momentico.";
                    }
                    return $ctx;
                }
                // El mensaje no aportó cambios ni datos de entrega: la IA atiende normal
                return null;
            }

            // B2) Pedido en otro estado: no se modifica automáticamente
            if ($open) {
                logger("ORDER: $wa_id tiene el pedido {$open['order_number']} en estado {$open['status']}. No se modifica.");
                return null;
            }

            // B3) Pedido nuevo
            $parsed = analyzeOrderData($history, $productList);
            logger("ORDER: extractor B3 de $wa_id -> " . json_encode($parsed, JSON_UNESCAPED_UNICODE));
            if ($parsed && !empty($parsed['is_order'])) {
                $items = $parsed['items'] ?? [];
                if (empty($items)) return null;

                // Solo delivery: si el cliente indica que quiere para llevar o en sala, no se registra
                if (preg_match('/para llevar|en sala|en el restaurante|para comer alla|para comer allí|comer alla|comer allí|en mesa/i', $text)) {
                    logger("ORDER: $wa_id pidió para llevar/sala. No se registra (solo delivery).");
                    return null;
                }

                // Validar que todos los items existan en el inventario disponible
                $unavailable = [];
                foreach ($items as $it) {
                    $name = trim($it['name'] ?? '');
                    if (!matchesProduct($name, $products)) $unavailable[] = $name;
                }
                if (!empty($unavailable)) {
                    logger("ORDER: $wa_id pidió items no disponibles: " . implode(', ', $unavailable) . ". No se crea el pedido.");
                    return "El cliente pidió: " . implode(' y ', $unavailable) . ", que NO están disponibles en el menú. Avísale con honestidad que no lo tienen en este momento y pregúntale si quiere algo del menú (sin sugerir platos específicos).";
                }

                $number = nextOrderNumber($pdo);
                $phone = onlyDigits($parsed['phone'] ?? '');
                $stmt = $pdo->prepare("INSERT INTO orders (order_number, wa_id, contact_phone, items, type, delivery_address, delivery_zone, status)
                                       VALUES (?, ?, ?, ?, 'delivery', ?, ?, 'nuevo')");
                $stmt->execute([
                    $number,
                    $wa_id,
                    $phone !== '' ? $phone : null,
                    json_encode($items, JSON_UNESCAPED_UNICODE),
                    trim($parsed['address'] ?? ''),
                    trim($parsed['zone'] ?? ''),
                ]);
                logger("ORDER: pedido nuevo $number creado para $wa_id (contacto: " . ($phone !== '' ? $phone : 'pendiente') . "): " . json_encode($items));

                $hasAddr = trim($parsed['address'] ?? '') !== '';
                $hasPhone = $phone !== '';
                $missing = [];
                if (!$hasAddr) $missing[] = "la dirección de entrega";
                if (!$hasPhone) $missing[] = "un número de contacto (explícale que es para que el delivery le avise cuando esté llegando)";
                $ctx = "Se acaba de crear el pedido del cliente: " . implode('; ', orderItemsLines(['items' => json_encode($items, JSON_UNESCAPED_UNICODE)]));
                if ($hasAddr) $ctx .= "; dirección: " . trim($parsed['address']);
                if ($hasPhone) $ctx .= "; teléfono: $phone";
                $ctx .= ". ";
                if (!empty($missing)) {
                    $ctx .= "Falta lo siguiente y debes pedirlo con naturalidad, una sola cosa por mensaje: " . implode(' y ', $missing) . ".";
                } else {
                    $ctx .= "El pedido quedó completo: confírmale con tus propias palabras qué quedó anotado (breve) y dile que le avisas con el total en un momentico.";
                }
                return $ctx;
            }
        }
        return null;
    } catch (Exception $e) {
        logger("ERROR en processOrderFlow: " . $e->getMessage());
        return null;
    }
}

function orderItemsLines($order) {
    $items = json_decode($order['items'] ?? '[]', true);
    if (!is_array($items) || empty($items)) return ["• Sin detalle"];
    $lines = [];
    foreach ($items as $it) {
        $name = $it['name'] ?? 'Plato';
        $qty = (int)($it['qty'] ?? 1);
        $lines[] = ($qty > 1 ? "$qty x " : "• ") . $name;
    }
    return $lines;
}

function formatPaymentMethodsText($methods) {
    $pm = $methods['pago_movil'] ?? [];
    $tr = $methods['transferencia'] ?? [];
    $txt = "";

    if (!empty($pm)) {
        $txt .= "Pago móvil:\n";
        if (!empty($pm['banco']))    $txt .= "Banco: {$pm['banco']}\n";
        if (!empty($pm['telefono'])) $txt .= "Teléfono: {$pm['telefono']}\n";
        if (!empty($pm['documento']))$txt .= "Documento: {$pm['documento']}\n";
        if (!empty($pm['titular']))  $txt .= "Titular: {$pm['titular']}\n";
        $txt .= "\n";
    }
    if (!empty($tr)) {
        $txt .= "Transferencia:\n";
        if (!empty($tr['banco']))   $txt .= "Banco: {$tr['banco']}\n";
        if (!empty($tr['cuenta']))  $txt .= "Cuenta: {$tr['cuenta']}\n";
        if (!empty($tr['titular'])) $txt .= "Titular: {$tr['titular']}\n";
        $txt .= "\n";
    }
    return trim($txt);
}

function buildApprovalMessage($order, $methods) {
    $msg = "Tu pedido:\n" . implode("\n", orderItemsLines($order));
    if (!empty($order['delivery_address'])) $msg .= "\nDirección: {$order['delivery_address']}";
    if (!empty($order['delivery_zone']))    $msg .= "\nZona: {$order['delivery_zone']}";
    if (!empty($order['delivery_cost']) && (float)$order['delivery_cost'] > 0) {
        $msg .= "\n\nDelivery: $" . number_format((float)$order['delivery_cost'], 2, ',', '.');
    }
    $msg .= "\nTotal: $" . number_format((float)$order['total'], 2, ',', '.');

    $pay = formatPaymentMethodsText($methods);
    if ($pay) {
        $msg .= "\n\nDatos de pago:\n$pay\nCuando pagues me mandas la captura por aquí.";
    } else {
        $msg .= "\n\nTe paso los datos de pago. Cuando pagues me mandas la captura por aquí.";
    }
    return $msg;
}

function buildPaymentConfirmationMessage($order) {
    $msg = "¡Pago recibido, gracias! Tu pedido ya está en preparación.\n\n";
    if (!empty($order['payment_method'])) $msg .= "Método de pago: {$order['payment_method']}\n";
    if (!empty($order['payment_ref']))    $msg .= "Referencia: {$order['payment_ref']}\n";
    $msg .= "\nTe aviso en cuanto salga para la entrega.";
    return $msg;
}

/**
 * Acciones ejecutadas desde el panel (requieren contexto de tenant activo).
 */

function approveOrder($pdo, $orderId, $delivery, $totalManual = null) {
    $order = getOrder($pdo, $orderId);
    if (!$order || $order['status'] !== 'nuevo') {
        throw new Exception("El pedido no existe o ya fue procesado.");
    }
    if (empty(trim($order['delivery_address'] ?? ''))) {
        throw new Exception("El pedido no tiene dirección de entrega. Pídesela al cliente por el chat antes de aprobar.");
    }
    if (empty(trim($order['contact_phone'] ?? ''))) {
        throw new Exception("El pedido no tiene número de contacto. Pídeselo al cliente por el chat antes de aprobar.");
    }
    $itemsOrder = json_decode($order['items'] ?? '[]', true);
    if (!is_array($itemsOrder) || empty($itemsOrder)) {
        throw new Exception("El pedido no tiene items. Pídele al cliente que confirme su pedido antes de aprobar.");
    }
    $delivery = round((float)$delivery, 2);
    if ($delivery < 0) {
        throw new Exception("El delivery no puede ser negativo.");
    }

    $subtotal = computeOrderSubtotal($pdo, $order);
    if ($subtotal === null && $totalManual === null) {
        throw new Exception("No se pudo calcular el total automáticamente (un plato no está en el inventario). Coloca el total manualmente.");
    }
    $total = ($subtotal !== null) ? round($subtotal + $delivery, 2) : round((float)$totalManual, 2);
    if ($total <= 0) {
        throw new Exception("El total debe ser mayor que cero.");
    }

    $stmt = $pdo->prepare("UPDATE orders SET total=?, delivery_cost=?, status='aprobado' WHERE id=?");
    $stmt->execute([$total, $delivery, $orderId]);

    $methods = getPaymentMethods($pdo);
    $msg = buildApprovalMessage(array_merge($order, ['total' => $total, 'delivery_cost' => $delivery]), $methods);
    $phoneId = $GLOBALS['TENANT']['phone_number_id'] ?? null;
    logger("APPROVE: Enviando a {$order['wa_id']} con phone_id=" . ($phoneId ?: 'DEFAULT'));
    $sent = false;
    foreach (splitMessage($msg) as $chunk) {
        $sent = sendWhatsAppText($order['wa_id'], $chunk) || $sent;
        saveAssistantMessage($pdo, $order['wa_id'], $chunk);
        usleep(300000);
    }
    logger("ORDER: pedido {$order['order_number']} aprobado (delivery $delivery, total $total). Mensaje enviado a {$order['wa_id']}: " . ($sent ? 'OK' : 'FALLO'));
    return "Pedido {$order['order_number']} aprobado y notificado al cliente.";
}

function confirmOrderPaid($pdo, $orderId, $method) {
    $order = getOrder($pdo, $orderId);
    if (!$order) {
        throw new Exception("El pedido no existe.");
    }
    if (!in_array($order['status'], ['aprobado', 'en_verificacion'])) {
        throw new Exception("El pedido debe estar aprobado o en verificación para confirmar el pago.");
    }

    $stmt = $pdo->prepare("UPDATE orders SET status='pagado', payment_method=? WHERE id=?");
    $stmt->execute([$method, $orderId]);

    $msg = buildPaymentConfirmationMessage(array_merge($order, ['payment_method' => $method]));
    $sent = false;
    foreach (splitMessage($msg) as $chunk) {
        $sent = sendWhatsAppText($order['wa_id'], $chunk) || $sent;
        saveAssistantMessage($pdo, $order['wa_id'], $chunk);
        usleep(300000);
    }
    logger("ORDER: pedido {$order['order_number']} marcado PAGADO ($method). Mensaje enviado: " . ($sent ? 'OK' : 'FALLO'));
    return "Pedido {$order['order_number']} confirmado como pagado y notificado al cliente.";
}

function setOrderOnWay($pdo, $orderId) {
    $order = getOrder($pdo, $orderId);
    if (!$order) {
        throw new Exception("El pedido no existe.");
    }
    if ($order['status'] !== 'pagado') {
        throw new Exception("El pedido debe estar pagado para marcarlo en camino.");
    }
    $stmt = $pdo->prepare("UPDATE orders SET status='en_camino' WHERE id=?");
    $stmt->execute([$orderId]);
    $sent = sendWhatsAppText($order['wa_id'], "¡Hola! Tu pedido ya salió del restaurante y va en camino con el delivery. Te aviso en cuanto llegue.");
    saveAssistantMessage($pdo, $order['wa_id'], "¡Hola! Tu pedido ya salió del restaurante y va en camino con el delivery. Te aviso en cuanto llegue.");
    logger("ORDER: pedido {$order['order_number']} marcado EN CAMINO. Notificado: " . ($sent ? 'OK' : 'FALLO'));
    return "Pedido {$order['order_number']} marcado en camino y notificado al cliente.";
}

function setOrderDelivered($pdo, $orderId) {
    $order = getOrder($pdo, $orderId);
    if (!$order) {
        throw new Exception("El pedido no existe.");
    }
    if (!in_array($order['status'], ['pagado', 'en_camino'])) {
        throw new Exception("El pedido debe estar pagado o en camino para marcarlo como entregado.");
    }
    $stmt = $pdo->prepare("UPDATE orders SET status='entregado' WHERE id=?");
    $stmt->execute([$orderId]);
    $sent = sendWhatsAppText($order['wa_id'], "¡Tu pedido llegó! Que lo disfrutes. Si necesitas algo más, aquí estoy.");
    saveAssistantMessage($pdo, $order['wa_id'], "¡Tu pedido llegó! Que lo disfrutes. Si necesitas algo más, aquí estoy.");
    logger("ORDER: pedido {$order['order_number']} marcado ENTREGADO. Notificado: " . ($sent ? 'OK' : 'FALLO'));
    return "Pedido {$order['order_number']} marcado como entregado y notificado al cliente.";
}

function cancelOrder($pdo, $orderId, $notify = true, $note = '') {
    $order = getOrder($pdo, $orderId);
    if (!$order) {
        throw new Exception("El pedido no existe.");
    }
    if ($order['status'] === 'cancelado') {
        return "Pedido ya cancelado.";
    }
    $stmt = $pdo->prepare("UPDATE orders SET status='cancelado', admin_note=? WHERE id=?");
    $stmt->execute([$note, $orderId]);
    if ($notify) {
        $msgCancel = "Tu pedido quedó cancelado. Si quieres pedir de nuevo o necesitas algo, con gusto te ayudo.";
        sendWhatsAppText($order['wa_id'], $msgCancel);
        saveAssistantMessage($pdo, $order['wa_id'], $msgCancel);
    }
    logger("ORDER: pedido {$order['order_number']} cancelado" . ($note !== '' ? " ($note)" : '') . ".");
    return "Pedido cancelado.";
}

/**
 * Detecta si el mensaje del cliente indica que ya no va a comprar / cancela su pedido.
 * Evita falsos positivos como "no quiero agregar nada más" o "no, eso es todo".
 */
function looksLikeCancellation($text) {
    $t = mb_strtolower(trim($text));
    if ($t === '') return false;
    // 1) Frases inequívocas de cancelación (evalúan primero, incluso si mencionan pago)
    $strong = [
        '/ya no (?:voy a|quiero|necesito) (?:comprar|pedir|nada)/',
        '/no (?:voy a|quiero|necesito) (?:comprar|pedir) nada/',
        '/mejor no(?:,| me voy|\.|$)/',
        '/no me interesa/',
        '/ya no lo quiero/',
        '/olvida(?:te)? el pedido/',
        '/quita todo(?: el pedido| lo|)?/',
        '/quitar todo(?: el pedido| lo|)?/',
        '/elimina el pedido/',
        '/no quiero el pedido/',
        '/no voy a pedir nada/',
        '/dejalo asi(?:,|\.|$)/',
        '/ya no quiero comprar nada/',
    ];
    foreach ($strong as $p) {
        if (preg_match($p, $t)) return true;
    }
    // NOTA: "cancelar"/"anula" NO cuentan como cancelación: en Venezuela
    // "cancelar" significa PAGAR. Solo se cancela con frases inequívocas.
    return false;
}

/**
 * Cancela silenciosamente los pedidos abiertos (nuevo/aprobado) cuyo último mensaje
 * del cliente tiene más de $hours horas. Se llama en cada webhook y desde el cron.
 */
function expireStaleOrders($pdo, $hours = 6) {
    try {
        $stmt = $pdo->prepare(
            "UPDATE orders o
             LEFT JOIN (
                 SELECT wa_id, MAX(created_at) AS last_user FROM messages WHERE role='user' GROUP BY wa_id
             ) m ON m.wa_id = o.wa_id
             SET o.status='cancelado', o.admin_note=?
             WHERE o.status IN ('nuevo','aprobado')
               AND COALESCE(m.last_user, o.created_at) < DATE_SUB(NOW(), INTERVAL ? HOUR)"
        );
        $stmt->execute(['Cancelado por inactividad (' . $hours . 'h)', $hours]);
        $n = $stmt->rowCount();
        if ($n > 0) {
            logger("ORDER: $n pedido(s) cancelado(s) por inactividad de " . $hours . "h");
        }
        return $n;
    } catch (Exception $e) {
        logger("WARN expireStaleOrders: " . $e->getMessage());
        return 0;
    }
}

function deleteOrder($pdo, $orderId) {
    $order = getOrder($pdo, $orderId);
    if (!$order) {
        throw new Exception("El pedido no existe.");
    }
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id=?");
    $stmt->execute([$orderId]);
    logger("ORDER: pedido {$order['order_number']} ELIMINADO permanentemente.");
    return "Pedido {$order['order_number']} eliminado.";
}