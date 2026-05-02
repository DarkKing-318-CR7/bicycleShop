<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('buyer');

$currentUser = currentUser();
$isLoggedIn = isLoggedIn();
$userRole = $currentUser['role'] ?? '';
$buyerId = (int) ($currentUser['id'] ?? 0);
$userName = $currentUser['full_name'] ?? 'Tài khoản';
$buyerName = $userName;
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80';

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

$orders = [];

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

    return date('d/m/Y H:i', $timestamp);
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

function getPaymentStatusLabel(string $status): string
{
    return $status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán';
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

$sql = "
    SELECT
        o.id,
        o.order_code,
        o.offered_price,
        o.status,
        o.payment_method,
        o.payment_status,
        o.contact_method,
        o.meeting_location,
        o.buyer_note,
        o.created_at,
        b.id AS bike_id,
        COALESCE(b.title, 'Xe đạp thể thao') AS bike_title,
        COALESCE(c.name, 'Danh mục khác') AS category_name,
        COALESCE(br.name, '') AS brand_name,
        COALESCE(seller.full_name, 'Người bán') AS seller_name,
        COALESCE(img.image_url, ?) AS image_url
    FROM orders o
    INNER JOIN bikes b ON b.id = o.bike_id
    LEFT JOIN users seller ON seller.id = o.seller_id
    LEFT JOIN categories c ON c.id = b.category_id
    LEFT JOIN brands br ON br.id = b.brand_id
    LEFT JOIN bike_images img ON img.id = (
        SELECT bi.id
        FROM bike_images bi
        WHERE bi.bike_id = b.id
        ORDER BY bi.is_primary DESC, bi.sort_order ASC, bi.id ASC
        LIMIT 1
    )
    WHERE o.buyer_id = ?
";

$params = [$fallbackImage, $buyerId];
$types = 'si';

if ($keyword !== '') {
    $sql .= " AND (o.order_code LIKE ? OR b.title LIKE ? OR seller.full_name LIKE ?)";
    $likeKeyword = '%' . $keyword . '%';
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
    $types .= 'sss';
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
    <title>Bike Marketplace | Đơn mua của tôi</title>
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
                    <li class="nav-item"><a class="nav-link" href="../categories.php">Danh mục</a></li>
                    <li class="nav-item"><a class="nav-link" href="../contact.php">Liên hệ</a></li>
                </ul>
                <div class="dropdown">
                    <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= e($buyerName) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Hồ sơ</a></li>
                        <li><a class="dropdown-item active" href="my-orders.php"><i class="bi bi-receipt me-2"></i>Đơn mua của tôi</a></li>
                        <li><a class="dropdown-item" href="favorites.php"><i class="bi bi-heart me-2"></i>Xe yêu thích</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Người mua <span>/</span> Đơn mua của tôi</div>
                <h1 class="section-title text-white mb-2">Đơn mua của tôi</h1>
                <p class="mb-0 text-white-50">Theo dõi trạng thái giao dịch và xem lại toàn bộ những đơn mua bạn đã tạo.</p>
            </div>
        </section>

        <section class="container">
            <div class="manage-card">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                    <div>
                        <h2 class="section-title fs-2 mb-2">Danh sách đơn mua</h2>
                        <p class="section-subtitle mb-0">Tìm theo mã đơn, tên xe hoặc trạng thái để kiểm tra giao dịch nhanh hơn.</p>
                    </div>
                </div>

                <form method="get" class="toolbar-row">
                    <input
                        type="text"
                        class="form-control"
                        name="keyword"
                        placeholder="Tìm theo mã đơn, tên xe, người bán"
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
                    <?php if ($keyword !== '' || $statusFilter !== '' || $sort !== 'newest'): ?>
                        <a href="my-orders.php" class="btn btn-outline-dark">Xóa lọc</a>
                    <?php endif; ?>
                </form>

                <div class="listing-list">
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $statusMeta = getOrderStatusMeta((string) ($order['status'] ?? 'pending'));
                            $brandName = trim((string) ($order['brand_name'] ?? ''));
                            $subLine = (string) ($order['category_name'] ?? 'Danh mục khác');
                            if ($brandName !== '') {
                                $subLine .= ' • ' . $brandName;
                            }
                            ?>
                            <article class="listing-item">
                                <div class="listing-grid">
                                    <img class="listing-thumb" src="<?= e((string) ($order['image_url'] ?? $fallbackImage)) ?>" alt="<?= e((string) ($order['bike_title'] ?? 'Xe đạp thể thao')) ?>">
                                    <div>
                                        <div class="listing-title"><?= e((string) ($order['order_code'] ?? 'ORD')) ?></div>
                                        <div class="listing-sub mb-2"><?= e((string) ($order['bike_title'] ?? 'Xe đạp thể thao')) ?></div>
                                        <div class="listing-meta">
                                            <span><i class="bi bi-person me-1"></i> Người bán: <?= e((string) ($order['seller_name'] ?? 'Người bán')) ?></span>
                                            <span><i class="bi bi-tags me-1"></i> <?= e($subLine) ?></span>
                                            <span><i class="bi bi-calendar-event me-1"></i> <?= e(formatDateVi($order['created_at'] ?? null)) ?></span>
                                        </div>
                                    </div>
                                    <div class="listing-side">
                                        <span class="status-badge <?= e($statusMeta['class']) ?>"><?= e($statusMeta['label']) ?></span>
                                        <div class="listing-meta">
                                            <span><i class="bi bi-cash-stack me-1"></i> <?= e(formatPriceVnd($order['offered_price'] ?? 0)) ?></span>
                                            <span><i class="bi bi-credit-card me-1"></i> <?= e(getPaymentStatusLabel((string) ($order['payment_status'] ?? 'unpaid'))) ?></span>
                                        </div>
                                    </div>
                                    <div class="listing-actions">
                                        <a href="order-detail.php?id=<?= e((int) ($order['id'] ?? 0)) ?>" class="btn btn-success">Xem chi tiết</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="helper-card text-center">
                            <div class="stats-icon mx-auto mb-3"><i class="bi bi-receipt"></i></div>
                            <h3 class="section-heading">Bạn chưa có đơn mua nào</h3>
                            <p class="mb-3">Khám phá thêm những mẫu xe phù hợp và tạo đơn mua đầu tiên của bạn.</p>
                            <a href="../bikes.php" class="btn btn-success">Khám phá xe đạp</a>
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
                    <p>Theo dõi trạng thái đơn mua, thông tin xe và người bán trong một giao diện rõ ràng, dễ sử dụng.</p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Liên kết nhanh</h5>
                    <p class="mb-2"><a href="../bikes.php">Khám phá xe đạp</a></p>
                    <p class="mb-2"><a href="../categories.php">Danh mục xe</a></p>
                    <p class="mb-0"><a href="../contact.php">Liên hệ hỗ trợ</a></p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Tài khoản của bạn</h5>
                    <p class="mb-2"><a href="profile.php">Hồ sơ</a></p>
                    <p class="mb-2"><a href="favorites.php">Xe yêu thích</a></p>
                    <p class="mb-0"><a href="../logout.php">Đăng xuất</a></p>
                </div>
            </div>
        </div>
    </footer>

    <?php require __DIR__ . '/../includes/chat-widget.php'; ?>
    <script src="<?= e(baseUrl('js/chat-widget.js')) ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
