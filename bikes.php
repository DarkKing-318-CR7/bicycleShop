<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$currentUser = currentUser();
$isLoggedIn = isLoggedIn();
$userRole = $currentUser['role'] ?? '';
$userName = $currentUser['full_name'] ?? 'Tài khoản';
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80';

$keyword = trim($_GET['keyword'] ?? '');
$categoryId = (int) ($_GET['category_id'] ?? 0);
$brandId = (int) ($_GET['brand_id'] ?? 0);
$sort = $_GET['sort'] ?? 'newest';

$allowedSorts = ['newest', 'price_asc', 'price_desc'];

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'newest';
}

$categories = [];
$brands = [];
$bikes = [];
$totalBikes = 0;
$contactSent = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_form']);

function getUserHomeLink(string $role): string
{
    if ($role === 'admin') {
        return 'admin/index.php';
    }

    if ($role === 'seller') {
        return 'seller/my-bikes.php';
    }

    if ($role === 'inspector') {
        return 'inspector/index.php';
    }

    return 'index.php';
}

function formatPriceVnd($price): string
{
    return number_format((float) $price, 0, ',', '.') . 'đ';
}

function formatBikeDate(?string $date): string
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

$userHomeLink = getUserHomeLink($userRole);

$categorySql = "
    SELECT
        c.id,
        c.name,
        COUNT(b.id) AS bike_count
    FROM categories c
    LEFT JOIN bikes b
        ON b.category_id = c.id
        AND b.status = 'approved'
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
";
$categoryResult = $conn->query($categorySql);

if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

$brandSql = "
    SELECT
        br.id,
        br.name,
        COUNT(b.id) AS bike_count
    FROM brands br
    LEFT JOIN bikes b
        ON b.brand_id = br.id
        AND b.status = 'approved'
    GROUP BY br.id, br.name
    ORDER BY br.name ASC
";
$brandResult = $conn->query($brandSql);

if ($brandResult) {
    while ($row = $brandResult->fetch_assoc()) {
        $brands[] = $row;
    }
}

$where = ["b.status = 'approved'"];
$types = '';
$params = [];

if ($keyword !== '') {
    $where[] = "(b.title LIKE ? OR c.name LIKE ? OR br.name LIKE ?)";
    $keywordLike = '%' . $keyword . '%';
    $types .= 'sss';
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
}

if ($categoryId > 0) {
    $where[] = "b.category_id = ?";
    $types .= 'i';
    $params[] = $categoryId;
}

if ($brandId > 0) {
    $where[] = "b.brand_id = ?";
    $types .= 'i';
    $params[] = $brandId;
}

$orderBy = "b.created_at DESC, b.id DESC";

if ($sort === 'price_asc') {
    $orderBy = "b.price ASC, b.id DESC";
} elseif ($sort === 'price_desc') {
    $orderBy = "b.price DESC, b.id DESC";
}

$whereSql = implode(' AND ', $where);

$countSql = "
    SELECT COUNT(*) AS total
    FROM bikes b
    LEFT JOIN categories c ON c.id = b.category_id
    LEFT JOIN brands br ON br.id = b.brand_id
    WHERE {$whereSql}
";
$countStmt = $conn->prepare($countSql);

