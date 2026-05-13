<?php
require_once __DIR__ . '/db.php';

/**
 * Lógica para detectar leads y extraer nombres/negocios en PHP
 */

function processLeads($wa_id, $reply, $history) {
    $pdo = getDB();
    
    // 1. Detectar si el bot envió un enlace de acción (Calificado)
    $isQualified = (strpos($reply, '[[ACTION_LINK]]') !== false || strpos($reply, '[[AGENDA_LINK]]') !== false);
    
    if (!$isQualified) return; // Solo procesar IA si está calificado para ahorrar tokens

    // 2. Extraer datos con IA
    $leadData = extractLeadData($history);
    
    $nombre = $leadData['nombre'] ?? null;
    $negocio = $leadData['negocio'] ?? null;
    $resumen = $leadData['resumen'] ?? null;
    $solicitud = $leadData['solicitud'] ?? null;
    $status = 'calificado';

    // 3. Upsert del Lead
    $stmt = $pdo->prepare("INSERT INTO leads (wa_id, qualification_status, nombre, negocio, resumen, solicitud) 
                           VALUES (?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE 
                           qualification_status = VALUES(qualification_status),
                           nombre = COALESCE(VALUES(nombre), nombre),
                           negocio = COALESCE(VALUES(negocio), negocio),
                           resumen = VALUES(resumen),
                           solicitud = VALUES(solicitud),
                           updated_at = NOW()");
    $stmt->execute([$wa_id, $status, $nombre, $negocio, $resumen, $solicitud]);
}

/**
 * Usa Groq para analizar la conversación y extraer datos estructurados
 */
function extractLeadData($history) {
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    
    $conversationText = "";
    foreach (array_slice($history, -10) as $msg) {
        $role = $msg['role'] === 'user' ? 'Cliente' : 'Asistente';
        $conversationText .= "$role: {$msg['content']}\n";
    }

    $prompt = "Analiza la siguiente conversación de ventas y extrae la información en formato JSON puro.
    Campos requeridos:
    - nombre: Nombre del cliente (si lo mencionó).
    - negocio: Nombre de su empresa/negocio (si lo mencionó).
    - resumen: Un resumen muy breve (1 frase) de lo hablado.
    - solicitud: Qué es exactamente lo que el cliente está solicitando o su duda principal.

    Conversación:
    $conversationText
    
    Responde ÚNICAMENTE con el JSON.";

    $payload = [
        'model' => 'llama-3.3-70b-versatile',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    return json_decode($data['choices'][0]['message']['content'] ?? '{}', true);
}

function cleanReply($reply) {
    // Quitar los marcadores internos antes de enviar al usuario
    $clean = str_replace(['[[ACTION_LINK]]', '[[AGENDA_LINK]]'], getenv('QUALIFIED_CTA_URL'), $reply);
    $clean = preg_replace('/\[\[DESCALIFICADO.*?\]\]/i', '', $clean);
    return trim($clean);
}
