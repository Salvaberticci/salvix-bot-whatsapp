<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tenants.php';

header('Content-Type: application/json');

/**
 * Endpoint de salud para verificar la configuración de PHP y Base de Datos
 */

$response = [
    'status' => 'ok',
    'engine' => 'PHP',
    'php_version' => PHP_VERSION,
    'database' => 'error',
    'config' => [
        'wa_phone_id' => getenv('WHATSAPP_PHONE_NUMBER_ID') ? 'Set' : 'Missing',
        'groq_api_key' => (getenv('GROQ_API_KEY') ?: getenv('OPENAI_API_KEY')) ? 'Set' : 'Missing',
    ]
];

try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT 1");
    if ($stmt) {
        $response['database'] = 'connected (MySQL)';
    }
    $tenants = getAllTenants();
    $response['tenants_count'] = count($tenants);
    $response['tenants'] = array_map(function($t) {
        return ['slug' => $t['slug'], 'nombre' => $t['nombre'], 'phone_number_id' => $t['phone_number_id']];
    }, $tenants);
} catch (Exception $e) {
    $response['database_error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);