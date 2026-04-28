<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$currentUser = currentUser();
$isLoggedIn = isLoggedIn();
$userName = $currentUser['full_name'] ?? 'Tài khoản';
$userEmail = $currentUser['email'] ?? '';
$userRole = $currentUser['role'] ?? '';
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80';
$contactSent = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_form']);

$stats = [
    'total_bikes' => 0,
    'total_sellers' => 0,
    'total_buyers' => 0,
];

$categories = [];
$featuredBikes = [];
$newBikes = [];

$categoryVisuals = [
    [
        'image' => 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80',
        'chip_icon' => 'bi-speedometer2',
        'chip_text' => 'Hiệu suất',
    ],
    [
        'image' => 'https://images.unsplash.com/photo-1517649763962-0c623066013b?auto=format&fit=crop&w=900&q=80',
        'chip_icon' => 'bi-tree-fill',
        'chip_text' => 'Phiêu lưu',
    ],
    [
        'image' => 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=900&q=80',
        'chip_icon' => 'bi-buildings',
        'chip_text' => 'Đô thị',
    ],
    [
        'image' => 'https://images.unsplash.com/photo-1571068316344-75bc76f77890?auto=format&fit=crop&w=900&q=80',
        'chip_icon' => 'bi-battery-charging',
        'chip_text' => 'Điện',
    ],
    [
        'image' => 'https://images.unsplash.com/photo-1507035895480-2b3156c31fc8?auto=format&fit=crop&w=900&q=80',
        'chip_icon' => 'bi-stars',
        'chip_text' => 'Gia đình',
    ],
];

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

