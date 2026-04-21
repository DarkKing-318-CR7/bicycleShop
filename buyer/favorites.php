<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (!hasRole('buyer')) {
    redirect('../index.php');
}

$currentUser = currentUser();
$buyerId = (int) ($currentUser['id'] ?? 0);
$buyerName = $currentUser['full_name'] ?? 'Tài khoản';
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=800&q=80';

$favoriteBikes = [];
$totalFavorites = 0;
$recentUpdates = 0;
$discountedBikes = 0;

function formatPriceVnd($price): string
{
    return number_format((float) $price, 0, ',', '.') . 'đ';
}

function fallbackValue($value, string $fallback = 'Đang cập nhật'): string
{
    $value = trim((string) $value);
    return $value !== '' ? $value : $fallback;
}

$sql = "
    SELECT
        b.id,
        b.title,
        b.price,
        b.location,
        b.condition_status,
        b.created_at,
        c.name AS category_name,
        br.name AS brand_name,
        COALESCE((
            SELECT bi.image_url
            FROM bike_images bi
            WHERE bi.bike_id = b.id
            ORDER BY bi.id ASC
            LIMIT 1
        ), ?) AS image
    FROM favorites f
    INNER JOIN bikes b ON b.id = f.bike_id
    LEFT JOIN categories c ON c.id = b.category_id
    LEFT JOIN brands br ON br.id = b.brand_id
    WHERE f.user_id = ?
    ORDER BY b.created_at DESC, b.id DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param('si', $fallbackImage, $buyerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $favoriteBikes[] = $row;
        }
    }

    $stmt->close();
}

$totalFavorites = count($favoriteBikes);

