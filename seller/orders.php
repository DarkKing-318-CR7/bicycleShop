<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('seller');

$currentUser = currentUser();
$sellerId = (int) ($currentUser['id'] ?? 0);
$sellerName = $currentUser['full_name'] ?? 'Người bán';
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80';

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
        case 'shipping':
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

    if (in_array('total_price', $columns, true)) {
        return 'total_price';
    }

    return 'total_amount';
}

$amountColumn = getOrderAmountColumn($conn);

$stats = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'shipping' => 0,
    'completed' => 0,
];

$statsSql = "
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN o.status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_orders,
        SUM(CASE WHEN o.status = 'shipping' THEN 1 ELSE 0 END) AS shipping_orders,
        SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) AS completed_orders
    FROM orders o
    INNER JOIN bikes b ON b.id = o.bike_id
    WHERE b.seller_id = ?
";
$statsStmt = $conn->prepare($statsSql);

if ($statsStmt) {
    $statsStmt->bind_param('i', $sellerId);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $statsRow = $statsResult ? $statsResult->fetch_assoc() : null;
    $statsStmt->close();

    if ($statsRow) {
        $stats['total'] = (int) ($statsRow['total_orders'] ?? 0);
        $stats['pending'] = (int) ($statsRow['pending_orders'] ?? 0);
        $stats['confirmed'] = (int) ($statsRow['confirmed_orders'] ?? 0);
        $stats['shipping'] = (int) ($statsRow['shipping_orders'] ?? 0);
        $stats['completed'] = (int) ($statsRow['completed_orders'] ?? 0);
    }
}

$orders = [];
$sql = "
    SELECT
        o.id,
        o.status,
        o.created_at,
        o.{$amountColumn} AS order_total,
        COALESCE(b.title, 'Xe đạp thể thao') AS bike_title,
        COALESCE(u.full_name, 'Người mua') AS buyer_name,
        COALESCE(img.image_url, ?) AS image_url
    FROM orders o
    INNER JOIN bikes b ON b.id = o.bike_id
    LEFT JOIN users u ON u.id = o.buyer_id
    LEFT JOIN bike_images img ON img.id = (
        SELECT bi.id
        FROM bike_images bi
        WHERE bi.bike_id = b.id
        ORDER BY bi.id ASC
        LIMIT 1
    )
    WHERE b.seller_id = ?
    ORDER BY o.created_at DESC, o.id DESC
";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param('si', $fallbackImage, $sellerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Đơn mua xe của tôi</title>
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
                    <a href="add-bike.php" class="btn btn-success">Đăng tin mới</a>
                    <a href="../logout.php" class="btn btn-outline-dark"><?= e($sellerName) ?></a>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Người bán <span>/</span> Đơn mua xe của tôi</div>
                <h1 class="section-title text-white mb-2">Đơn mua xe của tôi</h1>
                <p class="mb-0 text-white-50">Quản lý các yêu cầu mua và giao dịch từ người mua.</p>
            </div>
        </section>

        <section class="container">
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-receipt"></i></span>
                        <div><small>Tổng đơn</small><strong><?= e(number_format($stats['total'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-hourglass-split"></i></span>
                        <div><small>Chờ xác nhận</small><strong><?= e(number_format($stats['pending'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-patch-check"></i></span>
                        <div><small>Đã xác nhận</small><strong><?= e(number_format($stats['confirmed'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-arrow-repeat"></i></span>
                        <div><small>Đang giao dịch</small><strong><?= e(number_format($stats['shipping'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-bag-check"></i></span>
                        <div><small>Hoàn tất</small><strong><?= e(number_format($stats['completed'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="manage-card">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                    <div>
                        <h2 class="section-title fs-2 mb-2">Danh sách đơn mua</h2>
                        <p class="section-subtitle mb-0">Theo dõi các yêu cầu mua được gửi tới tin đăng của bạn và xử lý giao dịch theo từng trạng thái.</p>
                    </div>
                </div>

                <div class="listing-list">
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                            <?php $statusMeta = getOrderStatusMeta((string) ($order['status'] ?? 'pending')); ?>
                            <article class="listing-item">
                                <div class="listing-grid">
                                    <img class="listing-thumb" src="<?= e($order['image_url'] ?? $fallbackImage) ?>" alt="<?= e($order['bike_title'] ?? 'Xe đạp thể thao') ?>">
                                    <div>
                                        <div class="listing-title">#<?= e((int) ($order['id'] ?? 0)) ?></div>
                                        <div class="listing-sub mb-2"><?= e($order['bike_title'] ?? 'Xe đạp thể thao') ?></div>
                                        <div class="listing-meta">
                                            <span><i class="bi bi-person me-1"></i> Người mua: <?= e($order['buyer_name'] ?? 'Người mua') ?></span>
                                            <span><i class="bi bi-cash me-1"></i> <?= e(formatPriceVnd($order['order_total'] ?? 0)) ?></span>
                                            <span><i class="bi bi-calendar-event me-1"></i> <?= e(formatDateVi($order['created_at'] ?? null)) ?></span>
                                        </div>
                                    </div>
                                    <div class="listing-side">
                                        <span class="status-badge <?= e($statusMeta['class']) ?>"><?= e($statusMeta['label']) ?></span>
                                    </div>
                                    <div class="listing-actions">
                                        <a href="order-detail.php?id=<?= e((int) ($order['id'] ?? 0)) ?>" class="btn btn-outline-dark">Xem chi tiết</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="helper-card mt-4 text-center">
                            <div class="stats-icon mx-auto mb-3"><i class="bi bi-inbox"></i></div>
                            <h3 class="section-heading">Chưa có đơn hàng</h3>
                            <p class="text-muted mb-3">Hiện chưa có đơn mua nào cho xe của bạn.</p>
                            <a href="add-bike.php" class="btn btn-success">Đăng thêm xe</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer id="contact">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="fw-bold mb-3">Bike Marketplace</h4>
                    <p>Nền tảng mua bán xe đạp hiện đại giúp người bán theo dõi đơn mua, xác nhận giao dịch và quản lý trao đổi với người mua hiệu quả.</p>
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
                <small>&copy; 2026 Bike Marketplace. Trang đơn mua của người bán được xây dựng với HTML, CSS, Bootstrap 5 và Bootstrap Icons.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
