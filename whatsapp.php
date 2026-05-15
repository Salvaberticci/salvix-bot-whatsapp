<?php
require_once __DIR__ . '/config.php';

/**
 * Cliente simple para enviar mensajes de WhatsApp Cloud API
 */

function sendWhatsAppText($to, $text) {
    $url = "https://graph.facebook.com/v25.0/" . WA_PHONE_ID . "/messages";
    
    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $to,
        'type' => 'text',
        'text' => ['body' => substr($text, 0, 4096)]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . WA_TOKEN
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        logger("ERROR al enviar WhatsApp a $to: Código HTTP $httpCode. Respuesta: $response. Error CURL: $error");
    } else {
        logger("ÉXITO: Mensaje enviado a $to.");
    }

    return $httpCode === 200;
}

/**
 * Descarga un archivo multimedia desde Meta usando su ID
 */
function downloadMetaMedia($mediaId) {
    // 1. Obtener la URL de descarga
    $url = "https://graph.facebook.com/v25.0/" . $mediaId;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
    $resp = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($resp, true);
    $downloadUrl = $data['url'] ?? null;
    
    if (!$downloadUrl) {
        logger("ERROR: No se pudo obtener la URL de descarga de Meta. Respuesta: " . $resp);
        return null;
    }

    // 2. Descargar el archivo binario (Restaurando Authorization para evitar 401)
    $ch = curl_init($downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . WA_TOKEN,
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    ]);
    $binary = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($info['http_code'] !== 200) {
        logger("ERROR: Fallo al descargar el binario de Meta. Código HTTP: " . $info['http_code']);
        return null;
    }

    // Guardar temporalmente
    $tmpFile = __DIR__ . '/tmp/' . $mediaId;
    if (!is_dir(__DIR__ . '/tmp')) mkdir(__DIR__ . '/tmp');
    file_put_contents($tmpFile, $binary);
    
    logger("ÉXITO: Archivo descargado y guardado en $tmpFile (Tamaño: " . strlen($binary) . " bytes)");
    return $tmpFile;
}
