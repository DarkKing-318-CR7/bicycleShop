<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('buyer');

$currentUser = currentUser();
$buyerId = (int) ($currentUser['id'] ?? 0);
$buyerName = $currentUser['full_name'] ?? 'Tài khoản';
$buyerEmail = $currentUser['email'] ?? '';
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80';

$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');
$contactSent = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_form']);

$allowedStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$allowedSorts = ['newest', 'oldest', 'price_desc', 'price_asc'];
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

function buildOrderCode(array $order): string
{
    $orderCode = trim((string) ($order['order_code'] ?? ''));

    if ($orderCode !== '') {
        return $orderCode;
    }

    $id = (int) ($order['id'] ?? 0);
    return 'ORD-' . date('Y') . '-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
}

$stats = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
];

$statsSql = "
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN status IN ('confirmed', 'in_progress') THEN 1 ELSE 0 END) AS confirmed_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_orders
    FROM orders
    WHERE buyer_id = ?
";

$statsStmt = $conn->prepare($statsSql);
if ($statsStmt) {
    $statsStmt->bind_param('i', $buyerId);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $statsRow = $statsResult ? $statsResult->fetch_assoc() : null;
    $statsStmt->close();

    if ($statsRow) {
        $stats['total'] = (int) ($statsRow['total_orders'] ?? 0);
        $stats['pending'] = (int) ($statsRow['pending_orders'] ?? 0);
        $stats['confirmed'] = (int) ($statsRow['confirmed_orders'] ?? 0);
        $stats['completed'] = (int) ($statsRow['completed_orders'] ?? 0);
    }
}

$sql = "
    SELECT
        o.id,
        o.order_code,
        o.bike_id,
        o.offered_price,
        o.contact_method,
        o.meeting_location,
        o.buyer_note,
        o.payment_method,
        o.payment_status,
        o.status,
        o.created_at,
        COALESCE(b.title, 'Xe đạp thể thao') AS bike_title,
        COALESCE(b.location, 'Đang cập nhật') AS bike_location,
        COALESCE(c.name, 'Danh mục khác') AS category_name,
        COALESCE(br.name, '') AS brand_name,
        COALESCE(s.full_name, 'Người bán') AS seller_name,
        COALESCE(img.image_url, ?) AS image_url
    FROM orders o
    LEFT JOIN bikes b ON b.id = o.bike_id
    LEFT JOIN categories c ON c.id = b.category_id
    LEFT JOIN brands br ON br.id = b.brand_id
    LEFT JOIN users s ON s.id = o.seller_id
    LEFT JOIN bike_images img ON img.id = (
        SELECT bi.id
        FROM bike_images bi
        WHERE bi.bike_id = b.id
        ORDER BY bi.id ASC
        LIMIT 1
    )
    WHERE o.buyer_id = ?
";

$params = [$fallbackImage, $buyerId];
$types = 'si';

if ($keyword !== '') {
    $sql .= " AND (b.title LIKE ? OR o.order_code LIKE ?)";
    $likeKeyword = '%' . $keyword . '%';
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $types .= 'ss';
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
        $sql .= " ORDER BY o.offered_price DESC, o.created_at DESC";
        break;
    case 'price_asc':
        $sql .= " ORDER BY o.offered_price ASC, o.created_at DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY o.created_at DESC, o.id DESC";
        break;
}

$orders = [];
$stmt = $conn->prepare($sql);

