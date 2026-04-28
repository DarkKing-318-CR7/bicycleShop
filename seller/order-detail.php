<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('seller');

$currentUser = currentUser();
$sellerId = (int) ($currentUser['id'] ?? 0);
$sellerName = $currentUser['full_name'] ?? 'Người bán';
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80';
$orderId = (int) ($_GET['id'] ?? 0);
$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

if ($orderId <= 0) {
    redirect('orders.php');
}

function formatPriceVnd($price): string
{
    return number_format((float) $price, 0, ',', '.') . 'đ';
}

function formatDateVi(?string $date): string
{
    if (!$date) {
        return 'Đang cập nhật';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return 'Đang cập nhật';
    }

    return date('d/m/Y', $timestamp);
}

function getOrderStatusMeta(string $status): array
{
    switch ($status) {
        case 'confirmed':
            return ['class' => 'status-approved', 'label' => 'Đã xác nhận'];
        case 'in_progress':
            return ['class' => 'status-approved', 'label' => 'Đang giao dịch'];
        case 'completed':
            return ['class' => 'status-sold', 'label' => 'Hoàn tất'];
        case 'cancelled':
            return ['class' => 'status-rejected', 'label' => 'Đã hủy'];
        case 'pending':
        default:
            return ['class' => 'status-pending', 'label' => 'Chờ xác nhận'];
    }
}

function getOrderAmountColumn(mysqli $conn): string
{
    $columns = [];
    $result = $conn->query('SHOW COLUMNS FROM orders');

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $field = $row['Field'] ?? '';

            if ($field !== '') {
                $columns[] = $field;
            }
        }

        $result->free();
    }

    if (in_array('offered_price', $columns, true)) {
        return 'offered_price';
    }

    if (in_array('total_price', $columns, true)) {
        return 'total_price';
    }

    return 'total_amount';
}

$amountColumn = getOrderAmountColumn($conn);

