<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$section = $_GET['section'] ?? '';
$since = (int)($_GET['since'] ?? 0);
$pdo = getDB();

switch ($section) {

    case 'dashboard':
        $row = $pdo->query("SELECT GREATEST(
            COALESCE((SELECT MAX(UNIX_TIMESTAMP(created_at)) FROM messages),0),
            COALESCE((SELECT MAX(UNIX_TIMESTAMP(created_at)) FROM orders),0)
        )")->fetch();
        $latest = (int)($row[0] ?? 0);

        if ($since > 0 && $latest <= $since) {
            http_response_code(304);
            exit;
        }

        echo json_encode([
            'ts' => $latest,
            'totalMsgs' => (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
            'totalOrders' => (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
            'pendingOrders' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('nuevo','aprobado','en_verificacion')")->fetchColumn(),
            'threads' => $pdo->query("SELECT wa_id, MAX(created_at) as last_msg FROM messages GROUP BY wa_id ORDER BY last_msg DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC),
        ]);
        break;

    case 'orders':
        $row = $pdo->query("SELECT COALESCE(MAX(UNIX_TIMESTAMP(created_at)),0) FROM orders")->fetch();
        $latest = (int)($row[0] ?? 0);

        if ($since > 0 && $latest <= $since) {
            http_response_code(304);
            exit;
        }

        echo json_encode([
            'ts' => $latest,
            'orders' => $pdo->query("SELECT id, order_number, wa_id, contact_phone, items, type, delivery_address, delivery_zone, delivery_cost, total, status, payment_method, payment_ref, payment_image, payment_analysis, created_at FROM orders ORDER BY FIELD(status,'nuevo','en_verificacion','aprobado','pagado','en_camino','entregado','cancelado'), id DESC")->fetchAll(PDO::FETCH_ASSOC),
        ]);
        break;

    case 'chat':
        $wa_id = $_GET['wa_id'] ?? '';
        if (!$wa_id) { http_response_code(400); exit; }

        $stmt = $pdo->prepare("SELECT COALESCE(MAX(UNIX_TIMESTAMP(created_at)),0) FROM messages WHERE wa_id = ?");
        $stmt->execute([$wa_id]);
        $ts = (int)$stmt->fetchColumn();

        if ($since > 0 && $ts <= $since) {
            http_response_code(304);
            exit;
        }

        $stmt2 = $pdo->prepare("SELECT id, wa_id, role, content, image_data, created_at FROM messages WHERE wa_id = ? ORDER BY created_at ASC LIMIT 50");
        $stmt2->execute([$wa_id]);
        echo json_encode([
            'ts' => $ts,
            'messages' => $stmt2->fetchAll(PDO::FETCH_ASSOC),
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'invalid section']);
}
