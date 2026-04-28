<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('seller');

$currentUser = currentUser();
$sellerId = (int) ($currentUser['id'] ?? 0);
$sellerName = $currentUser['full_name'] ?? 'Người bán';
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80';
$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');

$allowedStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
$allowedSorts = ['newest', 'oldest', 'price_desc', 'price_asc'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'newest';
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

function bindDynamicParams(mysqli_stmt $stmt, string $types, array $values): void
{
    if ($types === '' || empty($values)) {
        return;
    }

    $params = [$types];

    foreach ($values as $key => $value) {
        $params[] = &$values[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $params);
}

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

$amountColumn = getOrderAmountColumn($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    if ($orderId <= 0) {
        $_SESSION['error_message'] = 'Đơn mua không hợp lệ.';
    } elseif (updateSellerOrderStatus($conn, $orderId, $sellerId, $newStatus)) {
        $_SESSION['success_message'] = 'Đã cập nhật trạng thái đơn mua.';
    } else {
        $_SESSION['error_message'] = 'Không thể cập nhật đơn mua. Đơn có thể đã hoàn tất, đã hủy hoặc không thuộc tài khoản của bạn.';
    }

    redirect('orders.php');
}

$stats = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0,
];

$statsSql = "
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN o.status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_orders,
        SUM(CASE WHEN o.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_orders,
        SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
        SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
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
        $stats['in_progress'] = (int) ($statsRow['in_progress_orders'] ?? 0);
        $stats['completed'] = (int) ($statsRow['completed_orders'] ?? 0);
        $stats['cancelled'] = (int) ($statsRow['cancelled_orders'] ?? 0);
    }
}

$orders = [];
$sql = "
    SELECT
        o.id,
        o.order_code,
        o.bike_id,
        o.status,
        o.created_at,
        o.{$amountColumn} AS order_total,
        o.contact_method,
        o.meeting_location,
        COALESCE(b.title, 'Xe ??p th? thao') AS bike_title,
        COALESCE(u.full_name, 'Ng??i mua') AS buyer_name,
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
";

$params = [$fallbackImage, $sellerId];
$types = 'si';

if ($keyword !== '') {
    $sql .= " AND (b.title LIKE ? OR u.full_name LIKE ? OR o.order_code LIKE ? OR o.meeting_location LIKE ? OR CAST(o.id AS CHAR) LIKE ?)";
    $keywordLike = '%' . $keyword . '%';
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $types .= 'sssss';
}

if ($statusFilter !== '') {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

switch ($sort) {
    case 'oldest':
        $sql .= " ORDER BY o.created_at ASC, o.id ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY o.{$amountColumn} DESC, o.created_at DESC";
        break;
    case 'price_asc':
        $sql .= " ORDER BY o.{$amountColumn} ASC, o.created_at DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY o.created_at DESC, o.id DESC";
        break;
}

$stmt = $conn->prepare($sql);

if ($stmt) {
    bindDynamicParams($stmt, $types, $params);
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
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Người bán <span>/</span> Đơn mua xe của tôi</div>
                <h1 class="section-title text-white mb-2">Đơn mua xe của tôi</h1>
                <p class="mb-0 text-white-50">Quản lý các yêu cầu mua và giao dịch từ người mua.</p>
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
                        <div><small>Đang giao dịch</small><strong><?= e(number_format($stats['in_progress'], 0, ',', '.')) ?></strong></div>
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

                <form method="get" class="toolbar-row">
                    <input
                        type="text"
                        class="form-control"
                        name="keyword"
                        placeholder="Tìm theo mã đơn, xe, người mua hoặc địa điểm"
                        value="<?= e($keyword) ?>"
                    >
                    <select name="status" class="form-select">
                        <option value="" <?= $statusFilter === "" ? "selected" : "" ?>>Tất cả trạng thái</option>
                        <option value="pending" <?= $statusFilter === "pending" ? "selected" : "" ?>>Chờ xác nhận</option>
                        <option value="confirmed" <?= $statusFilter === "confirmed" ? "selected" : "" ?>>Đã xác nhận</option>
                        <option value="in_progress" <?= $statusFilter === "in_progress" ? "selected" : "" ?>>Đang giao dịch</option>
                        <option value="completed" <?= $statusFilter === "completed" ? "selected" : "" ?>>Hoàn tất</option>
                        <option value="cancelled" <?= $statusFilter === "cancelled" ? "selected" : "" ?>>Đã hủy</option>
                    </select>
                    <select name="sort" class="form-select">
                        <option value="newest" <?= $sort === "newest" ? "selected" : "" ?>>Mới nhất</option>
                        <option value="oldest" <?= $sort === "oldest" ? "selected" : "" ?>>Cũ nhất</option>
                        <option value="price_desc" <?= $sort === "price_desc" ? "selected" : "" ?>>Giá cao đến thấp</option>
                        <option value="price_asc" <?= $sort === "price_asc" ? "selected" : "" ?>>Giá thấp đến cao</option>
                    </select>
                    <button type="submit" class="btn btn-outline-dark">Lọc</button>
                    <?php if ($keyword !== '' || $statusFilter !== '' || $sort !== 'newest'): ?>
                        <a href="orders.php" class="btn btn-outline-dark">Xóa lọc</a>
                    <?php endif; ?>
                </form>

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
                                        <?php if (($order['status'] ?? '') === 'pending'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?= e((int) ($order['id'] ?? 0)) ?>">
                                                <input type="hidden" name="new_status" value="confirmed">
                                                <button type="submit" name="update_order_status" class="btn btn-success">Xác nhận</button>
                                            </form>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn hủy đơn này?');">
                                                <input type="hidden" name="order_id" value="<?= e((int) ($order['id'] ?? 0)) ?>">
                                                <input type="hidden" name="new_status" value="cancelled">
                                                <button type="submit" name="update_order_status" class="btn btn-outline-dark">Hủy</button>
                                            </form>
                                        <?php elseif (($order['status'] ?? '') === 'confirmed'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?= e((int) ($order['id'] ?? 0)) ?>">
                                                <input type="hidden" name="new_status" value="in_progress">
                                                <button type="submit" name="update_order_status" class="btn btn-success">Đang giao dịch</button>
                                            </form>
                                        <?php elseif (($order['status'] ?? '') === 'in_progress'): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Hoàn tất đơn này và đánh dấu xe đã bán?');">
                                                <input type="hidden" name="order_id" value="<?= e((int) ($order['id'] ?? 0)) ?>">
                                                <input type="hidden" name="new_status" value="completed">
                                                <button type="submit" name="update_order_status" class="btn btn-success">Hoàn tất</button>
                                            </form>
                                        <?php endif; ?>
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
