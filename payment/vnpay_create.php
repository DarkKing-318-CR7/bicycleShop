<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/vnpay.php';

requireRole('buyer');

$buyerId = (int) (currentUser()['id'] ?? 0);
$orderId = (int) ($_GET['order_id'] ?? 0);

if ($orderId <= 0 || !vnpayIsConfigured()) {
    redirect('../buyer/my-orders.php');
}

$stmt = $conn->prepare("
    SELECT id, order_code, offered_price, payment_method, payment_status, status
    FROM orders
    WHERE id = ? AND buyer_id = ?
    LIMIT 1
");

$order = null;

if ($stmt) {
    $stmt->bind_param('ii', $orderId, $buyerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$order
    || $order['payment_method'] !== 'vnpay'
    || $order['payment_status'] === 'paid'
    || in_array($order['status'], ['completed', 'cancelled'], true)
) {
    redirect('../buyer/order-detail.php?id=' . $orderId);
}

$updateStmt = $conn->prepare("
    UPDATE orders
    SET payment_status = 'pending',
        updated_at = CURRENT_TIMESTAMP
    WHERE id = ?
");

if ($updateStmt) {
    $updateStmt->bind_param('i', $orderId);
    $updateStmt->execute();
    $updateStmt->close();
}

$paymentUrl = vnpayBuildPaymentUrl([
    'order_code' => $order['order_code'],
    'amount' => $order['offered_price'],
], vnpayBaseUrl() . '/payment/vnpay_return.php');

redirect($paymentUrl);
