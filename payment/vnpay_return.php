<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/vnpay.php';

requireRole('buyer');

$currentUser = currentUser();
$buyerId = (int) ($currentUser['id'] ?? 0);
$buyerName = $currentUser['full_name'] ?? 'Tài khoản';

$orderCode = trim((string) ($_GET['vnp_TxnRef'] ?? ''));
$responseCode = trim((string) ($_GET['vnp_ResponseCode'] ?? ''));
$transactionStatus = trim((string) ($_GET['vnp_TransactionStatus'] ?? ''));
$transactionNo = trim((string) ($_GET['vnp_TransactionNo'] ?? ''));
$bankCode = trim((string) ($_GET['vnp_BankCode'] ?? ''));
$payDate = trim((string) ($_GET['vnp_PayDate'] ?? ''));
$vnpAmount = (int) ($_GET['vnp_Amount'] ?? 0);

$isValidSignature = vnpayVerifyReturn($_GET);
$message = 'Không tìm thấy đơn hàng.';
$messageType = 'danger';
$order = null;

function formatPriceVnd($price): string
{
    return number_format((float) $price, 0, ',', '.') . 'd';
}

function formatVnpayDate(?string $date): ?string
{
    if (!$date || strlen($date) !== 14) {
        return null;
    }

    $dt = DateTime::createFromFormat('YmdHis', $date);

    return $dt ? $dt->format('Y-m-d H:i:s') : null;
}

function ordersHasColumn(mysqli $conn, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

if ($orderCode !== '') {
    $stmt = $conn->prepare("
        SELECT id, order_code, offered_price, payment_status, payment_method
        FROM orders
        WHERE order_code = ? AND buyer_id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param('si', $orderCode, $buyerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}

if ($order) {
    $expectedAmount = (int) round((float) $order['offered_price']) * 100;
    $amountMatches = $vnpAmount === $expectedAmount;
    $isSuccess = $isValidSignature
        && $amountMatches
        && $responseCode === '00'
        && ($transactionStatus === '' || $transactionStatus === '00');

    $paymentStatus = $isSuccess ? 'paid' : 'failed';
    $paidAt = $isSuccess ? (formatVnpayDate($payDate) ?? date('Y-m-d H:i:s')) : null;

    $hasPaymentLogColumns = ordersHasColumn($conn, 'payment_transaction_id')
        && ordersHasColumn($conn, 'payment_bank_code')
        && ordersHasColumn($conn, 'payment_response_code')
        && ordersHasColumn($conn, 'payment_paid_at');

    if ($hasPaymentLogColumns) {
        $updateStmt = $conn->prepare("
            UPDATE orders
            SET payment_status = ?,
                payment_transaction_id = ?,
                payment_bank_code = ?,
                payment_response_code = ?,
                payment_paid_at = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
    } else {
        $updateStmt = $conn->prepare("
            UPDATE orders
            SET payment_status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
    }

    if ($updateStmt) {
        if ($hasPaymentLogColumns) {
            $updateStmt->bind_param(
                'sssssi',
                $paymentStatus,
                $transactionNo,
                $bankCode,
                $responseCode,
                $paidAt,
                $order['id']
            );
        } else {
            $updateStmt->bind_param('si', $paymentStatus, $order['id']);
        }

        $updateStmt->execute();
        $updateStmt->close();
    }

    if ($isSuccess) {
        $message = 'Thanh toán VNPay thành công. Đơn hàng của bạn đã được ghi nhận.';
        $messageType = 'success';
    } elseif (!$isValidSignature) {
        $message = 'Không thể xác thực chữ ký giao dịch VNPay.';
    } elseif (!$amountMatches) {
        $message = 'Số tiền VNPay trả về không khớp với đơn hàng.';
    } else {
        $message = 'Thanh toán VNPay không thành công hoặc đã bị hủy.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Kết quả thanh toán</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/bike-marketplace.css">
</head>
<body class="seller-my-bikes-page">
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container py-2">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <span class="brand-mark"><i class="bi bi-bicycle"></i></span>
                Bike Marketplace
            </a>
            <div class="ms-auto dropdown">
                <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?= e($buyerName) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item" href="../buyer/my-orders.php"><i class="bi bi-receipt me-2"></i>Đơn mua của tôi</a></li>
                    <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Thanh toán</div>
                <h1 class="section-title text-white mb-2">Kết quả thanh toán</h1>
                <p class="mb-0 text-white-50">Hệ thống đã nhận phản hồi từ cổng thanh toán VNPay.</p>
            </div>
        </section>

        <section class="container">
            <div class="content-card">
                <div class="alert alert-<?= e($messageType) ?> mb-4" role="alert">
                    <?= e($message) ?>
                </div>

                <?php if ($order): ?>
                    <div class="meta-grid mb-4">
                        <div class="meta-item"><small>Mã đơn</small><strong><?= e($order['order_code']) ?></strong></div>
                        <div class="meta-item"><small>Số tiền</small><strong><?= e(formatPriceVnd($order['offered_price'])) ?></strong></div>
                        <div class="meta-item"><small>Mã giao dịch VNPay</small><strong><?= e($transactionNo !== '' ? $transactionNo : 'Chưa có') ?></strong></div>
                        <div class="meta-item"><small>Ngân hàng</small><strong><?= e($bankCode !== '' ? $bankCode : 'Chưa có') ?></strong></div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a href="../buyer/order-detail.php?id=<?= e((int) $order['id']) ?>" class="btn btn-success">Xem chi tiết đơn</a>
                        <a href="../buyer/my-orders.php" class="btn btn-light border">Về đơn mua của tôi</a>
                    </div>
                <?php else: ?>
                    <a href="../buyer/my-orders.php" class="btn btn-success">Về đơn mua của tôi</a>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