if ($countStmt) {
    bindDynamicParams($countStmt, $types, $params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();

    if ($countResult) {
        $countRow = $countResult->fetch_assoc();
        $totalBikes = (int) ($countRow['total'] ?? 0);
    }

    $countStmt->close();
}

$bikeSql = "
    SELECT
        b.id,
        b.title,
        b.price,
        b.location,
        b.created_at,
        b.condition_status,
        c.name AS category_name,
        br.name AS brand_name,
        COALESCE(img.image_url, ?) AS image_url
    FROM bikes b
    LEFT JOIN categories c ON c.id = b.category_id
    LEFT JOIN brands br ON br.id = b.brand_id
    LEFT JOIN bike_images img ON img.id = (
        SELECT bi.id
        FROM bike_images bi
        WHERE bi.bike_id = b.id
        ORDER BY bi.is_primary DESC, bi.sort_order ASC, bi.id ASC
        LIMIT 1
    )
    WHERE {$whereSql}
    ORDER BY {$orderBy}
";
$bikeStmt = $conn->prepare($bikeSql);

if ($bikeStmt) {
    $bikeTypes = 's' . $types;
    $bikeParams = array_merge([$fallbackImage], $params);

    bindDynamicParams($bikeStmt, $bikeTypes, $bikeParams);
    $bikeStmt->execute();
    $bikeResult = $bikeStmt->get_result();

    if ($bikeResult) {
        while ($row = $bikeResult->fetch_assoc()) {
            $bikes[] = $row;
        }
    }

    $bikeStmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Xe đạp</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bike-marketplace.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container py-2">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <span class="brand-mark"><i class="bi bi-bicycle"></i></span>
                Bike Marketplace
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Chuyển đổi điều hướng">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto mb-3 mb-lg-0 gap-lg-3">
                    <li class="nav-item"><a class="nav-link" href="index.php">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link active" href="bikes.php">Xe đạp</a></li>
                    <li class="nav-item"><a class="nav-link" href="categories.php">Danh mục</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Liên hệ</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2">
                    <?php if ($isLoggedIn): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?= e($userName) ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <?php if ($userRole === 'buyer'): ?>
                                    <li><a class="dropdown-item" href="buyer/profile.php"><i class="bi bi-person me-2"></i>Hồ sơ</a></li>
                                    <li><a class="dropdown-item" href="buyer/my-orders.php"><i class="bi bi-receipt me-2"></i>Đơn mua của tôi</a></li>
                                    <li><a class="dropdown-item" href="buyer/favorites.php"><i class="bi bi-heart me-2"></i>Xe yêu thích</a></li>
                                <?php elseif ($userRole === 'seller'): ?>
                                    <li><a class="dropdown-item" href="seller/my-bikes.php"><i class="bi bi-grid me-2"></i>Tin đăng của tôi</a></li>
                                    <li><a class="dropdown-item" href="seller/orders.php"><i class="bi bi-receipt me-2"></i>Đơn hàng</a></li>
                                <?php elseif ($userRole === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/index.php"><i class="bi bi-speedometer2 me-2"></i>Trang quản trị</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-dark">Đăng nhập</a>
                        <a href="register.php" class="btn btn-success">Đăng ký</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main>
        <section class="page-hero">
            <div class="container">
                <div class="page-hero-box">
                    <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Xe đạp</div>
                    <h1>Khám phá bộ sưu tập xe đạp của chúng tôi</h1>
                    <p>Duyệt các mẫu xe đạp thể thao mới và đã qua sử dụng từ những người bán đáng tin cậy.</p>
                </div>
            </div>
        </section>
        <section class="section-space pt-0">
            <div class="container">
                <form class="filter-card mb-4" method="get" action="bikes.php">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-3">
                            <label class="form-label fw-semibold">Tìm kiếm</label>
                            <input type="text" name="keyword" class="form-control" placeholder="Tìm xe đạp, hãng, mẫu xe" value="<?= e($keyword) ?>">
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-semibold">Danh mục</label>
                            <select name="category_id" class="form-select">
                                <option value="0">Tất cả</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= e((int) $category['id']) ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                                        <?= e($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-semibold">Giá</label>
                            <select class="form-select">
                                <option>Dưới $500</option>
                                <option>$500 - $1000</option>
                                <option>$1000 - $2000</option>
                                <option>Trên $2000</option>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-semibold">Tình trạng</label>
                            <select class="form-select">
                                <option>Mới</option>
                                <option>Đã qua sử dụng - Rất tốt</option>
                                <option>Đã qua sử dụng - Tốt</option>
                                <option>Đã qua sử dụng - Khá</option>
                            </select>
                        </div>
                        <div class="col-6 col-lg-2">
                            <label class="form-label fw-semibold">Sắp xếp</label>
                            <select name="sort" class="form-select">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Giá tăng dần</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Giá giảm dần</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-1 d-grid">
                            <button class="btn btn-success" type="submit"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                </form>
                <div class="row g-4">
                    <aside class="col-lg-4 col-xl-3" id="categories">
                        <div class="sidebar-card">
                            <h3 class="sidebar-title">Danh mục</h3>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                    <a href="bikes.php?category_id=<?= e((int) $category['id']) ?>" class="list-link">
                                        <span><?= e($category['name']) ?></span>
                                        <span class="count-badge"><?= e((int) $category['bike_count']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-muted">Chưa có danh mục</div>
                            <?php endif; ?>
                        </div>
                        <div class="sidebar-card">
                            <h3 class="sidebar-title">Hãng</h3>
                            <?php if (!empty($brands)): ?>
                                <?php foreach ($brands as $brand): ?>
                                    <a href="bikes.php?brand_id=<?= e((int) $brand['id']) ?>" class="list-link">
                                        <span><?= e($brand['name']) ?></span>
                                        <span class="count-badge"><?= e((int) $brand['bike_count']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-muted">Chưa có thương hiệu</div>
                            <?php endif; ?>
                        </div>
                        <div class="sidebar-card">
                            <h3 class="sidebar-title">Tình trạng</h3>
                            <div class="d-flex flex-column gap-2">
                                <label class="d-flex align-items-center gap-2"><input type="checkbox" class="form-check-input mt-0"> Mới</label>
                                <label class="d-flex align-items-center gap-2"><input type="checkbox" class="form-check-input mt-0"> Đã qua sử dụng - Rất tốt</label>
                                <label class="d-flex align-items-center gap-2"><input type="checkbox" class="form-check-input mt-0"> Đã qua sử dụng - Tốt</label>
                                <label class="d-flex align-items-center gap-2"><input type="checkbox" class="form-check-input mt-0"> Đã qua sử dụng - Khá</label>
                            </div>
                        </div>
                        <div class="sidebar-card">
                            <h3 class="sidebar-title">Khoảng giá</h3>
                            <div class="static-price-box">
                                <div class="d-flex justify-content-between text-muted small mb-2"><span>$100</span><span>$4,500</span></div>
                                <div class="progress" style="height: 8px; border-radius: 999px; background: rgba(47,125,50,0.12);">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 62%"></div>
                                </div>
                                <div class="mt-3 fw-semibold">Gợi ý khoảng giá: $500 - $2,000</div>
                            </div>
                        </div>
                        <div class="sidebar-card promo-card">
                            <h3 class="sidebar-title text-white">Bán xe đạp của bạn</h3>
                            <p class="mb-3 text-white-50">Đăng tin xe chỉ trong vài phút và kết nối với những người đang tìm mua xe đạp thể thao chất lượng.</p>
                            <a href="seller/add-bike.php" class="btn btn-warning text-dark">Đăng tin</a>
                        </div>
                    </aside>
                    <div class="col-lg-8 col-xl-9">
                        <div class="toolbar-inline">
                            <div>
                                <h2 class="h4 fw-bold mb-1">Danh sách xe đạp</h2>
                                <p class="text-muted mb-0">Hiển thị <?= e(count($bikes)) ?> trong <?= e($totalBikes) ?> xe đạp</p>
                            </div>
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
                                <div class="view-toggle">
                                    <button class="active" aria-label="Grid view" type="button"><i class="bi bi-grid-3x3-gap-fill"></i></button>
                                    <button aria-label="List view" type="button"><i class="bi bi-list-ul"></i></button>
                                </div>
                                <form method="get" action="bikes.php">
                                    <input type="hidden" name="keyword" value="<?= e($keyword) ?>">
                                    <input type="hidden" name="category_id" value="<?= e($categoryId) ?>">
                                    <input type="hidden" name="brand_id" value="<?= e($brandId) ?>">
                                    <select class="form-select" name="sort" style="min-width: 210px;" onchange="this.form.submit()">
                                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Sắp xếp theo: Mới nhất</option>
                                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Sắp xếp theo: Giá tăng dần</option>
                                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Sắp xếp theo: Giá giảm dần</option>
                                    </select>
                                </form>
                            </div>
                        </div>
                        <div class="row g-4">
                            <?php if (!empty($bikes)): ?>
                                <?php foreach ($bikes as $bike): ?>
                                    <div class="col-md-6 col-xl-4">
                                        <article class="bike-card">
                                            <img src="<?= e($bike['image_url'] ?: $fallbackImage) ?>" alt="<?= e($bike['title']) ?>">
                                            <div class="bike-content">
                                                <div class="bike-top">
                                                    <span class="condition-badge"><?= e(getConditionLabel((string) ($bike['condition_status'] ?? 'used'))) ?></span>
                                                    <button class="favorite-btn" type="button"><i class="bi bi-heart"></i></button>
                                                </div>
                                                <h3 class="bike-title"><?= e($bike['title']) ?></h3>
                                                <p class="bike-desc"><?= e(($bike['brand_name'] ?: 'Thương hiệu đang cập nhật') . ' • ' . ($bike['category_name'] ?: 'Danh mục khác') . ' • Đăng ' . formatBikeDate($bike['created_at'] ?? null)) ?></p>
                                                <div class="price"><?= e(formatPriceVnd($bike['price'] ?? 0)) ?></div>
                                                <div class="location"><i class="bi bi-geo-alt"></i> <?= e($bike['location'] ?: 'Đang cập nhật') ?></div>
                                                <div class="meta-row">
                                                    <span class="meta-pill"><?= e($bike['brand_name'] ?: 'Khác') ?></span>
                                                    <span class="meta-pill"><?= e($bike['category_name'] ?: 'Danh mục khác') ?></span>
                                                </div>
                                                <a href="bike-detail.php?id=<?= e((int) $bike['id']) ?>" class="btn btn-success w-100">Xem chi tiết</a>
                                            </div>
                                        </article>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="sidebar-card text-center">
                                        <h3 class="sidebar-title">Không tìm thấy xe đạp phù hợp</h3>
                                        <p class="text-muted mb-3">Hãy thử thay đổi từ khóa tìm kiếm hoặc bộ lọc để xem thêm kết quả.</p>
                                        <a href="bikes.php" class="btn btn-success">Xóa bộ lọc</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <nav class="mt-4" aria-label="Bike pagination">
                            <ul class="pagination justify-content-center justify-content-lg-start flex-wrap">
                                <li class="page-item"><a class="page-link" href="#">Trước</a></li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item"><a class="page-link" href="#">Sau</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <section class="cta-section mt-5">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-8">
                            <h2 class="fw-bold mb-2">Bạn muốn bán chiếc xe đạp đã qua sử dụng của mình?</h2>
                            <p class="mb-0">Tham gia marketplace của chúng tôi và tiếp cận nhiều người đạp xe hơn ngay hôm nay.</p>
                        </div>
                        <div class="col-lg-4 d-flex flex-column flex-sm-row gap-3 justify-content-lg-end">
                            <a href="seller/add-bike.php" class="btn btn-warning text-dark">Đăng xe của bạn</a>
                            <a href="#contact" class="btn btn-outline-light rounded-pill">Liên hệ</a>
                        </div>
                    </div>
                </section>
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
                            <h2 class="section-title text-white mb-3">Cần hỗ trợ mua hoặc bán xe?</h2>
                            <p class="contact-copy">Gửi thông tin cho Bike Marketplace, đội ngũ hỗ trợ sẽ phản hồi để tư vấn tin đăng, giao dịch hoặc giải đáp thắc mắc về xe bạn đang quan tâm.</p>
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
                                        <p>hello@bikemarketplace.com</p>
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
                        <form class="contact-form h-100" action="bikes.php#contact" method="post">
                            <input type="hidden" name="contact_form" value="1">
                            <?php if ($contactSent): ?>
                                <div class="alert alert-success" role="alert">
                                    Cảm ơn bạn đã liên hệ. Bike Marketplace sẽ phản hồi trong thời gian sớm nhất.
                                </div>
                            <?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_name">Họ và tên</label>
                                    <input type="text" class="form-control" id="contact_name" name="contact_name" placeholder="Nhập họ và tên">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_phone">Số điện thoại</label>
                                    <input type="tel" class="form-control" id="contact_phone" name="contact_phone" placeholder="Nhập số điện thoại">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_email">Email</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" placeholder="email@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_topic">Chủ đề</label>
                                    <select class="form-select" id="contact_topic" name="contact_topic">
                                        <option>Tư vấn mua xe</option>
                                        <option>Hỗ trợ đăng bán</option>
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
                    <p>Một giao diện danh sách xe đạp hiện đại dành cho marketplace xe đạp thể thao cũ và sẵn sàng chuyển sang PHP sau này.</p>
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
                    <p class="mb-0">Mở cửa mỗi ngày từ 8:00 AM đến 8:00 PM để hỗ trợ người mua và onboarding người bán.</p>
                </div>
            </div>
            <div class="border-top border-secondary-subtle mt-4 pt-4 text-center text-white-50">
                <small>&copy; 2026 Bike Marketplace. Trang danh sách xe đạp được xây dựng với HTML, CSS, Bootstrap 5 và Bootstrap Icons.</small>
            </div>
        </div>
    </footer>
    <?php require __DIR__ . '/includes/chat-widget.php'; ?>
    <script src="<?= e(baseUrl('js/chat-widget.js')) ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