function updateSellerOrderStatus(mysqli $conn, int $orderId, int $sellerId, string $newStatus): bool
{
    if (!in_array($newStatus, ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'], true)) {
        return false;
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare("
        UPDATE orders o
        INNER JOIN bikes b ON b.id = o.bike_id
        SET o.status = ?, o.updated_at = CURRENT_TIMESTAMP
        WHERE o.id = ?
          AND b.seller_id = ?
          AND o.status NOT IN ('completed', 'cancelled')
    ");

    if (!$stmt) {
        $conn->rollback();
        return false;
    }

    $stmt->bind_param('sii', $newStatus, $orderId, $sellerId);
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$updated) {
        $conn->rollback();
        return false;
    }

    if ($newStatus === 'completed') {
        $bikeStmt = $conn->prepare("
            UPDATE bikes b
            INNER JOIN orders o ON o.bike_id = b.id
            SET b.status = 'sold', b.updated_at = CURRENT_TIMESTAMP
            WHERE o.id = ? AND b.seller_id = ?
        ");

        if (!$bikeStmt) {
            $conn->rollback();
            return false;
        }

        $bikeStmt->bind_param('ii', $orderId, $sellerId);
        $bikeStmt->execute();
        $bikeStmt->close();

        $cancelOtherStmt = $conn->prepare("
            UPDATE orders
            SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
            WHERE bike_id = (
                SELECT bike_id FROM (
                    SELECT bike_id FROM orders WHERE id = ?
                ) AS completed_order
            )
              AND id <> ?
              AND status IN ('pending', 'confirmed', 'in_progress')
        ");

        if (!$cancelOtherStmt) {
            $conn->rollback();
            return false;
        }

        $cancelOtherStmt->bind_param('ii', $orderId, $orderId);
        $cancelOtherStmt->execute();
        $cancelOtherStmt->close();
    }

    $conn->commit();
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $newStatus = trim($_POST['new_status'] ?? '');

    if (updateSellerOrderStatus($conn, $orderId, $sellerId, $newStatus)) {
        $_SESSION['success_message'] = 'Đã cập nhật trạng thái đơn mua.';
    } else {
        $_SESSION['error_message'] = 'Không thể cập nhật đơn mua. Đơn có thể đã hoàn tất, đã hủy hoặc không thuộc tài khoản của bạn.';
    }

    redirect('order-detail.php?id=' . $orderId);
}

$order = null;

$sql = "
    SELECT
        o.id,
        o.order_code,
        o.status,
        o.created_at,
        o.contact_method,
        o.meeting_location,
        o.buyer_note,
        o.payment_method,
        o.payment_status,
        o.{$amountColumn} AS order_total,
        b.id AS bike_id,
        b.title AS bike_title,
        b.price AS bike_price,
        b.condition_status,
        b.frame_size,
        b.wheel_size,
        b.color,
        COALESCE(c.name, 'Danh m?c kh?c') AS category_name,
        COALESCE(br.name, '') AS brand_name,
        COALESCE(img.image_url, ?) AS image_url,
        COALESCE(u.full_name, 'Ng??i mua') AS buyer_name,
        COALESCE(u.phone, '?ang c?p nh?t') AS buyer_phone,
        COALESCE(u.email, '?ang c?p nh?t') AS buyer_email
    FROM orders o
    INNER JOIN bikes b ON b.id = o.bike_id
    LEFT JOIN users u ON u.id = o.buyer_id
    LEFT JOIN categories c ON c.id = b.category_id
    LEFT JOIN brands br ON br.id = b.brand_id
    LEFT JOIN bike_images img ON img.id = (
        SELECT bi.id
        FROM bike_images bi
        WHERE bi.bike_id = b.id
        ORDER BY bi.id ASC
        LIMIT 1
    )
    WHERE o.id = ? AND b.seller_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param('sii', $fallbackImage, $orderId, $sellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$order) {
    redirect('orders.php');
}

$statusMeta = getOrderStatusMeta((string) ($order['status'] ?? 'pending'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Chi tiết đơn mua</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Chuyển đổi điều hướng">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto mb-3 mb-lg-0 gap-lg-3">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="../bikes.php">Xe đạp</a></li>
                    <li class="nav-item"><a class="nav-link" href="../bikes.php#categories">Danh mục</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Liên hệ</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2">
                    <a href="my-bikes.php" class="btn btn-outline-dark">Tin đăng</a>
                    <a href="orders.php" class="btn btn-outline-dark">Đơn mua</a>
                    <a href="add-bike.php" class="btn btn-success">Đăng tin mới</a>
                    <a href="../logout.php" class="btn btn-outline-dark"><?= e($sellerName) ?></a>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note">
                    <i class="bi bi-house-door"></i>
                    <a href="../index.php" class="text-white text-decoration-none">Trang chủ</a>
                    <span>/</span>
                    <a href="orders.php" class="text-white text-decoration-none">Đơn mua xe của tôi</a>
                    <span>/</span>
                    Chi tiết đơn
                </div>
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h1 class="section-title text-white mb-2">Chi tiết đơn mua</h1>
                        <p class="mb-0 text-white-50">Xem thông tin đơn hàng được gửi tới xe của bạn.</p>
                    </div>
                    <span class="status-badge <?= e($statusMeta['class']) ?> bg-white border-0">Mã đơn: <?= e($order['order_code'] ?? ('#' . (int) ($order['id'] ?? 0))) ?></span>
                </div>
            </div>
        </section>

        <section class="container">
            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success" role="alert">
                    <?= e($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?= e($errorMessage) ?>
                </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-4">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-hourglass-split"></i></span>
                        <div><small>Trạng thái</small><strong><?= e($statusMeta['label']) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-calendar-event"></i></span>
                        <div><small>Ngày yêu cầu</small><strong><?= e(formatDateVi($order['created_at'] ?? null)) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-cash-coin"></i></span>
                        <div><small>Giá đơn hàng</small><strong><?= e(formatPriceVnd($order['order_total'] ?? 0)) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <section class="content-card mb-4">
                        <h2 class="section-heading">Thông tin xe</h2>
                        <div class="row g-4 align-items-start">
                            <div class="col-md-5">
                                <img src="<?= e($order['image_url'] ?? $fallbackImage) ?>" alt="<?= e($order['bike_title'] ?? 'Xe đạp thể thao') ?>" class="img-fluid rounded-4 shadow-sm">
                            </div>
                            <div class="col-md-7">
                                <h3 class="section-title fs-3 mb-2"><?= e($order['bike_title'] ?? 'Xe đạp thể thao') ?></h3>
                                <div class="price mb-3"><?= e(formatPriceVnd($order['bike_price'] ?? 0)) ?></div>
                                <div class="meta-grid">
                                    <div class="meta-item"><small>Danh mục</small><strong><?= e($order['category_name'] ?? 'Danh mục khác') ?></strong></div>
                                    <div class="meta-item"><small>Hãng</small><strong><?= e($order['brand_name'] ?? '') ?></strong></div>
                                    <div class="meta-item"><small>Khung</small><strong><?= e($order['frame_size'] !== '' ? $order['frame_size'] : 'Đang cập nhật') ?></strong></div>
                                    <div class="meta-item"><small>Bánh</small><strong><?= e($order['wheel_size'] !== '' ? $order['wheel_size'] : 'Đang cập nhật') ?></strong></div>
                                    <div class="meta-item"><small>Màu</small><strong><?= e($order['color'] !== '' ? $order['color'] : 'Đang cập nhật') ?></strong></div>
                                    <div class="meta-item"><small>Giá</small><strong><?= e(formatPriceVnd($order['bike_price'] ?? 0)) ?></strong></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="content-card mb-4">
                        <h2 class="section-heading">Thông tin giao dịch</h2>
                        <div class="meta-grid mb-4">
                            <div class="meta-item"><small>Mã đơn</small><strong><?= e($order['order_code'] ?? ('#' . (int) ($order['id'] ?? 0))) ?></strong></div>
                            <div class="meta-item"><small>Ngày gửi</small><strong><?= e(formatDateVi($order['created_at'] ?? null)) ?></strong></div>
                            <div class="meta-item"><small>Trạng thái hiện tại</small><strong><?= e($statusMeta['label']) ?></strong></div>
                            <div class="meta-item"><small>Phương thức liên hệ</small><strong><?= e($order['contact_method'] !== "" ? $order['contact_method'] : "Đang cập nhật") ?></strong></div>
                            <div class="meta-item"><small>Phương thức thanh toán</small><strong><?= e($order['payment_method'] !== "" ? $order['payment_method'] : "Đang cập nhật") ?></strong></div>
                            <div class="meta-item"><small>Địa điểm giao dịch</small><strong><?= e($order['meeting_location'] !== "" ? $order['meeting_location'] : "Đang cập nhật") ?></strong></div>
                            <div class="meta-item"><small>Thanh toán</small><strong><?= e($order['payment_status'] !== "" ? $order['payment_status'] : "Đang cập nhật") ?></strong></div>
                            <div class="meta-item"><small>Ghi chú</small><strong><?= e($order['buyer_note'] !== "" ? $order['buyer_note'] : "Không có ghi chú") ?></strong></div>
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <aside class="sidebar-card mb-4">
                        <h2 class="section-heading">Thông tin người mua</h2>
                        <div class="text-center">
                            <div class="stats-icon mx-auto mb-3"><i class="bi bi-person"></i></div>
                            <h3 class="h5 fw-bold mb-2"><?= e($order['buyer_name'] ?? 'Người mua') ?></h3>
                            <p class="mb-2"><i class="bi bi-telephone me-2"></i><?= e($order['buyer_phone'] ?? 'Đang cập nhật') ?></p>
                            <p class="mb-0"><i class="bi bi-envelope me-2"></i><?= e($order['buyer_email'] ?? 'Đang cập nhật') ?></p>
                        </div>
                    </aside>

                    <section class="sidebar-card mb-4">
                        <h2 class="section-heading">Tóm tắt giao dịch</h2>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span>Giá xe</span>
                            <strong><?= e(formatPriceVnd($order['bike_price'] ?? 0)) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span>Giá đơn</span>
                            <strong><?= e(formatPriceVnd($order['order_total'] ?? 0)) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-3">
                            <span class="fw-semibold">Tổng</span>
                            <strong class="price mb-0"><?= e(formatPriceVnd($order['order_total'] ?? 0)) ?></strong>
                        </div>
                    </section>

                    <section class="sidebar-card">
                        <h2 class="section-heading">Điều hướng</h2>
                        <div class="d-grid gap-2">
                            <?php if (($order['status'] ?? '') === 'pending'): ?>
                                <form method="post">
                                    <input type="hidden" name="new_status" value="confirmed">
                                    <button type="submit" name="update_order_status" class="btn btn-success w-100">Xác nhận đơn</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Bạn có chắc muốn hủy đơn này?');">
                                    <input type="hidden" name="new_status" value="cancelled">
                                    <button type="submit" name="update_order_status" class="btn btn-outline-dark w-100">Hủy đơn</button>
                                </form>
                            <?php elseif (($order['status'] ?? '') === 'confirmed'): ?>
                                <form method="post">
                                    <input type="hidden" name="new_status" value="in_progress">
                                    <button type="submit" name="update_order_status" class="btn btn-success w-100">Chuyển sang đang giao dịch</button>
                                </form>
                            <?php elseif (($order['status'] ?? '') === 'in_progress'): ?>
                                <form method="post" onsubmit="return confirm('Hoàn tất đơn này và đánh dấu xe đã bán?');">
                                    <input type="hidden" name="new_status" value="completed">
                                    <button type="submit" name="update_order_status" class="btn btn-success w-100">Hoàn tất đơn</button>
                                </form>
                            <?php endif; ?>
                            <a href="../bike-detail.php?id=<?= e((int) ($order['bike_id'] ?? 0)) ?>" class="btn btn-success">Xem xe</a>
                            <a href="orders.php" class="btn btn-light border">Quay lại danh sách</a>
                        </div>
                    </section>
                </div>
            </div>
        </section>
    </main>

    <footer id="contact">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="fw-bold mb-3">Bike Marketplace</h4>
                    <p>Nền tảng mua bán xe đạp hiện đại giúp người bán theo dõi yêu cầu mua, xử lý giao dịch và kết nối hiệu quả với khách hàng.</p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Thông tin liên hệ</h5>
                    <p class="mb-2"><i class="bi bi-geo-alt me-2"></i> 128 Market Street, Ho Chi Minh City</p>
                    <p class="mb-2"><i class="bi bi-telephone me-2"></i> +84 901 234 567</p>
                    <p class="mb-0"><i class="bi bi-envelope me-2"></i> hello@bikemarketplace.com</p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Theo dõi chúng tôi</h5>
                    <div class="d-flex gap-2 mb-3">
                        <a class="social-link" href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a class="social-link" href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a class="social-link" href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
                        <a class="social-link" href="#" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                    </div>
                    <p class="mb-0">Hỗ trợ mỗi ngày từ 8:00 AM đến 8:00 PM dành cho cộng đồng mua bán xe đạp.</p>
                </div>
            </div>
            <div class="border-top border-secondary-subtle mt-4 pt-4 text-center text-white-50">
                <small>&copy; 2026 Bike Marketplace. Trang chi tiết đơn mua của người bán được xây dựng với HTML, CSS, Bootstrap 5 và Bootstrap Icons.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
