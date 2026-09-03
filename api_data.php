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
            COALESCE((SELECT MAX(id) FROM messages),0),
            COALESCE((SELECT MAX(id) FROM orders),0)
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
        $row = $pdo->query("SELECT COALESCE(MAX(id),0) FROM orders")->fetch();
        $latest = (int)($row[0] ?? 0);

        if ($since > 0 && $latest <= $since) {
            http_response_code(304);
            exit;
        }

        require_once __DIR__ . '/orders.php';
        $orders = $pdo->query("SELECT * FROM orders ORDER BY FIELD(status,'nuevo','en_verificacion','aprobado','pagado','en_camino','entregado','cancelado'), id DESC")->fetchAll();

        ob_start();
        foreach ($orders as $o) {
            $itemsLines = orderItemsLines($o);
            $badgeClass = match($o['status']) {
                'pagado', 'entregado' => 'badge-success',
                'nuevo', 'en_verificacion' => 'badge-warning',
                'en_camino' => 'badge-info',
                'cancelado' => 'badge-danger',
                default => 'badge-info',
            };
            $pmPreset = 'Pago Móvil';
            $pa = !empty($o['payment_analysis']) ? json_decode($o['payment_analysis'], true) : null;
            if (is_array($pa)) {
                if (($pa['type'] ?? '') === 'transferencia') $pmPreset = 'Transferencia';
                if (($pa['type'] ?? '') === 'efectivo') $pmPreset = 'Efectivo';
            }
            ?>
            <tr>
                <td style="font-family:monospace; font-size:13px; font-weight:600; color:var(--accent);"><?php echo htmlspecialchars($o['order_number']); ?></td>
                <td style="font-family:monospace; font-size:13px;">
                    <?php echo htmlspecialchars($o['wa_id']); ?>
                    <?php if(!empty($o['contact_phone']) && $o['contact_phone'] !== $o['wa_id']): ?>
                        <br><span style="font-size:11px; color:var(--text-3);">📞 <?php echo htmlspecialchars($o['contact_phone']); ?></span>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px; max-width:220px;">
                    <?php foreach ($itemsLines as $line): ?>
                        <div><?php echo htmlspecialchars($line); ?></div>
                    <?php endforeach; ?>
                    <?php if($o['delivery_zone']): ?><span style="font-size:11px; color:var(--text-3);">Zona: <?php echo htmlspecialchars($o['delivery_zone']); ?></span><?php endif; ?>
                    <?php if($o['payment_method']): ?><br><span style="font-size:11px; color:var(--success);">Pago: <?php echo htmlspecialchars($o['payment_method']); ?></span><?php endif; ?>
                    <?php if(!empty($o['payment_image'])): ?>
                        <div style="margin-top:8px; border:1px solid var(--border); border-radius:8px; padding:6px; background:var(--surface-3); display:inline-block;">
                            <img src="<?php echo htmlspecialchars($o['payment_image']); ?>" alt="Comprobante" style="max-width:140px; max-height:110px; border-radius:6px; cursor:pointer; display:block;" onclick="openImage(this.src)">
                            <?php if(is_array($pa)): ?>
                                <div style="font-size:11px; margin-top:4px;">
                                    <?php if(!empty($pa['is_payment'])): ?>
                                        <span class="badge badge-success">Pago analizado</span>
                                        <?php if(!empty($pa['bank'])): ?><div style="color:var(--text);">🏦 <?php echo htmlspecialchars($pa['bank']); ?></div><?php endif; ?>
                                        <?php if(!empty($pa['amount'])): ?><div style="color:var(--accent); font-weight:600;">💰 <?php echo htmlspecialchars($pa['amount']); ?></div><?php endif; ?>
                                        <?php if(!empty($pa['reference'])): ?><div style="color:var(--text-3);">REF: <?php echo htmlspecialchars($pa['reference']); ?></div><?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-danger">⚠️ No parece pago</span>
                                        <?php if(!empty($pa['reason'])): ?><div style="color:var(--text-3);"><?php echo htmlspecialchars($pa['reason']); ?></div><?php endif; ?>
                                    <?php endif; ?>
                                    <?php if(!empty($pa['summary'])): ?><div style="color:var(--text-3);"><?php echo htmlspecialchars($pa['summary']); ?></div><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px; max-width:180px;"><?php echo htmlspecialchars($o['delivery_address'] ?: '—'); ?></td>
                <td style="font-weight:600; color:var(--accent);"><?php echo $o['total'] > 0 ? '$' . number_format($o['total'], 2, ',', '.') : '—'; ?></td>
                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo getOrderStatusLabel($o['status']); ?></span></td>
                <td>
                    <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="showOrderDetail(<?php echo (int)$o['id']; ?>)">Detalle</button>
                        <?php if($o['status'] === 'nuevo'): ?>
                            <?php
                                $oIncomplete = empty(trim($o['delivery_address'] ?? '')) || empty(trim($o['contact_phone'] ?? ''));
                                $oMissing = [];
                                if (empty(trim($o['delivery_address'] ?? ''))) $oMissing[] = 'dirección';
                                if (empty(trim($o['contact_phone'] ?? ''))) $oMissing[] = 'número de contacto';
                            ?>
                            <?php if(!$oIncomplete): ?>
                                <?php
                                    $oSubtotal = computeOrderSubtotal($pdo, $o);
                                    $oCanAuto = ($oSubtotal !== null);
                                ?>
                                <form method="POST" style="display:inline-flex; gap:4px; align-items:center;" onsubmit="return confirm('¿Aprobar el pedido <?php echo htmlspecialchars($o['order_number']); ?> y enviar el total al cliente?');">
                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                    <input type="number" step="0.01" min="0" name="delivery_cost" placeholder="Delivery $" <?php echo $oCanAuto ? 'required' : ''; ?> style="width:90px; padding:6px 8px; border-radius:8px; background:var(--surface-3); border:1px solid var(--border); color:var(--text); font-size:12px; font-family:'Inter',sans-serif;">
                                    <?php if($oCanAuto): ?>
                                        <span style="font-size:12px; color:var(--text-3); white-space:nowrap;" id="totPrev_<?php echo (int)$o['id']; ?>" data-subtotal="<?php echo $oSubtotal; ?>">Total: $<?php echo number_format($oSubtotal, 2, ',', '.'); ?> + delivery</span>
                                    <?php else: ?>
                                        <input type="number" step="0.01" min="0.01" name="order_total" placeholder="Total $" required style="width:90px; padding:6px 8px; border-radius:8px; background:var(--surface-3); border:1px solid var(--border); color:var(--text); font-size:12px; font-family:'Inter',sans-serif;">
                                    <?php endif; ?>
                                    <button type="submit" name="approve_order" class="btn btn-primary btn-sm">Aprobar</button>
                                </form>
                            <?php else: ?>
                                <span class="badge badge-warning">Falta <?php echo implode(' y ', $oMissing); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if(in_array($o['status'], ['aprobado','en_verificacion'])): ?>
                            <form method="POST" style="display:inline-flex; gap:4px; align-items:center;" onsubmit="return confirm('¿Confirmar el pago del pedido <?php echo htmlspecialchars($o['order_number']); ?>?');">
                                <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                <select name="payment_method" required style="padding:6px 8px; border-radius:8px; background:var(--surface-3); border:1px solid var(--border); color:var(--text); font-size:12px; font-family:'Inter',sans-serif;">
                                    <option value="Pago Móvil" <?php echo $pmPreset === 'Pago Móvil' ? 'selected' : ''; ?>>Pago Móvil</option>
                                    <option value="Transferencia" <?php echo $pmPreset === 'Transferencia' ? 'selected' : ''; ?>>Transferencia</option>
                                    <option value="Efectivo" <?php echo $pmPreset === 'Efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                                </select>
                                <button type="submit" name="confirm_paid" class="btn btn-primary btn-sm">Confirmar Pago</button>
                            </form>
                            <?php if($o['status'] === 'en_verificacion' && !empty($o['payment_image'])): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Rechazar el comprobante?');">
                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                    <button type="submit" name="reject_payment" class="btn btn-danger btn-sm">Rechazar</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if($o['status'] === 'pagado'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Marcar como en camino?');">
                                <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                <button type="submit" name="mark_on_way" class="btn btn-primary btn-sm">En Camino</button>
                            </form>
                        <?php endif; ?>
                        <?php if($o['status'] === 'en_camino'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Marcar como entregado?');">
                                <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                <button type="submit" name="mark_delivered" class="btn btn-secondary btn-sm">Entregado</button>
                            </form>
                        <?php endif; ?>
                        <?php if(!in_array($o['status'], ['entregado','cancelado'])): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Cancelar el pedido?');">
                                <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                <button type="submit" name="cancel_order" class="btn btn-danger btn-sm">Cancelar</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar permanentemente?');">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            <button type="submit" name="delete_order" class="btn btn-danger btn-sm" style="background:var(--surface-3); color:var(--danger,#e74c3c); border-color:var(--danger,#e74c3c);">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php
        }
        $html = ob_get_clean();

        echo json_encode([
            'ts' => $latest,
            'html' => $html,
            'count' => count($orders),
        ]);
        break;

    case 'chat':
        $wa_id = $_GET['wa_id'] ?? '';
        if (!$wa_id) { http_response_code(400); exit; }

        $stmt = $pdo->prepare("SELECT COALESCE(MAX(id),0) FROM messages WHERE wa_id = ?");
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
