<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/orders.php';
$pdo = connectDB(getenv('DB_HOST') ?: 'localhost', 'salvxkld_salvix_restaurante', getenv('DB_USER'), getenv('DB_PASS'));
$hours = isset($argv[1]) ? (float)$argv[1] : 6;
$n = expireStaleOrders($pdo, $hours);
echo date('Y-m-d H:i:s') . " - Pedidos cancelados por inactividad: $n\n";