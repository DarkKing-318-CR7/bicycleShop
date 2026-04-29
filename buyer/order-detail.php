<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('buyer');

$currentUser = currentUser();
$isLoggedIn = isLoggedIn();
$userRole = $currentUser['role'] ?? '';
$buyerId = (int) ($currentUser['id'] ?? 0);
$userName = $currentUser['full_name'] ?? 'T?i kho?n';
$buyerName = $userName;
$orderId = (int) ($_GET['id'] ?? 0);
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80';

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

$sql = "
    SELECT
        o.id,
        o.order_code,
        o.offered_price,
        o.contact_method,
        o.meeting_location,
        o.buyer_note,
        o.status,
        o.payment_method,
        o.payment_status,
        o.created_at,
        o.updated_at,
        b.id AS bike_id,
        COALESCE(b.title, 'Xe đạp thể thao') AS bike_title,
        b.price AS bike_price,
        COALESCE(c.name, 'Danh mục khác') AS category_name,
        COALESCE(br.name, '') AS brand_name,
        COALESCE(seller.full_name, 'Người bán') AS seller_name,
        COALESCE(seller.email, 'Đang cập nhật') AS seller_email,
        COALESCE(seller.phone, 'Đang cập nhật') AS seller_phone,
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
    WHERE o.id = ? AND o.buyer_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$order = null;

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
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Người mua <span>/</span> Chi tiết đơn mua</div>
                <h1 class="section-title text-white mb-2">Chi tiết đơn mua</h1>
                <p class="mb-0 text-white-50">Xem lại thông tin xe, người bán và trạng thái xử lý giao dịch của bạn.</p>
            </div>
        </section>

        <section class="container">
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-4">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-hourglass-split"></i></span>
                        <div><small>Trạng thái đơn</small><strong><?= e($statusMeta['label']) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-credit-card"></i></span>
                        <div><small>Thanh toán</small><strong><?= e(getPaymentStatusLabel((string) ($order['payment_status'] ?? 'unpaid'))) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-calendar-event"></i></span>
                        <div><small>Ngày tạo</small><strong><?= e(formatDateVi($order['created_at'] ?? null)) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <section class="content-card mb-4">
                        <h2 class="section-heading">Thông tin xe</h2>
                        <div class="row g-4 align-items-start">
                            <div class="col-md-5">
                                <img src="<?= e((string) ($order['image_url'] ?? $fallbackImage)) ?>" alt="<?= e((string) ($order['bike_title'] ?? 'Xe đạp thể thao')) ?>" class="img-fluid rounded-4 shadow-sm">
                            </div>
                            <div class="col-md-7">
                                <h3 class="section-title fs-3 mb-2"><?= e((string) ($order['bike_title'] ?? 'Xe đạp thể thao')) ?></h3>
                                <div class="price mb-3"><?= e(formatPriceVnd($order['bike_price'] ?? 0)) ?></div>
                                <div class="meta-grid">
                                    <div class="meta-item"><small>Danh mục</small><strong><?= e((string) ($order['category_name'] ?? 'Danh mục khác')) ?></strong></div>
                                    <div class="meta-item"><small>Thương hiệu</small><strong><?= e((string) ((isset($order['brand_name']) && $order['brand_name'] !== '') ? $order['brand_name'] : 'Đang cập nhật')) ?></strong></div>
                                    <div class="meta-item"><small>Giá đề nghị</small><strong><?= e(formatPriceVnd($order['offered_price'] ?? 0)) ?></strong></div>
                                    <div class="meta-item"><small>Mã xe</small><strong>#<?= e((int) ($order['bike_id'] ?? 0)) ?></strong></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="content-card">
                        <h2 class="section-heading">Thông tin giao dịch</h2>
                        <div class="meta-grid">
                            <div class="meta-item"><small>Mã đơn</small><strong><?= e((string) ($order['order_code'] ?? 'ORD')) ?></strong></div>
                            <div class="meta-item"><small>Trạng thái đơn</small><strong><?= e($statusMeta['label']) ?></strong></div>
                            <div class="meta-item"><small>Trạng thái thanh toán</small><strong><?= e(getPaymentStatusLabel((string) ($order['payment_status'] ?? 'unpaid'))) ?></strong></div>
                            <div class="meta-item"><small>Ngày tạo</small><strong><?= e(formatDateVi($order['created_at'] ?? null)) ?></strong></div>
                            <div class="meta-item"><small>Cập nhật gần nhất</small><strong><?= e(formatDateVi($order['updated_at'] ?? null)) ?></strong></div>
                            <div class="meta-item"><small>Phương thức liên hệ</small><strong><?= e((string) ((isset($order['contact_method']) && trim((string) $order['contact_method']) !== '') ? $order['contact_method'] : 'Đang cập nhật')) ?></strong></div>
                            <div class="meta-item"><small>Địa điểm gặp/giao dịch</small><strong><?= e((string) ((isset($order['meeting_location']) && trim((string) $order['meeting_location']) !== '') ? $order['meeting_location'] : 'Đang cập nhật')) ?></strong></div>
                            <div class="meta-item"><small>Phương thức thanh toán</small><strong><?= e((string) ((isset($order['payment_method']) && trim((string) $order['payment_method']) !== '') ? $order['payment_method'] : 'Đang cập nhật')) ?></strong></div>
                            <div class="meta-item"><small>Ghi chú của buyer</small><strong><?= e((string) ((isset($order['buyer_note']) && trim((string) $order['buyer_note']) !== '') ? $order['buyer_note'] : 'Không có ghi chú')) ?></strong></div>
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <aside class="sidebar-card mb-4">
                        <h2 class="section-heading">Thông tin người bán</h2>
                        <div class="text-center">
                            <div class="stats-icon mx-auto mb-3"><i class="bi bi-person"></i></div>
                            <h3 class="h5 fw-bold mb-2"><?= e((string) ($order['seller_name'] ?? 'Người bán')) ?></h3>
                            <p class="mb-2"><i class="bi bi-envelope me-2"></i><?= e((string) ($order['seller_email'] ?? 'Đang cập nhật')) ?></p>
                            <p class="mb-0"><i class="bi bi-telephone me-2"></i><?= e((string) ($order['seller_phone'] ?? 'Đang cập nhật')) ?></p>
                        </div>
                    </aside>

                    <section class="sidebar-card">
                        <h2 class="section-heading">Điều hướng</h2>
                        <div class="d-grid gap-2">
                            <a href="../bike-detail.php?id=<?= e((int) ($order['bike_id'] ?? 0)) ?>" class="btn btn-success">Xem xe</a>
                            <a href="my-orders.php" class="btn btn-light border">Quay lại đơn mua</a>
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
                    <p>Nền tảng mua bán xe đạp giúp bạn theo dõi đơn mua rõ ràng và kết nối trực tiếp với người bán.</p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Liên kết nhanh</h5>
                    <p class="mb-2"><a href="../bikes.php">Khám phá xe đạp</a></p>
                    <p class="mb-2"><a href="../categories.php">Danh mục</a></p>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