function fetchCount(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return isset($row['total']) ? (int) $row['total'] : 0;
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

function getBikeMetaText(array $bike): string
{
    $parts = [];

    if (!empty($bike['category_name'])) {
        $parts[] = $bike['category_name'];
    }

    if (!empty($bike['brand_name'])) {
        $parts[] = $bike['brand_name'];
    }

    if (!empty($bike['location'])) {
        $parts[] = $bike['location'];
    }

    if (!empty($bike['created_at'])) {
        $parts[] = 'Đăng ' . formatBikeDate($bike['created_at']);
    }

    return implode(' • ', $parts);
}

function getCategoryDescription(array $category): string
{
    $description = trim((string) ($category['description'] ?? ''));

    if ($description !== '') {
        return $description;
    }

    return 'Khám phá các mẫu xe phù hợp với nhu cầu sử dụng và phong cách đạp xe của bạn.';
}

$stats['total_bikes'] = fetchCount($conn, "SELECT COUNT(*) AS total FROM bikes WHERE status = 'approved'");
$stats['total_sellers'] = fetchCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'seller' AND status = 'active'");
$stats['total_buyers'] = fetchCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'buyer' AND status = 'active'");

$categorySql = "SELECT id, name, slug, description FROM categories ORDER BY created_at DESC, id DESC LIMIT 5";
$categoryResult = $conn->query($categorySql);

if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

$bikeSql = "
    SELECT
        b.id,
        b.title,
        b.price,
        b.location,
        b.status,
        b.created_at,
        b.condition_status,
        COALESCE(c.name, 'Danh mục khác') AS category_name,
        COALESCE(br.name, '') AS brand_name,
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
    WHERE b.status = 'approved'
    ORDER BY b.created_at DESC, b.id DESC
    LIMIT 12
";

$bikeStmt = $conn->prepare($bikeSql);

if ($bikeStmt) {
    $bikeStmt->bind_param('s', $fallbackImage);
    $bikeStmt->execute();
    $bikeResult = $bikeStmt->get_result();

    if ($bikeResult) {
        while ($row = $bikeResult->fetch_assoc()) {
            $featuredBikes[] = $row;
        }
    }

    $bikeStmt->close();
}

if (count($featuredBikes) < 12) {
    $existingIds = array_map(static function ($bike): int {
        return (int) $bike['id'];
    }, $featuredBikes);

    $fallbackSql = "
        SELECT
            b.id,
            b.title,
            b.price,
            b.location,
            b.status,
            b.created_at,
            b.condition_status,
            COALESCE(c.name, 'Danh mục khác') AS category_name,
            COALESCE(br.name, '') AS brand_name,
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
        ORDER BY b.created_at DESC, b.id DESC
        LIMIT 12
    ";

    $fallbackStmt = $conn->prepare($fallbackSql);

    if ($fallbackStmt) {
        $fallbackStmt->bind_param('s', $fallbackImage);
        $fallbackStmt->execute();
        $fallbackResult = $fallbackStmt->get_result();

        if ($fallbackResult) {
            while ($row = $fallbackResult->fetch_assoc()) {
                $bikeId = (int) ($row['id'] ?? 0);

                if (!in_array($bikeId, $existingIds, true)) {
                    $featuredBikes[] = $row;
                    $existingIds[] = $bikeId;
                }

                if (count($featuredBikes) >= 12) {
                    break;
                }
            }
        }

        $fallbackStmt->close();
    }
}

$newBikes = array_slice($featuredBikes, 6, 6);
$featuredBikes = array_slice($featuredBikes, 0, 6);

if (empty($newBikes)) {
    $newBikes = $featuredBikes;
}

$userHomeLink = getUserHomeLink($userRole);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace</title>
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
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto mb-3 mb-lg-0 gap-lg-3">
                    <li class="nav-item"><a class="nav-link active" href="#home">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="bikes.php">Xe đạp</a></li>
                    <li class="nav-item"><a class="nav-link" href="#categories">Danh mục</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Liên hệ</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2">
                    <?php if ($isLoggedIn): ?>
                        <a href="profile.php" class="btn btn-outline-dark"><?= e($userName) ?></a>
                        <a href="logout.php" class="btn btn-success">Đăng xuất</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-dark">Đăng nhập</a>
                        <a href="register.php" class="btn btn-success">Đăng ký</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main>
        <section class="hero" id="home">
            <div class="container">
                <div class="hero-wrap d-flex align-items-center">
                    <div class="hero-copy">
                        <span class="eyebrow"><i class="bi bi-lightning-charge-fill"></i> Giao dịch xe đạp uy tín cho mọi người đạp xe</span>
                        <h1>Tìm chiếc xe phù hợp cho đường trường, đường mòn hoặc đi lại trong thành phố.</h1>
                        <p>Khám phá các tin đăng chất lượng, so sánh tình trạng xe và tìm xe đạp từ những người bán đáng tin cậy trong một marketplace hiện đại.</p>
                        <div class="d-flex flex-column flex-sm-row gap-3">
                            <a href="#featured" class="btn btn-warning text-dark">Khám phá xe đạp</a>
                            <a href="#categories" class="btn btn-outline-light rounded-pill px-4 py-3 fw-bold">Xem danh mục</a>
                        </div>
                        <div class="hero-stats">
                            <div class="hero-stat"><strong><?= e(number_format($stats['total_bikes'], 0, ',', '.')) ?>+</strong><span>Tin đăng xe đạp đang hoạt động</span></div>
                            <div class="hero-stat"><strong><?= e(number_format($stats['total_sellers'], 0, ',', '.')) ?>+</strong><span>Người bán đã xác minh</span></div>
                            <div class="hero-stat"><strong><?= e(number_format($stats['total_buyers'], 0, ',', '.')) ?>+</strong><span>Người mua đang tham gia marketplace</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="section-space" id="categories">
            <div class="container">
                <div class="mb-5">
                    <div class="section-label">Danh mục</div>
                    <h2 class="section-title">Mua sắm theo danh mục xe đạp</h2>
                    <p class="section-subtitle">Bố cục danh mục gọn gàng lấy cảm hứng từ các website thương mại điện tử hiện đại, giúp bạn duyệt nhanh những loại xe đạp phổ biến nhất.</p>
                </div>
                <div class="row g-4">
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $index => $category): ?>
                            <?php $visual = $categoryVisuals[$index % count($categoryVisuals)]; ?>
                            <div class="col-md-6 col-lg-4">
                                <article class="category-card">
                                    <img src="<?= e($visual['image']) ?>" alt="<?= e('Danh mục ' . $category['name']) ?>">
                                    <div class="content">
                                        <span class="category-chip"><i class="bi <?= e($visual['chip_icon']) ?>"></i> <?= e($visual['chip_text']) ?></span>
                                        <h5><?= e($category['name']) ?></h5>
                                        <p><?= e(getCategoryDescription($category)) ?></p>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <article class="feature-card">
                                <h5>Danh mục đang được cập nhật</h5>
                                <p>Chúng tôi đang hoàn thiện danh sách danh mục để giúp bạn tìm xe đạp phù hợp nhanh hơn.</p>
                            </article>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <section class="section-space pt-0" id="featured">
            <div class="container">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end mb-5 gap-3">
                    <div>
                        <div class="section-label">Nổi bật</div>
                        <h2 class="section-title">Xe đạp nổi bật</h2>
                        <p class="section-subtitle">Những tin đăng mới và chất lượng được chọn hiển thị nổi bật trên trang chủ.</p>
                    </div>
                    <a href="#new-bikes" class="btn btn-outline-dark">Xem xe mới về</a>
                </div>
                <div class="row g-4">
                    <?php if (!empty($featuredBikes)): ?>
                        <?php foreach ($featuredBikes as $bike): ?>
                            <div class="col-md-6 col-xl-4">
                                <article class="bike-card">
                                    <img src="<?= e($bike['image_url'] ?? $fallbackImage) ?>" alt="<?= e($bike['title'] ?? 'Xe đạp thể thao') ?>">
                                    <div class="content">
                                        <span class="bike-condition"><i class="bi bi-patch-check-fill"></i> <?= e(getConditionLabel((string) ($bike['condition_status'] ?? 'used'))) ?></span>
                                        <h5><?= e($bike['title'] ?? 'Xe đạp thể thao') ?></h5>
                                        <p class="bike-meta mb-3"><?= e(getBikeMetaText($bike)) ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="price"><?= e(formatPriceVnd($bike['price'] ?? 0)) ?></div>
                                            <a href="bike-detail.php?id=<?= e((int) ($bike['id'] ?? 0)) ?>" class="btn btn-sm btn-success px-3">Xem</a>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <article class="feature-card">
                                <h5>Chưa có xe nổi bật</h5>
                                <p>Các tin đăng nổi bật sẽ xuất hiện tại đây khi hệ thống có dữ liệu xe đạp phù hợp.</p>
                            </article>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <section class="section-space pt-0" id="new-bikes">
            <div class="container">
                <div class="mb-5">
                    <div class="section-label">Xe mới về</div>
                    <h2 class="section-title">Xe đạp mới</h2>
                    <p class="section-subtitle">Danh sách các mẫu xe mới cập nhật giúp bạn nắm bắt nhanh những lựa chọn vừa xuất hiện trên hệ thống.</p>
                </div>
                <div class="row g-4">
                    <?php if (!empty($newBikes)): ?>
                        <?php foreach ($newBikes as $bike): ?>
                            <div class="col-md-6 col-xl-4">
                                <article class="bike-card">
                                    <img src="<?= e($bike['image_url'] ?? $fallbackImage) ?>" alt="<?= e($bike['title'] ?? 'Xe đạp thể thao') ?>">
                                    <div class="content">
                                        <span class="bike-condition"><i class="bi bi-lightning-fill"></i> <?= e(getConditionLabel((string) ($bike['condition_status'] ?? 'used'))) ?></span>
                                        <h5><?= e($bike['title'] ?? 'Xe đạp thể thao') ?></h5>
                                        <p class="bike-meta mb-3"><?= e(getBikeMetaText($bike)) ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="price"><?= e(formatPriceVnd($bike['price'] ?? 0)) ?></div>
                                            <a href="bike-detail.php?id=<?= e((int) ($bike['id'] ?? 0)) ?>" class="btn btn-sm btn-success px-3">Xem</a>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <article class="feature-card">
                                <h5>Danh sách xe mới đang cập nhật</h5>
                                <p>Chúng tôi sẽ hiển thị các mẫu xe vừa được đăng ngay khi có dữ liệu mới từ người bán.</p>
                            </article>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <section class="section-space">
            <div class="container">
                <div class="cta-band mb-5 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <div class="section-label text-warning mb-2">Cam kết từ marketplace</div>
                        <h3 class="mb-2 fw-bold">Tin đăng minh bạch, trải nghiệm hiện đại và lựa chọn phong phú cho cộng đồng yêu xe đạp.</h3>
                        <p class="mb-0 text-white-50">Mỗi khu vực trên trang chủ được tổ chức để giúp bạn tìm nhanh danh mục phù hợp, xem xe nổi bật và khám phá các tin đăng mới nhất.</p>
                    </div>
                    <a href="#contact" class="btn btn-warning text-dark">Liên hệ với chúng tôi</a>
                </div>
                <div class="mb-5">
                    <div class="section-label">Vì sao chọn chúng tôi</div>
                    <h2 class="section-title">Xây dựng cho trải nghiệm mua xe đạp đáng tin cậy</h2>
                    <p class="section-subtitle">Khu vực này thể hiện các tín hiệu tin cậy và điểm mạnh dịch vụ thường có trên một homepage marketplace hiệu quả.</p>
                </div>
                <div class="row g-4">
                    <div class="col-md-6 col-xl-3"><article class="feature-card"><div class="feature-icon"><i class="bi bi-shield-check"></i></div><h5>Tin đăng đã xác minh</h5><p>Mỗi sản phẩm đều có giá và tình trạng rõ ràng để người mua đánh giá dễ hơn.</p></article></div>
                    <div class="col-md-6 col-xl-3"><article class="feature-card"><div class="feature-icon"><i class="bi bi-funnel"></i></div><h5>Duyệt nhanh, dễ dàng</h5><p>Danh mục, xe nổi bật và xe mới về được sắp xếp logic để tìm sản phẩm nhanh hơn.</p></article></div>
                    <div class="col-md-6 col-xl-3"><article class="feature-card"><div class="feature-icon"><i class="bi bi-chat-dots"></i></div><h5>Hỗ trợ trực tiếp</h5><p>Khu vực liên hệ và mạng xã hội sẵn sàng phục vụ trao đổi với người bán và hỗ trợ khách hàng.</p></article></div>
                    <div class="col-md-6 col-xl-3"><article class="feature-card"><div class="feature-icon"><i class="bi bi-phone"></i></div><h5>Thiết kế responsive</h5><p>Bố cục hiển thị tốt trên điện thoại, máy tính bảng và desktop nhờ Bootstrap grid.</p></article></div>
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
                            <h2 class="section-title text-white mb-3">Cần hỗ trợ từ Bike Marketplace?</h2>
                            <p class="contact-copy">Người mua và người bán đều có thể gửi yêu cầu tư vấn xe, hỗ trợ đăng tin, kiểm tra giao dịch hoặc góp ý để đội ngũ hỗ trợ phản hồi nhanh hơn.</p>
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
                        <form class="contact-form h-100" action="index.php#contact" method="post">
                            <input type="hidden" name="contact_form" value="1">
                            <?php if ($contactSent): ?>
                                <div class="alert alert-success" role="alert">
                                    Cảm ơn bạn đã liên hệ. Bike Marketplace sẽ phản hồi trong thời gian sớm nhất.
                                </div>
                            <?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_name">Họ và tên</label>
                                    <input type="text" class="form-control" id="contact_name" name="contact_name" value="<?= e($isLoggedIn ? $userName : '') ?>" placeholder="Nhập họ và tên">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_phone">Số điện thoại</label>
                                    <input type="tel" class="form-control" id="contact_phone" name="contact_phone" placeholder="Nhập số điện thoại">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_email">Email</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= e($userEmail) ?>" placeholder="email@example.com">
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
                    <p>Một giao diện trang chủ hiện đại cho marketplace xe đạp với cách trình bày sản phẩm rõ ràng và dễ theo dõi.</p>
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
                    <p class="mb-0">Mở cửa mỗi ngày từ 8:00 AM đến 8:00 PM để hỗ trợ người mua và người bán.</p>
                </div>
            </div>
            <div class="border-top border-secondary-subtle mt-4 pt-4 text-center text-white-50">
                <small>&copy; 2026 Bike Marketplace. Trang chủ được xây dựng bằng PHP, CSS, Bootstrap và JavaScript tối giản.</small>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
