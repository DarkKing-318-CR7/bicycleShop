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
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80';

$favorites = [];

function formatPriceVnd($price): string
{
    return number_format((float) $price, 0, ',', '.') . 'đ';
}

function getBikeStatusMeta(string $status): array
{
    switch ($status) {
        case 'approved':
            return ['class' => 'status-approved', 'label' => 'Đang bán'];
        case 'sold':
            return ['class' => 'status-sold', 'label' => 'Đã bán'];
        case 'rejected':
            return ['class' => 'status-rejected', 'label' => 'Tạm ẩn'];
        case 'pending':
        default:
            return ['class' => 'status-pending', 'label' => 'Chờ duyệt'];
    }
}

$sql = "
    SELECT
        b.id,
        b.title,
        b.price,
        b.location,
        b.status,
        COALESCE(c.name, 'Danh mục khác') AS category_name,
        COALESCE(br.name, '') AS brand_name,
        COALESCE(img.image_url, ?) AS image_url
    FROM favorites f
    INNER JOIN bikes b ON b.id = f.bike_id
    LEFT JOIN categories c ON c.id = b.category_id
    LEFT JOIN brands br ON br.id = b.brand_id
    LEFT JOIN bike_images img ON img.id = (
        SELECT bi.id
        FROM bike_images bi
        WHERE bi.bike_id = b.id
        ORDER BY bi.is_primary DESC, bi.sort_order ASC, bi.id ASC
        LIMIT 1
    )
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC, f.id DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param('si', $fallbackImage, $buyerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $favorites[] = $row;
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
    <title>Bike Marketplace | Xe yêu thích</title>
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
                        <li><a class="dropdown-item" href="my-orders.php"><i class="bi bi-receipt me-2"></i>Đơn mua của tôi</a></li>
                        <li><a class="dropdown-item active" href="favorites.php"><i class="bi bi-heart me-2"></i>Xe yêu thích</a></li>
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
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Người mua <span>/</span> Xe yêu thích</div>
                <h1 class="section-title text-white mb-2">Xe yêu thích</h1>
                <p class="mb-0 text-white-50">Theo dõi các mẫu xe bạn đã lưu để xem lại và liên hệ người bán nhanh hơn.</p>
            </div>
        </section>

        <section class="container">
            <div class="manage-card">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
                    <div>
                        <h2 class="section-title fs-2 mb-2">Danh sách yêu thích</h2>
                        <p class="section-subtitle mb-0">Tổng cộng <?= e(number_format(count($favorites), 0, ',', '.')) ?> xe đang được lưu trong tài khoản của bạn.</p>
                    </div>
                    <a href="../bikes.php" class="btn btn-success">Khám phá xe đạp</a>
                </div>

                <?php if (!empty($favorites)): ?>
                    <div class="row g-4">
                        <?php foreach ($favorites as $bike): ?>
                            <?php
                            $bikeId = (int) ($bike['id'] ?? 0);
                            $statusMeta = getBikeStatusMeta((string) ($bike['status'] ?? 'pending'));
                            $brandName = trim((string) ($bike['brand_name'] ?? ''));
                            $categoryName = (string) ($bike['category_name'] ?? 'Danh mục khác');
                            $location = trim((string) ($bike['location'] ?? '')) ?: 'Đang cập nhật';
                            ?>
                            <div class="col-md-6 col-xl-4">
                                <article class="bike-card h-100">
                                    <img src="<?= e((string) ($bike['image_url'] ?? $fallbackImage)) ?>" alt="<?= e((string) ($bike['title'] ?? 'Xe đạp thể thao')) ?>">
                                    <div class="content p-4">
                                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                            <span class="status-badge <?= e($statusMeta['class']) ?>"><?= e($statusMeta['label']) ?></span>
                                            <a href="../toggle-favorite.php?bike_id=<?= e($bikeId) ?>" class="text-danger fs-5" aria-label="Bỏ yêu thích">
                                                <i class="bi bi-heart-fill"></i>
                                            </a>
                                        </div>
                                        <h3 class="h5 fw-bold mb-2"><?= e((string) ($bike['title'] ?? 'Xe đạp thể thao')) ?></h3>
                                        <p class="text-muted mb-2"><?= e($brandName !== '' ? $brandName : 'Thương hiệu đang cập nhật') ?></p>
                                        <div class="price mb-3"><?= e(formatPriceVnd($bike['price'] ?? 0)) ?></div>
                                        <div class="bike-card-meta mb-2"><span><i class="bi bi-tags me-1"></i><?= e($categoryName) ?></span></div>
                                        <div class="bike-card-meta mb-4"><span><i class="bi bi-geo-alt me-1"></i><?= e($location) ?></span></div>
                                        <div class="d-grid gap-2">
                                            <a href="../bike-detail.php?id=<?= e($bikeId) ?>" class="btn btn-success">Xem chi tiết</a>
                                            <a href="../toggle-favorite.php?bike_id=<?= e($bikeId) ?>" class="btn btn-outline-dark">Bỏ yêu thích</a>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="helper-card text-center">
                        <div class="stats-icon mx-auto mb-3"><i class="bi bi-heart"></i></div>
                        <h3 class="section-heading">Bạn chưa lưu xe yêu thích nào</h3>
                        <p class="mb-3">Lưu lại những mẫu xe bạn quan tâm để tiện theo dõi và so sánh sau.</p>
                        <a href="../bikes.php" class="btn btn-success">Khám phá xe đạp</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer id="contact">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="fw-bold mb-3">Bike Marketplace</h4>
                    <p>Nền tảng mua bán xe đạp giúp bạn lưu xe yêu thích, so sánh lựa chọn và kết nối nhanh với người bán phù hợp.</p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Liên kết nhanh</h5>
                    <p class="mb-2"><a href="../bikes.php">Xem tất cả xe đạp</a></p>
                    <p class="mb-2"><a href="../categories.php">Danh mục xe</a></p>
                    <p class="mb-0"><a href="../contact.php">Liên hệ hỗ trợ</a></p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Tài khoản của bạn</h5>
                    <p class="mb-2"><a href="profile.php">Hồ sơ</a></p>
                    <p class="mb-2"><a href="my-orders.php">Đơn mua của tôi</a></p>
                    <p class="mb-0"><a href="../logout.php">Đăng xuất</a></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
