<?php
/**
 * Intercambia el code de Facebook Login por tokens y guarda las credenciales del tenant.
 * Usado por Embedded Signup (WhatsApp e Instagram).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tenants.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? null;
$platform = $input['platform'] ?? null;  // 'whatsapp' o 'instagram'
$tenantId = $input['tenant_id'] ?? null;

if (!$code || !$platform || !$tenantId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing code, platform, or tenant_id']);
    exit;
}

// Configuración de Meta
$APP_ID = '884874344543876';
$APP_SECRET = getenv('META_APP_SECRET') ?: '';
$REDIRECT_URI = 'https://demo.salvanovasolutions.online/restaurante-x/meta-exchange.php';

if (empty($APP_SECRET)) {
    http_response_code(500);
    echo json_encode(['error' => 'META_APP_SECRET not configured in .env']);
    exit;
}

// 1. Intercambiar code por access token de corta duración
$tokenUrl = "https://graph.facebook.com/v21.0/oauth/access_token?" . http_build_query([
    'client_id' => $APP_ID,
    'client_secret' => $APP_SECRET,
    'redirect_uri' => $REDIRECT_URI,
    'code' => $code
]);

$tokenResp = @file_get_contents($tokenUrl);
$tokenData = json_decode($tokenResp, true);

if (empty($tokenData['access_token'])) {
    logger("META EXCHANGE ERROR: No se pudo obtener access token. " . json_encode($tokenData));
    http_response_code(400);
    echo json_encode(['error' => 'Failed to exchange code', 'details' => $tokenData]);
    exit;
}

$userToken = $tokenData['access_token'];

// 2. Obtener token de largo plazo
$longUrl = "https://graph.facebook.com/v21.0/oauth/access_token?" . http_build_query([
    'grant_type' => 'fb_exchange_token',
    'client_id' => $APP_ID,
    'client_secret' => $APP_SECRET,
    'fb_exchange_token' => $userToken
]);

$longResp = @file_get_contents($longUrl);
$longData = json_decode($longResp, true);
$longToken = $longData['access_token'] ?? $userToken;

// 3. Conectar a la BD del tenant
$pdo = getBaseDB();
$stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch();

if (!$tenant) {
    http_response_code(404);
    echo json_encode(['error' => 'Tenant not found']);
    exit;
}

if ($platform === 'whatsapp') {
    // 3a. Obtener WhatsApp Business Accounts del usuario
    $wabaUrl = "https://graph.facebook.com/v21.0/me?fields=whatsapp_business_accounts{id,name,phone_numbers{id,display_phone_number,verified_name}}&access_token=" . $longToken;
    $wabaResp = @file_get_contents($wabaUrl);
    $wabaData = json_decode($wabaResp, true);

    $wabaAccounts = $wabaData['whatsapp_business_accounts']['data'] ?? [];
    if (empty($wabaAccounts)) {
        logger("META EXCHANGE: No se encontraron WABAs para el usuario.");
        http_response_code(400);
        echo json_encode(['error' => 'No WhatsApp Business Accounts found', 'raw' => $wabaData]);
        exit;
    }

    // Tomar la primera WABA (o la que tenga números)
    $waba = $wabaAccounts[0];
    $wabaId = $waba['id'];
    $phoneNumbers = $waba['phone_numbers']['data'] ?? [];
    $phoneId = $phoneNumbers[0]['id'] ?? null;
    $displayPhone = $phoneNumbers[0]['display_phone_number'] ?? null;

    if (!$phoneId) {
        logger("META EXCHANGE: WABA sin números de teléfono.");
        http_response_code(400);
        echo json_encode(['error' => 'WABA has no phone numbers', 'waba_id' => $wabaId]);
        exit;
    }

    // Guardar en BD
    $stmt = $pdo->prepare("UPDATE tenants SET waba_id = ?, phone_number_id = ?, wa_token = ? WHERE id = ?");
    $stmt->execute([$wabaId, $phoneId, $longToken, $tenantId]);

    logger("META EXCHANGE OK: WhatsApp conectado para tenant {$tenant['slug']}. WABA=$wabaId, Phone=$phoneId");

    echo json_encode([
        'success' => true,
        'platform' => 'whatsapp',
        'phone_number_id' => $phoneId,
        'display_phone' => $displayPhone,
        'waba_id' => $wabaId
    ]);

} elseif ($platform === 'instagram') {
    // 3b. Obtener Instagram Business Accounts del usuario
    $igUrl = "https://graph.facebook.com/v21.0/me?fields=instagram_business_accounts{id,username,name}&access_token=" . $longToken;
    $igResp = @file_get_contents($igUrl);
    $igData = json_decode($igResp, true);

    $igAccounts = $igData['instagram_business_accounts']['data'] ?? [];
    if (empty($igAccounts)) {
        logger("META EXCHANGE: No se encontraron cuentas de Instagram.");
        http_response_code(400);
        echo json_encode(['error' => 'No Instagram Business Accounts found', 'raw' => $igData]);
        exit;
    }

    $igAccount = $igAccounts[0];
    $igAccountId = $igAccount['id'];
    $igUsername = $igAccount['username'] ?? '';

    // Guardar en BD
    $stmt = $pdo->prepare("UPDATE tenants SET ig_account_id = ?, ig_token = ? WHERE id = ?");
    $stmt->execute([$igAccountId, $longToken, $tenantId]);

    logger("META EXCHANGE OK: Instagram conectado para tenant {$tenant['slug']}. IG=$igAccountId (@$igUsername)");

    echo json_encode([
        'success' => true,
        'platform' => 'instagram',
        'ig_account_id' => $igAccountId,
        'username' => $igUsername
    ]);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid platform. Use "whatsapp" or "instagram".']);
}
