<?php
require_once __DIR__ . '/config.php';

/**
 * Cliente simple para enviar mensajes de WhatsApp Cloud API
 */

function sendWhatsAppText($to, $text) {
    $url = "https://graph.facebook.com/v21.0/" . WA_PHONE_ID . "/messages";
    
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
    curl_close($ch);

    return $httpCode === 200;
}