foreach ($favoriteBikes as $bike) {
    $createdAt = strtotime((string) ($bike['created_at'] ?? ''));

    if ($createdAt !== false && $createdAt >= strtotime('-7 days')) {
        $recentUpdates++;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Xe đạp yêu thích</title>
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
                    <a href="favorites.php" class="btn btn-success">Yêu thích</a>
                    <a href="../login.php" class="btn btn-outline-dark"><?= e($buyerName) ?></a>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Người mua <span>/</span> Yêu thích</div>
                <h1 class="section-title text-white mb-2">Xe đạp yêu thích</h1>
                <p class="mb-0 text-white-50">Lưu lại những mẫu xe bạn quan tâm để xem lại và so sánh dễ dàng.</p>
            </div>
        </section>

        <section class="container">
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-4">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-heart-fill"></i></span>
                        <div><small>Tổng số xe đã lưu</small><strong><?= e(number_format($totalFavorites, 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-arrow-repeat"></i></span>
                        <div><small>Xe mới cập nhật</small><strong><?= e(number_format($recentUpdates, 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-tags-fill"></i></span>
                        <div><small>Xe đã giảm giá</small><strong><?= e(number_format($discountedBikes, 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="manage-card mb-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                    <div>
                        <h2 class="section-title fs-2 mb-2">Danh sách xe yêu thích</h2>
                        <p class="section-subtitle mb-0">Lọc, so sánh và liên hệ nhanh với người bán của những mẫu xe bạn đang quan tâm.</p>
                    </div>
                </div>

                <div class="toolbar-row">
                    <input type="text" class="form-control" placeholder="Tìm theo tên xe">
                    <select class="form-select">
                        <option selected>Tất cả</option>
                        <option>Road Bike</option>
                        <option>Mountain Bike</option>
                        <option>Touring</option>
                        <option>Fixed Gear</option>
                        <option>City Bike</option>
                        <option>E-Bike</option>
                    </select>
                    <select class="form-select">
                        <option selected>Tất cả tình trạng</option>
                        <option>Mới</option>
                        <option>Đã qua sử dụng - Rất tốt</option>
                        <option>Đã qua sử dụng - Tốt</option>
                        <option>Đã qua sử dụng - Khá</option>
                    </select>
                    <select class="form-select">
                        <option selected>Mới lưu gần đây</option>
                        <option>Giá tăng dần</option>
                        <option>Giá giảm dần</option>
                        <option>Mới nhất</option>
                    </select>
                    <button class="btn btn-outline-dark">Lọc</button>
                </div>

                <div class="row g-4">
                    <?php if (!empty($favoriteBikes)): ?>
                        <?php foreach ($favoriteBikes as $bike): ?>
                            <?php
                            $bikeId = (int) ($bike['id'] ?? 0);
                            $title = fallbackValue($bike['title'] ?? '', 'Xe đạp');
                            $price = formatPriceVnd($bike['price'] ?? 0);
                            $location = fallbackValue($bike['location'] ?? '');
                            $categoryName = fallbackValue($bike['category_name'] ?? '', 'Danh mục khác');
                            $brandName = fallbackValue($bike['brand_name'] ?? '', 'Khác');
                            $conditionStatus = fallbackValue($bike['condition_status'] ?? '');
                            $image = fallbackValue($bike['image'] ?? '', $fallbackImage);
                            ?>
                            <div class="col-md-6 col-xl-3">
                                <article class="bike-card h-100">
                                    <div class="position-relative">
                                        <img src="<?= e($image) ?>" class="bike-card-image" alt="<?= e($title) ?>">
                                        <span class="badge bg-success position-absolute top-0 start-0 m-3 rounded-pill px-3 py-2"><?= e($conditionStatus) ?></span>
                                        <a href="../toggle-favorite.php?bike_id=<?= e($bikeId) ?>" class="btn btn-light position-absolute top-0 end-0 m-3 rounded-circle shadow-sm"><i class="bi bi-heart-fill text-danger"></i></a>
                                    </div>
                                    <div class="bike-card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge text-bg-light"><?= e($categoryName) ?></span>
                                            <small class="text-success fw-semibold"><?= e($brandName) ?></small>
                                        </div>
                                        <h3 class="bike-card-title"><?= e($title) ?></h3>
                                        <p class="bike-card-text"><?= e($brandName . ' ' . $categoryName . ' đang có trong danh sách xe bạn đã lưu để theo dõi và so sánh.') ?></p>
                                        <div class="price mb-2"><?= e($price) ?></div>
                                        <div class="bike-card-meta"><span><i class="bi bi-geo-alt me-1"></i><?= e($location) ?></span></div>
                                        <div class="bike-card-meta"><span><i class="bi bi-award me-1"></i><?= e($brandName) ?></span><span><i class="bi bi-tags me-1"></i><?= e($categoryName) ?></span></div>
                                        <div class="bike-card-meta mb-3"><span><i class="bi bi-hash me-1"></i><?= e('Mã xe #' . $bikeId) ?></span></div>
                                        <div class="d-grid gap-2">
                                            <a href="../bike-detail.php?id=<?= e($bikeId) ?>" class="btn btn-success">Xem chi tiết</a>
                                            <a href="../bike-detail.php?id=<?= e($bikeId) ?>#seller-information" class="btn btn-outline-dark">Liên hệ người bán</a>
                                            <a href="../toggle-favorite.php?bike_id=<?= e($bikeId) ?>" class="btn btn-outline-danger">Xóa khỏi yêu thích</a>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="helper-card text-center">
                                <div class="stats-icon mx-auto mb-3"><i class="bi bi-heart"></i></div>
                                <h3 class="section-heading">Bạn chưa có xe yêu thích?</h3>
                                <p class="mb-3">Khám phá thêm các mẫu xe đạp đang được rao bán và lưu lại những lựa chọn phù hợp để tiện so sánh sau.</p>
                                <a href="../bikes.php" class="btn btn-success">Khám phá xe đạp</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pagination-wrap">
                    <nav aria-label="Phân trang xe yêu thích">
                        <ul class="pagination justify-content-center flex-wrap">
                            <li class="page-item"><a class="page-link" href="#">Trước</a></li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#">Sau</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </section>

        <section class="container">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="helper-card h-100">
                        <div class="d-flex align-items-start gap-3">
                            <span class="stats-icon flex-shrink-0"><i class="bi bi-heart"></i></span>
                            <div>
                                <h3 class="section-heading">Chưa có xe yêu thích?</h3>
                                <p class="mb-3">Khám phá thêm các mẫu xe đạp đang được rao bán và lưu lại những lựa chọn phù hợp để tiện so sánh sau.</p>
                                <a href="../bikes.php" class="btn btn-success">Khám phá xe đạp</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="tips-card h-100">
                        <h3 class="section-heading">Mẹo theo dõi xe yêu thích</h3>
                        <ul class="tip-list">
                            <li><i class="bi bi-check-circle-fill"></i><span>Lưu xe để theo dõi giá và các lần cập nhật mới nhất.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>So sánh tình trạng và thông số để chọn đúng mẫu xe phù hợp.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Liên hệ người bán ngay khi tìm được chiếc xe ưng ý.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Theo dõi xe mới cập nhật để không bỏ lỡ lựa chọn tốt.</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="container">
            <div class="cta-band">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <h2 class="fw-bold mb-2">Tìm thêm chiếc xe phù hợp với bạn</h2>
                        <p class="mb-0">Khám phá thêm nhiều mẫu xe đạp thể thao cũ chất lượng từ cộng đồng người bán.</p>
                    </div>
                    <div class="col-lg-4 d-flex flex-column flex-sm-row gap-3 justify-content-lg-end">
                        <a href="../bikes.php" class="btn btn-warning text-dark">Xem tất cả xe đạp</a>
                        <a href="#contact" class="btn btn-outline-light">Liên hệ hỗ trợ</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer id="contact">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="fw-bold mb-3">Bike Marketplace</h4>
                    <p>Nền tảng mua bán xe đạp hiện đại giúp người mua lưu lại các mẫu xe quan tâm, so sánh nhanh và kết nối trực tiếp với người bán.</p>
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
                <small>&copy; 2026 Bike Marketplace. Trang xe yêu thích được xây dựng với HTML, CSS, Bootstrap 5 và Bootstrap Icons.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
