<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('buyer');

$currentUser = currentUser();
$buyerId = (int) ($currentUser['id'] ?? 0);
$buyerName = $currentUser['full_name'] ?? 'Tài khoản';
$orderId = (int) ($_GET['id'] ?? 0);
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80';
$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

if ($orderId <= 0) {
    redirect('my-orders.php');
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

function getConditionLabel(string $condition): string
{
    switch ($condition) {
        case 'new':
            return 'Mới';
        case 'like_new':
            return 'Đã qua sử dụng - Rất tốt';
        case 'used':
        default:
            return 'Đã qua sử dụng - Tốt';
    }
}

function buildOrderCode(array $order): string
{
    $orderCode = trim((string) ($order['order_code'] ?? ''));

    if ($orderCode !== '') {
        return $orderCode;
    }

    $id = (int) ($order['id'] ?? 0);
    return 'ORD-' . date('Y') . '-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $cancelStmt = $conn->prepare("
        UPDATE orders
        SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
          AND buyer_id = ?
          AND status = 'pending'
    ");

    if ($cancelStmt) {
        $cancelStmt->bind_param('ii', $orderId, $buyerId);
        $cancelStmt->execute();
        $updated = $cancelStmt->affected_rows > 0;
        $cancelStmt->close();

        if ($updated) {
            $_SESSION['success_message'] = 'Đã hủy đơn mua thành công.';
        } else {
            $_SESSION['error_message'] = 'Không thể hủy đơn. Đơn có thể đã được người bán xử lý.';
        }
    } else {
        $_SESSION['error_message'] = 'Không thể xử lý yêu cầu hủy đơn lúc này.';
    }

    redirect('order-detail.php?id=' . $orderId);
}

$order = null;
$sql = "
    SELECT
        o.id,
        o.order_code,
        o.bike_id,
        o.quantity,
        o.offered_price,
        o.contact_method,
        o.meeting_location,
        o.buyer_note,
        o.status,
        o.payment_method,
        o.payment_status,
        o.created_at,
        o.updated_at,
        b.title AS bike_title,
        b.price AS bike_price,
        b.description AS bike_description,
        b.condition_status,
        b.frame_size,
        b.wheel_size,
        b.color,
        b.location AS bike_location,
        COALESCE(c.name, 'Danh mục khác') AS category_name,
        COALESCE(br.name, '') AS brand_name,
        COALESCE(s.full_name, 'Người bán') AS seller_name,
        COALESCE(s.phone, 'Đang cập nhật') AS seller_phone,
        COALESCE(s.email, 'Đang cập nhật') AS seller_email,
        COALESCE(img.image_url, ?) AS image_url
    FROM orders o
    LEFT JOIN bikes b ON b.id = o.bike_id
    LEFT JOIN categories c ON c.id = b.category_id
    LEFT JOIN brands br ON br.id = b.brand_id
    LEFT JOIN users s ON s.id = b.seller_id
    LEFT JOIN bike_images img ON img.id = (
        SELECT bi.id
        FROM bike_images bi
        WHERE bi.bike_id = b.id
        ORDER BY bi.is_primary DESC, bi.sort_order ASC, bi.id ASC
        LIMIT 1
    )
    WHERE o.id = ? AND o.buyer_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param('sii', $fallbackImage, $orderId, $buyerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$order) {
    redirect('my-orders.php');
}

$statusMeta = getOrderStatusMeta((string) ($order['status'] ?? 'pending'));
$orderCode = buildOrderCode($order);
$note = trim((string) ($order['buyer_note'] ?? ''));
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
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
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
                    <a href="my-orders.php" class="btn btn-outline-dark">Đơn mua</a>
                    <a href="favorites.php" class="btn btn-outline-dark">Yêu thích</a>
                    <a href="../logout.php" class="btn btn-success"><?= e($buyerName) ?></a>
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
                    <a href="my-orders.php" class="text-white text-decoration-none">Đơn mua của tôi</a>
                    <span>/</span>
                    Chi tiết đơn
                </div>
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <h1 class="section-title text-white mb-2">Chi tiết đơn mua</h1>
                        <p class="mb-0 text-white-50">Theo dõi thông tin xe, người bán và trạng thái xử lý đơn mua.</p>
                    </div>
                    <span class="status-badge <?= e($statusMeta['class']) ?> bg-white border-0">Mã đơn: <?= e($orderCode) ?></span>
                </div>
            </div>
        </section>

        <section class="container">
            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success" role="alert"><?= e($successMessage) ?></div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= e($errorMessage) ?></div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-4">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-hourglass-split"></i></span>
                        <div><small>Trạng thái đơn</small><strong><?= e($statusMeta['label']) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-calendar-event"></i></span>
                        <div><small>Ngày đặt</small><strong><?= e(formatDateVi($order['created_at'] ?? null)) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-cash-stack"></i></span>
                        <div><small>Tổng thanh toán</small><strong><?= e(formatPriceVnd($order['offered_price'] ?? 0)) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <section class="content-card mb-4">
                        <h2 class="section-heading">Thông tin xe đạp</h2>
                        <div class="row g-4 align-items-start">
                            <div class="col-md-5">
                                <img src="<?= e($order['image_url'] ?? $fallbackImage) ?>" alt="<?= e($order['bike_title'] ?? 'Xe đạp thể thao') ?>" class="img-fluid rounded-4 shadow-sm">
                            </div>
                            <div class="col-md-7">
                                <h3 class="section-title fs-3 mb-2"><?= e($order['bike_title'] ?? 'Xe đạp thể thao') ?></h3>
                                <div class="price mb-3"><?= e(formatPriceVnd($order['bike_price'] ?? 0)) ?></div>
                                <p class="text-muted mb-4"><?= e($order['bike_description'] ?: 'Người bán chưa cập nhật mô tả chi tiết cho xe này.') ?></p>
                                <div class="meta-grid">
                                    <div class="meta-item"><small>Danh mục</small><strong><?= e($order['category_name'] ?? 'Danh mục khác') ?></strong></div>
                                    <div class="meta-item"><small>Hãng</small><strong><?= e($order['brand_name'] ?: 'Đang cập nhật') ?></strong></div>
                                    <div class="meta-item"><small>Tình trạng</small><strong><?= e(getConditionLabel((string) ($order['condition_status'] ?? 'used'))) ?></strong></div>
                                    <div class="meta-item"><small>Khung</small><strong><?= e($order['frame_size'] ?: 'Đang cập nhật') ?></strong></div>
                                    <div class="meta-item"><small>Bánh</small><strong><?= e($order['wheel_size'] ?: 'Đang cập nhật') ?></strong></div>
                                    <div class="meta-item"><small>Màu sắc</small><strong><?= e($order['color'] ?: 'Đang cập nhật') ?></strong></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="content-card mb-4">
                        <h2 class="section-heading">Thông tin đơn mua</h2>
                        <div class="meta-grid mb-4">
                            <div class="meta-item"><small>Mã đơn </small><strong><?= e($orderCode) ?></strong></div>
                            <div class="meta-item"><small>Ngày đặt </small><strong><?= e(formatDateVi($order['created_at'] ?? null)) ?></strong></div>
                            <div class="meta-item"><small>Trạng thái </small><strong><?= e($statusMeta['label']) ?></strong></div>
                            <div class="meta-item"><small>Phương thức liên hệ </small><strong><?= e($order['contact_method'] ?: "Đang cập nhật") ?></strong></div>
                            <div class="meta-item"><small>Phương thức thanh toán </small><strong><?= e($order['payment_method'] ?: "Đang cập nhật") ?></strong></div>
                            <div class="meta-item"><small>Địa điểm giao dịch </small><strong><?= e($order['meeting_location'] ?: "Đang cập nhật") ?></strong></div>
                            <div class="meta-item"><small>Số lượng </small><strong><?= e((int) ($order['quantity'] ?? 1)) ?></strong></div>
                            <div class="meta-item"><small>Thanh toán </small><strong><?= e($order['payment_status'] ?: "Đang cập nhật") ?></strong></div>
                            <div class="meta-item"><small>Ghi chú </small><strong><?= nl2br(e($note !== "" ? $note : "Không có ghi chú")) ?></strong></div>
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <aside class="sidebar-card mb-4">
                        <h2 class="section-heading">Thông tin người bán</h2>
                        <div class="text-center">
                            <div class="stats-icon mx-auto mb-3"><i class="bi bi-person"></i></div>
                            <h3 class="h5 fw-bold mb-2"><?= e($order['seller_name'] ?? 'Người bán') ?></h3>
                            <p class="mb-2"><i class="bi bi-telephone me-2"></i><?= e($order['seller_phone'] ?? 'Đang cập nhật') ?></p>
                            <p class="mb-2"><i class="bi bi-envelope me-2"></i><?= e($order['seller_email'] ?? 'Đang cập nhật') ?></p>
                            <p class="mb-0"><i class="bi bi-geo-alt me-2"></i><?= e($order['bike_location'] ?? 'Đang cập nhật') ?></p>
                        </div>
                    </aside>

                    <section class="sidebar-card mb-4">
                        <h2 class="section-heading">Tóm tắt thanh toán</h2>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span>Giá xe</span>
                            <strong><?= e(formatPriceVnd($order['bike_price'] ?? 0)) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span>Phí giao dịch</span>
                            <strong>0đ</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-3">
                            <span class="fw-semibold">Tổng cộng</span>
                            <strong class="price mb-0"><?= e(formatPriceVnd($order['offered_price'] ?? 0)) ?></strong>
                        </div>
                    </section>

                    <section class="sidebar-card">
                        <h2 class="section-heading">Thao tác</h2>
                        <div class="d-grid gap-2">
                            <a href="my-orders.php" class="btn btn-outline-dark">Quay lại đơn mua</a>
                            <a href="../bike-detail.php?id=<?= e((int) ($order['bike_id'] ?? 0)) ?>" class="btn btn-success">Xem chi tiết xe</a>
                            <a href="../bikes.php" class="btn btn-outline-success">Tiếp tục xem xe khác</a>
                            <?php if (($order['status'] ?? '') === 'pending'): ?>
                                <form method="post" onsubmit="return confirm('Bạn có chắc muốn hủy đơn mua này?');">
                                    <button type="submit" name="cancel_order" class="btn btn-outline-danger w-100">Hủy đơn</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small mt-3 mb-0">Bạn chỉ có thể hủy đơn khi người bán chưa xác nhận.</p>
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
                    <p>Nền tảng mua bán xe đạp hiện đại giúp người mua theo dõi tiến trình giao dịch và kết nối dễ dàng với người bán.</p>
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
                <small>&copy; 2026 Bike Marketplace. Trang chi tiết đơn mua được xây dựng với PHP, CSS, Bootstrap 5 và Bootstrap Icons.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
