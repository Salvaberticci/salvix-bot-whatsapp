<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/knowledge.php';

/**
 * Cliente simple para Groq (OpenAI Compatible) usando cURL
 */

function buildSystemPrompt($userMessage = "") {
    // Leer el prompt desde la base de datos (settings)
    $prompt = '';
    try {
        require_once __DIR__ . '/db.php';
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'system_prompt'");
        $stmt->execute();
        $prompt = $stmt->fetchColumn();
    } catch (Exception $e) {
        logger("ERROR leyendo system_prompt de DB: " . $e->getMessage());
    }
    // Fallback: archivo de ejemplo si no hay nada en DB
    if (!$prompt) {
        $prompt = @file_get_contents(__DIR__ . '/prompts/system.example.md');
    }
    if (!$prompt) {
        $prompt = "Eres un asistente de ventas útil para Salvix. Responde de forma concisa en español.";
    }
    
    // RAG: Buscar información relevante en la base de conocimientos
    if (!empty($userMessage)) {
        $chunks = searchKnowledge($userMessage, 3);
        if (!empty($chunks)) {
            logger("RAG: Encontrados " . count($chunks) . " fragmentos relevantes para: '$userMessage'");
            $prompt .= "\n\n--- INFORMACIÓN DE APOYO (RELEVANTE PARA ESTA CONSULTA) ---\n";
            foreach ($chunks as $c) {
                $prompt .= "[Fuente: {$c['source_file']}]\n{$c['content']}\n\n";
            }
            $prompt .= "--- FIN INFORMACIÓN DE APOYO ---\n";
        } else {
            logger("RAG: No se encontraron fragmentos relevantes para: '$userMessage'");
        }
    }
    
    // Leer el Menú de la base de datos
    require_once __DIR__ . '/db.php';
    try {
        $pdo = getDB();
        $inventory = $pdo->query("SELECT * FROM inventory WHERE active = 1 ORDER BY category, item_name")->fetchAll();
        if (!empty($inventory)) {
            logger("MENÚ: Cargados " . count($inventory) . " productos activos.");
            $prompt .= "\n\n--- MENÚ DISPONIBLE ---\n";
            $currentCat = null;
            foreach ($inventory as $i) {
                $cat = $i['category'] ?: 'General';
                if ($cat !== $currentCat) {
                    $prompt .= "\n[{$cat}]\n";
                    $currentCat = $cat;
                }
                $prompt .= "- {$i['item_name']} | $" . number_format($i['price'], 2);
                if (!empty($i['description'])) $prompt .= " | " . $i['description'];
                $prompt .= "\n";
            }
        } else {
            logger("MENÚ: No hay productos activos en la base de datos.");
        }
    } catch (Exception $e) {
        logger("ERROR MENÚ: " . $e->getMessage());
    }

    // Métodos de pago reales configurados en el panel (NO inventar otros)
    try {
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'payment_methods'");
        $stmt->execute();
        $raw = $stmt->fetchColumn();
        $methods = $raw ? json_decode($raw, true) : null;
        if (is_array($methods) && (!empty($methods['pago_movil']) || !empty($methods['transferencia']))) {
            logger("PAGOS: Datos de pago reales cargados desde el panel.");
            $prompt .= "\n\n--- FORMAS DE PAGO REALES (del panel del restaurante: dale al cliente EXACTAMENTE estos datos) ---\n";
            $pm = $methods['pago_movil'] ?? [];
            if (!empty($pm)) {
                $prompt .= "Pago movil:\n";
                if (!empty($pm['banco']))    $prompt .= "- Banco: {$pm['banco']}\n";
                if (!empty($pm['telefono'])) $prompt .= "- Telefono: {$pm['telefono']}\n";
                if (!empty($pm['documento']))$prompt .= "- Documento: {$pm['documento']}\n";
                if (!empty($pm['titular']))  $prompt .= "- Titular: {$pm['titular']}\n";
            }
            $tr = $methods['transferencia'] ?? [];
            if (!empty($tr)) {
                $prompt .= "Transferencia:\n";
                if (!empty($tr['banco']))   $prompt .= "- Banco: {$tr['banco']}\n";
                if (!empty($tr['cuenta']))  $prompt .= "- Cuenta: {$tr['cuenta']}\n";
                if (!empty($tr['titular'])) $prompt .= "- Titular: {$tr['titular']}\n";
            }
            $prompt .= "Cuando el cliente pida los datos de pago, pasale exactamente los datos de arriba (y si no estan completos, di \"te paso los datos de pago en un momentico\").\n";
        }
    } catch (Exception $e) {
        logger("ERROR PAGOS: " . $e->getMessage());
    }

    return $prompt;
}

function completeChat($userMessage, $history = [], $premium = false) {
    $url = GROQ_BASE_URL . '/chat/completions';
    // Enrutamiento: gpt-oss-120b solo en momentos críticos (pedidos, pagos, reclamos)
    $model = $premium ? 'openai/gpt-oss-120b' : GROQ_MODEL;

    logger("DEBUG: URL GROQ: $url | MODELO: $model" . ($premium ? ' (PREMIUM)' : ''));
    
    // Reactivamos RAG e Inventario
    $systemPrompt = buildSystemPrompt($userMessage); 
    
    $messages = [];
    $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    
    foreach ($history as $msg) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
    
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 1500
    ];

    logger("DEBUG: Enviando cURL a Groq...");
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Bajamos a 15 segundos para que no se cuelgue

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        logger("ERROR en completeChat (Groq): Código HTTP $httpCode | Error cURL: $curlError | Respuesta: $response");
        return "Uy, se me cortó la conexión un segundito. ¿Me repites lo que necesitas?";
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? "No pude obtener una respuesta.";
}

function transcribeAudio($filePath) {
    $url = 'https://api.groq.com/openai/v1/audio/transcriptions';
    $ch = curl_init($url);
    $cfile = new CURLFile($filePath, 'audio/ogg', 'audio.ogg');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'file' => $cfile,
        'model' => 'whisper-large-v3',
        'response_format' => 'json',
        'language' => 'es'
    ]);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . GROQ_API_KEY]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['text'] ?? null;
}

function analyzeImage($filePath, $userText = "Describe esta imagen", $history = []) {
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $imageData = base64_encode(file_get_contents($filePath));

    // Detectar el MIME type real del archivo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    if (!$mimeType) $mimeType = 'image/jpeg';

    $systemPrompt = buildSystemPrompt($userText);
    
    $messages = [];
    $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    foreach ($history as $msg) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
    
    $messages[] = [
        'role' => 'user',
        'content' => [
            ['type' => 'text', 'text' => $userText],
            ['type' => 'image_url', 'image_url' => ['url' => "data:$mimeType;base64,$imageData"]]
        ]
    ];

    $payload = [
        'model' => VISION_MODEL,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 200
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        logger("ERROR analyzeImage (Groq): HTTP $httpCode | Error cURL: $curlError | Respuesta: $response");
        return "Uy, no alcancé a ver bien la imagen. ¿Me cuentas qué es?";
    }

    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? null;
    
    if (!$content) {
        logger("ERROR analyzeImage: Respuesta vacía de Groq. Response raw: " . $response);
        return "Uy, no alcancé a ver bien la imagen. ¿Me cuentas qué muestra?";
    }
    
    return $content;
}