if ($stmt) {
    $bindValues = [];
    $bindValues[] = &$types;
    foreach ($params as $key => $value) {
        $bindValues[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindValues);
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
    <title>Bike Marketplace | Đơn mua của tôi</title>
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
                    <li class="nav-item"><a class="nav-link" href="../categories.php">Danh mục</a></li>
                    <li class="nav-item"><a class="nav-link" href="../contact.php">Liên hệ</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2">
                    <a href="favorites.php" class="btn btn-outline-dark">Yêu thích</a>
                    <a href="../profile.php" class="btn btn-outline-dark"><?= e($buyerName) ?></a>
                    <a href="../logout.php" class="btn btn-success">Đăng xuất</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Người mua <span>/</span> Đơn mua của tôi</div>
                <h1 class="section-title text-white mb-2">Đơn mua của tôi</h1>
                <p class="mb-0 text-white-50">Theo dõi tình trạng các đơn mua xe đạp của bạn.</p>
            </div>
        </section>

        <section class="container">
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-receipt"></i></span>
                        <div><small>Tổng đơn</small><strong><?= e(number_format($stats['total'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-hourglass-split"></i></span>
                        <div><small>Chờ xác nhận</small><strong><?= e(number_format($stats['pending'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-patch-check"></i></span>
                        <div><small>Đã xác nhận</small><strong><?= e(number_format($stats['confirmed'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-bag-check"></i></span>
                        <div><small>Đã hoàn tất</small><strong><?= e(number_format($stats['completed'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="manage-card">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                    <div>
                        <h2 class="section-title fs-2 mb-2">Danh sách đơn mua</h2>
                        <p class="section-subtitle mb-0">Theo dõi tiến trình xử lý đơn, trạng thái giao dịch và thông tin xe đạp bạn đã đặt mua.</p>
                    </div>
                </div>

                <form method="get" class="toolbar-row">
                    <input
                        type="text"
                        class="form-control"
                        name="keyword"
                        placeholder="Tìm theo mã đơn hoặc tên xe..."
                        value="<?= e($keyword) ?>"
                    >
                    <select class="form-select" name="status">
                        <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Tất cả trạng thái</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
                        <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
                        <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>Đang giao dịch</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Hoàn tất</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                    </select>
                    <select class="form-select" name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Cũ nhất</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Giá cao đến thấp</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Giá thấp đến cao</option>
                    </select>
                    <button type="submit" class="btn btn-outline-dark">Lọc</button>
                </form>

                <div class="listing-list">
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $statusMeta = getOrderStatusMeta((string) ($order['status'] ?? 'pending'));
                            $orderCode = buildOrderCode($order);
                            $brandName = trim((string) ($order['brand_name'] ?? ''));
                            $categoryLabel = $brandName !== '' ? ($order['category_name'] . ' • ' . $brandName) : $order['category_name'];
                            ?>
                            <article class="listing-item">
                                <div class="listing-grid">
                                    <img class="listing-thumb" src="<?= e($order['image_url'] ?? $fallbackImage) ?>" alt="<?= e($order['bike_title'] ?? 'Xe đạp thể thao') ?>">
                                    <div>
                                        <div class="listing-title"><?= e($orderCode) ?></div>
                                        <div class="listing-sub mb-2"><?= e($order['bike_title'] ?? 'Xe đạp thể thao') ?></div>
                                        <div class="listing-meta">
                                            <span><i class="bi bi-person me-1"></i> Người bán: <?= e($order['seller_name'] ?? 'Người bán') ?></span>
                                            <span><i class="bi bi-cash me-1"></i> <?= e(formatPriceVnd($order['offered_price'] ?? 0)) ?></span>
                                            <span><i class="bi bi-calendar-event me-1"></i> <?= e(formatDateVi($order['created_at'] ?? null)) ?></span>
                                        </div>
                                    </div>
                                    <div class="listing-side">
                                        <span class="status-badge <?= e($statusMeta['class']) ?>"><?= e($statusMeta['label']) ?></span>
                                        <div class="listing-meta">
                                            <span><i class="bi bi-bicycle me-1"></i> <?= e($categoryLabel) ?></span>
                                        </div>
                                    </div>
                                    <div class="listing-actions">
                                        <a href="order-detail.php?id=<?= e((int) ($order['id'] ?? 0)) ?>" class="btn btn-success">Xem chi tiết</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="helper-card mt-4 text-center">
                            <div class="stats-icon mx-auto mb-3"><i class="bi bi-receipt-cutoff"></i></div>
                            <h3 class="section-heading">Bạn chưa có đơn mua nào</h3>
                            <p class="text-muted mb-3">Hãy khám phá các mẫu xe đạp đang được đăng bán và tạo đơn mua đầu tiên của bạn.</p>
                            <a href="../bikes.php" class="btn btn-success">Khám phá xe đạp</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <section class="contact-section" id="contact">
        <div class="container">
            <div class="contact-panel">
                <div class="row g-4 align-items-stretch">
                    <div class="col-lg-5">
                        <div class="contact-info h-100">
                            <div class="section-label text-warning">Liên hệ</div>
                            <h2 class="section-title text-white mb-3">Cần hỗ trợ đơn mua?</h2>
                            <p class="contact-copy">Người mua có thể gửi yêu cầu hỗ trợ về đơn hàng, lịch hẹn xem xe, thanh toán hoặc trao đổi với người bán.</p>
                            <div class="contact-list">
                                <div class="contact-item">
                                    <span><i class="bi bi-geo-alt"></i></span>
                                    <div>
                                        <strong>Địa chỉ</strong>
                                        <p>128 Market Street, Ho Chi Minh City</p>
                                    </div>
                                </div>
                                <div class="contact-item">
                                    <span><i class="bi bi-telephone"></i></span>
                                    <div>
                                        <strong>Hotline</strong>
                                        <p>+84 901 234 567</p>
                                    </div>
                                </div>
                                <div class="contact-item">
                                    <span><i class="bi bi-envelope"></i></span>
                                    <div>
                                        <strong>Email</strong>
                                        <p>buyer-support@bikemarketplace.com</p>
                                    </div>
                                </div>
                                <div class="contact-item">
                                    <span><i class="bi bi-clock"></i></span>
                                    <div>
                                        <strong>Giờ hỗ trợ</strong>
                                        <p>8:00 AM - 8:00 PM mỗi ngày</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <form class="contact-form h-100" action="my-orders.php#contact" method="post">
                            <input type="hidden" name="contact_form" value="1">
                            <?php if ($contactSent): ?>
                                <div class="alert alert-success" role="alert">
                                    Cảm ơn bạn đã liên hệ. Bike Marketplace sẽ phản hồi trong thời gian sớm nhất.
                                </div>
                            <?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_name">Họ và tên</label>
                                    <input type="text" class="form-control" id="contact_name" name="contact_name" value="<?= e($buyerName) ?>" placeholder="Nhập họ và tên">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_phone">Số điện thoại</label>
                                    <input type="tel" class="form-control" id="contact_phone" name="contact_phone" placeholder="Nhập số điện thoại">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_email">Email</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= e($buyerEmail) ?>" placeholder="email@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_topic">Chủ đề</label>
                                    <select class="form-select" id="contact_topic" name="contact_topic">
                                        <option>Hỗ trợ đơn mua</option>
                                        <option>Tư vấn mua xe</option>
                                        <option>Hỗ trợ giao dịch</option>
                                        <option>Góp ý hệ thống</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="contact_message">Nội dung</label>
                                    <textarea class="form-control" id="contact_message" name="contact_message" rows="5" placeholder="Bạn cần hỗ trợ điều gì?"></textarea>
                                </div>
                                <div class="col-12 d-grid d-sm-flex justify-content-sm-end">
                                    <button type="submit" class="btn btn-success px-4">
                                        <i class="bi bi-send me-2"></i>Gửi liên hệ
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="fw-bold mb-3">Bike Marketplace</h4>
                    <p>Nền tảng mua bán xe đạp hiện đại giúp người mua theo dõi đơn hàng, xem lại lịch sử giao dịch và kết nối thuận tiện với người bán.</p>
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
                <small>&copy; 2026 Bike Marketplace. Trang đơn mua được xây dựng với PHP, CSS, Bootstrap 5 và Bootstrap Icons.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
