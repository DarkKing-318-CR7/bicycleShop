<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$currentUser = currentUser();
$isLoggedIn = isLoggedIn();
$userName = $currentUser['full_name'] ?? 'Tài khoản';
$userRole = $currentUser['role'] ?? '';
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=1400&q=80';
$fallbackSellerAvatar = 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=300&q=80';
$bikeId = (int) ($_GET['id'] ?? 0);

$bike = null;
$bikeImages = [];
$relatedBikes = [];
$inspectionReport = null;

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

function getStatusLabel(string $status): string
{
    switch ($status) {
        case 'approved':
            return 'Đang có sẵn';
        case 'sold':
            return 'Đã bán';
        case 'rejected':
            return 'Tạm ẩn';
        case 'pending':
        default:
            return 'Chờ duyệt';
    }
}

function normalizeImagePath(?string $path, string $fallback): string
{
    $path = trim((string) $path);

    if ($path === '') {
        return $fallback;
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    return $path;
}

$userHomeLink = getUserHomeLink($userRole);

if ($bikeId > 0) {
    $bikeSql = "
        SELECT
            b.id,
            b.title,
            b.price,
            b.description,
            b.location,
            b.status,
            b.created_at,
            b.condition_status,
            b.frame_size,
            b.wheel_size,
            b.color,
            b.category_id,
            c.name AS category_name,
            br.name AS brand_name,
            u.full_name AS seller_full_name,
            u.email AS seller_email,
            u.phone AS seller_phone,
            u.avatar AS seller_avatar
        FROM bikes b
        LEFT JOIN categories c ON c.id = b.category_id
        LEFT JOIN brands br ON br.id = b.brand_id
        LEFT JOIN users u ON u.id = b.seller_id
        WHERE b.id = ?
        LIMIT 1
    ";
    $bikeStmt = $conn->prepare($bikeSql);

    if ($bikeStmt) {
        $bikeStmt->bind_param('i', $bikeId);
        $bikeStmt->execute();
        $bikeResult = $bikeStmt->get_result();
        $bike = $bikeResult ? $bikeResult->fetch_assoc() : null;
        $bikeStmt->close();
    }

    if ($bike) {
        $imageSql = "
            SELECT image_url
            FROM bike_images
            WHERE bike_id = ?
            ORDER BY is_primary DESC, sort_order ASC, id ASC
        ";
        $imageStmt = $conn->prepare($imageSql);

        if ($imageStmt) {
            $imageStmt->bind_param('i', $bikeId);
            $imageStmt->execute();
            $imageResult = $imageStmt->get_result();

            if ($imageResult) {
                while ($row = $imageResult->fetch_assoc()) {
                    $bikeImages[] = normalizeImagePath($row['image_url'] ?? '', $fallbackImage);
                }
            }

            $imageStmt->close();
        }

        if (empty($bikeImages)) {
            $bikeImages[] = $fallbackImage;
        }

        $inspectionSql = "
            SELECT
                report.overall_status,
                report.summary,
                report.inspected_at,
                report.evidence_image,
                inspector.full_name AS inspector_name
            FROM inspection_reports report
            LEFT JOIN users inspector ON inspector.id = report.inspector_id
            WHERE report.bike_id = ?
            ORDER BY report.inspected_at DESC, report.id DESC
            LIMIT 1
        ";
        $inspectionStmt = $conn->prepare($inspectionSql);

        if ($inspectionStmt) {
            $inspectionStmt->bind_param('i', $bikeId);
            $inspectionStmt->execute();
            $inspectionResult = $inspectionStmt->get_result();
            $inspectionReport = $inspectionResult ? $inspectionResult->fetch_assoc() : null;
            $inspectionStmt->close();
        }

        $relatedSql = "
            SELECT
                b.id,
                b.title,
                b.price,
                b.condition_status,
                COALESCE(img.image_url, ?) AS image_url
            FROM bikes b
            LEFT JOIN bike_images img ON img.id = (
                SELECT bi.id
                FROM bike_images bi
                WHERE bi.bike_id = b.id
                ORDER BY bi.is_primary DESC, bi.sort_order ASC, bi.id ASC
                LIMIT 1
            )
            WHERE b.id <> ?
              AND b.status = 'approved'
              AND b.category_id = ?
            ORDER BY b.created_at DESC, b.id DESC
            LIMIT 4
        ";
        $relatedStmt = $conn->prepare($relatedSql);

        if ($relatedStmt) {
            $categoryId = (int) ($bike['category_id'] ?? 0);
            $relatedStmt->bind_param('sii', $fallbackImage, $bikeId, $categoryId);
            $relatedStmt->execute();
            $relatedResult = $relatedStmt->get_result();

            if ($relatedResult) {
                while ($row = $relatedResult->fetch_assoc()) {
                    $relatedBikes[] = $row;
                }
            }

            $relatedStmt->close();
        }
    }
}

$mainImage = $bikeImages[0] ?? $fallbackImage;
$sellerAvatar = normalizeImagePath($bike['seller_avatar'] ?? '', $fallbackSellerAvatar);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | <?= e($bike['title'] ?? 'Chi tiết xe đạp') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bike-marketplace.css">
</head>
<body class="bike-detail-page">
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link active" href="bikes.php">Xe đạp</a></li>
                    <li class="nav-item"><a class="nav-link" href="bikes.php#categories">Danh mục</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Liên hệ</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2">
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= e($userHomeLink) ?>" class="btn btn-outline-dark"><?= e($userName) ?></a>
                        <a href="logout.php" class="btn btn-success">Đăng xuất</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-dark">Đăng nhập</a>
                        <a href="register.php" class="btn btn-success">Đăng ký</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <?php if (!$bike): ?>
            <section class="container">
                <div class="content-card text-center">
                    <div class="page-kicker mb-2">Chi tiết xe đạp</div>
                    <h1 class="bike-title fs-2 mb-3">Không tìm thấy xe đạp</h1>
                    <p class="muted mb-4">Tin đăng bạn đang tìm có thể không tồn tại hoặc đã bị gỡ khỏi hệ thống.</p>
                    <a href="bikes.php" class="btn btn-success">Quay lại danh sách xe đạp</a>
                </div>
            </section>
        <?php else: ?>
            <section class="container">
                <div class="breadcrumb-wrap">
                    <div class="crumb-chip">
                        <i class="bi bi-house-door"></i>
                        <span>Trang chủ</span>
                        <span>/</span>
                        <span>Xe đạp</span>
                        <span>/</span>
                        <span><?= e($bike['title']) ?></span>
                    </div>
                    <div class="page-kicker">Chi tiết xe đạp</div>
                </div>

                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <div class="card-surface gallery-card">
                            <img class="main-image" src="<?= e($mainImage) ?>" alt="<?= e('Ảnh chính ' . $bike['title']) ?>">
                            <div class="thumb-grid">
                                <?php foreach (array_slice($bikeImages, 0, 4) as $index => $image): ?>
                                    <img src="<?= e($image) ?>" alt="<?= e('Ảnh thu nhỏ xe ' . ($index + 1)) ?>">
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="detail-panel">
                            <span class="condition-badge"><i class="bi bi-patch-check-fill"></i> <?= e(getConditionLabel((string) ($bike['condition_status'] ?? 'used'))) ?></span>
                            <?php if ($inspectionReport): ?>
                                <span class="condition-badge ms-2"><i class="bi bi-shield-check"></i>
                                    <?php
                                    $inspectionLabel = 'Đã kiểm định';

                                    if (($inspectionReport['overall_status'] ?? '') === 'needs_service') {
                                        $inspectionLabel = 'Cần bảo dưỡng';
                                    } elseif (($inspectionReport['overall_status'] ?? '') === 'rejected') {
                                        $inspectionLabel = 'Không đạt kiểm định';
                                    }
                                    ?>
                                    <?= e($inspectionLabel) ?>
                                </span>
                            <?php endif; ?>
                            <h1 class="bike-title"><?= e($bike['title']) ?></h1>
                            <div class="detail-price"><?= e(formatPriceVnd($bike['price'] ?? 0)) ?></div>
                            <div class="muted"><i class="bi bi-geo-alt"></i> <?= e($bike['location'] ?: 'Đang cập nhật') ?></div>

                            <div class="rating-row">
                                <div class="star-list">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-half"></i>
                                </div>
                                <strong>4.8</strong>
                                <span>18 đánh giá</span>
                            </div>

                            <p class="summary"><?= e($bike['description'] ?: 'Thông tin mô tả đang được cập nhật.') ?></p>

                            <div class="meta-grid">
                                <div class="meta-item"><small>Hãng</small><strong><?= e($bike['brand_name'] ?: 'Đang cập nhật') ?></strong></div>
                                <div class="meta-item"><small>Danh mục</small><strong><?= e($bike['category_name'] ?: 'Đang cập nhật') ?></strong></div>
                                <div class="meta-item"><small>Size khung</small><strong><?= e($bike['frame_size'] ?: 'Đang cập nhật') ?></strong></div>
                                <div class="meta-item"><small>Kích thước bánh</small><strong><?= e($bike['wheel_size'] ?: 'Đang cập nhật') ?></strong></div>
                                <div class="meta-item"><small>Màu sắc</small><strong><?= e($bike['color'] ?: 'Đang cập nhật') ?></strong></div>
                                <div class="meta-item"><small>Ngày đăng</small><strong><?= e(formatBikeDate($bike['created_at'] ?? null)) ?></strong></div>
                                <div class="meta-item"><small>Khu vực</small><strong><?= e($bike['location'] ?: 'Đang cập nhật') ?></strong></div>
                                <div class="meta-item"><small>Trạng thái</small><strong><?= e(getStatusLabel((string) ($bike['status'] ?? 'pending'))) ?></strong></div>
                            </div>

                            <div class="seller-box">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-bold"><?= e($bike['seller_full_name'] ?: 'Người bán') ?></div>
                                        <div class="seller-badge mt-2"><i class="bi bi-shield-check"></i> Người bán đã xác minh</div>
                                    </div>
                                    <div class="text-end muted small">Liên hệ qua email hoặc số điện thoại</div>
                                </div>
                            </div>

                            <?php if ($inspectionReport): ?>
                                <div class="seller-box">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-bold">Báo cáo kiểm định</div>
                                            <div class="seller-badge mt-2"><i class="bi bi-clipboard2-check"></i> <?= e($inspectionReport['inspector_name'] ?: 'Inspector hệ thống') ?></div>
                                        </div>
                                        <div class="text-end muted small">Ngày kiểm định: <?= e(formatBikeDate($inspectionReport['inspected_at'] ?? null)) ?></div>
                                    </div>
                                    <p class="muted mb-0 mt-3"><?= e($inspectionReport['summary'] ?: 'Xe đã có báo cáo kiểm định từ hệ thống.') ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="action-grid">
                                <a href="#seller-information" class="btn btn-outline-dark">Xem hồ sơ người bán</a>
                                <a href="#contact" class="btn btn-success">Liên hệ người bán</a>
                                <a href="toggle-favorite.php?bike_id=<?= e((int) $bike['id']) ?>" class="btn btn-outline-dark">Lưu vào yêu thích</a>
                                <a href="bikes.php" class="btn btn-warning text-dark">Xem thêm xe khác</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <section class="container mt-5">
                <div class="content-card">
                    <div class="section-tabs">
                        <span class="tab-pill active">Mô tả</span>
                        <span class="tab-pill">Thông số</span>
                        <span class="tab-pill">Thông tin người bán</span>
                        <span class="tab-pill">Đánh giá</span>
                    </div>

                    <div class="row g-4">
                        <div class="col-12">
                            <h2 class="section-heading">Mô tả</h2>
                            <p class="muted mb-0"><?= nl2br(e($bike['description'] ?: 'Thông tin mô tả đang được cập nhật.')) ?></p>
                        </div>

                        <div class="col-lg-6">
                            <h2 class="section-heading">Thông số</h2>
                            <div class="spec-grid">
                                <div class="spec-box"><small>Hãng</small><strong><?= e($bike['brand_name'] ?: 'Đang cập nhật') ?></strong></div>
                                <div class="spec-box"><small>Mẫu xe</small><strong><?= e($bike['title']) ?></strong></div>
                                <div class="spec-box"><small>Danh mục</small><strong><?= e($bike['category_name'] ?: 'Đang cập nhật') ?></strong></div>
                                <div class="spec-box"><small>Tình trạng</small><strong><?= e(getConditionLabel((string) ($bike['condition_status'] ?? 'used'))) ?></strong></div>
                                <div class="spec-box"><small>Giá bán</small><strong><?= e(formatPriceVnd($bike['price'] ?? 0)) ?></strong></div>
                                <div class="spec-box"><small>Size khung</small><strong><?= e($bike['frame_size'] ?: 'Đang cập nhật') ?></strong></div>
                                <div class="spec-box"><small>Kích thước bánh</small><strong><?= e($bike['wheel_size'] ?: 'Đang cập nhật') ?></strong></div>
                                <div class="spec-box"><small>Màu sắc</small><strong><?= e($bike['color'] ?: 'Đang cập nhật') ?></strong></div>
                                <div class="spec-box"><small>Khu vực</small><strong><?= e($bike['location'] ?: 'Đang cập nhật') ?></strong></div>
                                <div class="spec-box"><small>Ngày đăng</small><strong><?= e(formatBikeDate($bike['created_at'] ?? null)) ?></strong></div>
                                <div class="spec-box"><small>Trạng thái</small><strong><?= e(getStatusLabel((string) ($bike['status'] ?? 'pending'))) ?></strong></div>
                                <div class="spec-box"><small>Người bán</small><strong><?= e($bike['seller_full_name'] ?: 'Đang cập nhật') ?></strong></div>
                            </div>
                        </div>

                        <div class="col-lg-6" id="seller-information">
                            <h2 class="section-heading">Thông tin người bán</h2>
                            <div class="seller-card">
                                <div class="seller-profile">
                                    <img class="seller-avatar" src="<?= e($sellerAvatar) ?>" alt="Ảnh đại diện người bán">
                                    <div>
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                            <h3 class="h5 fw-bold mb-0"><?= e($bike['seller_full_name'] ?: 'Người bán') ?></h3>
                                            <span class="seller-badge"><i class="bi bi-shield-check"></i> Đã xác minh</span>
                                        </div>
                                        <p class="muted mb-2">Thông tin người bán được hiển thị để bạn dễ dàng liên hệ và trao đổi trước khi quyết định mua xe.</p>
                                        <div class="d-flex flex-column gap-2 muted">
                                            <span><i class="bi bi-telephone me-2"></i> <?= e($bike['seller_phone'] ?: 'Đang cập nhật') ?></span>
                                            <span><i class="bi bi-envelope me-2"></i> <?= e($bike['seller_email'] ?: 'Đang cập nhật') ?></span>
                                            <span><i class="bi bi-grid me-2"></i> Tin đăng thuộc danh mục <?= e($bike['category_name'] ?: 'Đang cập nhật') ?></span>
                                            <span><i class="bi bi-calendar-event me-2"></i> Xe được đăng ngày <?= e(formatBikeDate($bike['created_at'] ?? null)) ?></span>
                                        </div>
                                        <a href="bikes.php" class="btn btn-success mt-3">Xem tất cả tin đăng</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($inspectionReport): ?>
                            <div class="col-12">
                                <h2 class="section-heading">Kiểm định hệ thống</h2>
                                <div class="seller-card">
                                    <div class="seller-profile">
                                        <div>
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                <h3 class="h5 fw-bold mb-0">Xe đã qua kiểm định</h3>
                                                <span class="seller-badge"><i class="bi bi-shield-check"></i> <?= e($inspectionReport['inspector_name'] ?: 'Inspector hệ thống') ?></span>
                                            </div>
                                            <p class="muted mb-2"><?= e($inspectionReport['summary'] ?: 'Báo cáo kiểm định đang được cập nhật.') ?></p>
                                            <div class="d-flex flex-column gap-2 muted">
                                                <span><i class="bi bi-calendar-event me-2"></i> Hoàn tất ngày <?= e(formatBikeDate($inspectionReport['inspected_at'] ?? null)) ?></span>
                                                <span><i class="bi bi-patch-check me-2"></i> Kết luận: <?= e(($inspectionReport['overall_status'] ?? '') === 'approved' ? 'Đạt kiểm định' : (($inspectionReport['overall_status'] ?? '') === 'needs_service' ? 'Cần bảo dưỡng thêm' : 'Không đạt kiểm định')) ?></span>
                                            </div>
                                            <?php if (!empty($inspectionReport['evidence_image'])): ?>
                                                <div class="mt-3">
                                                    <img src="<?= e(normalizeImagePath($inspectionReport['evidence_image'], $fallbackImage)) ?>" alt="Ảnh kiểm định" class="img-fluid rounded-4 shadow-sm" style="max-width: 320px;">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <h2 class="section-heading">Đánh giá</h2>
                            <div class="review-card">
                                <div class="review-head">
                                    <div>
                                        <strong>Nguyễn Thanh</strong>
                                        <div class="muted small">14/03/2026</div>
                                    </div>
                                    <div class="star-list">
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                    </div>
                                </div>
                                <p class="muted mb-0">Chiếc xe đúng như mô tả. Chuyển số mượt, khung đẹp và rất thoải mái khi đi đường dài. Người bán phản hồi nhanh và rõ ràng.</p>
                            </div>
                            <div class="review-card">
                                <div class="review-head">
                                    <div>
                                        <strong>Hoàng Khoa</strong>
                                        <div class="muted small">28/02/2026</div>
                                    </div>
                                    <div class="star-list">
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-half"></i>
                                    </div>
                                </div>
                                <p class="muted mb-0">Hình học endurance rất ổn và cảm giác lái chắc chắn. Xe được chăm sóc tốt và sẵn sàng sử dụng ngay.</p>
                            </div>
                            <div class="review-card">
                                <div class="review-head">
                                    <div>
                                        <strong>Lê Quỳnh</strong>
                                        <div class="muted small">19/01/2026</div>
                                    </div>
                                    <div class="star-list">
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                    </div>
                                </div>
                                <p class="muted mb-0">Rất phù hợp cho đạp đường dài. Khung xe êm và tổng thể chiếc xe mang lại cảm giác sử dụng rất yên tâm.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="container mt-5">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                    <div>
                        <div class="page-kicker mb-2">Gợi ý thêm</div>
                        <h2 class="bike-title fs-2 m-0">Xe đạp liên quan</h2>
                    </div>
                    <p class="muted mb-0">Những mẫu xe cùng danh mục mà người mua thường tham khảo thêm.</p>
                </div>

                <div class="row g-4">
                    <?php if (!empty($relatedBikes)): ?>
                        <?php foreach ($relatedBikes as $relatedBike): ?>
                            <div class="col-md-6 col-xl-3">
                                <article class="related-card">
                                    <img src="<?= e(normalizeImagePath($relatedBike['image_url'] ?? '', $fallbackImage)) ?>" alt="<?= e($relatedBike['title']) ?>">
                                    <span class="condition-badge"><i class="bi bi-patch-check-fill"></i> <?= e(getConditionLabel((string) ($relatedBike['condition_status'] ?? 'used'))) ?></span>
                                    <h3 class="h5 fw-bold mt-3"><?= e($relatedBike['title']) ?></h3>
                                    <p class="related-desc">Thêm một lựa chọn đáng cân nhắc trong cùng nhóm xe để bạn dễ so sánh trước khi quyết định.</p>
                                    <div class="price mb-3"><?= e(formatPriceVnd($relatedBike['price'] ?? 0)) ?></div>
                                    <a href="bike-detail.php?id=<?= e((int) $relatedBike['id']) ?>" class="btn btn-success w-100">Xem chi tiết</a>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="content-card text-center">
                                <h3 class="section-heading">Chưa có xe liên quan</h3>
                                <p class="muted mb-3">Bạn có thể quay lại danh sách để khám phá thêm các mẫu xe khác.</p>
                                <a href="bikes.php" class="btn btn-success">Xem danh sách xe đạp</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <section class="container">
                <div class="cta-band">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-8">
                            <h2 class="fw-bold mb-2">Sẵn sàng đăng bán chiếc xe đạp của bạn?</h2>
                            <p class="mb-0">Tiếp cận hàng nghìn người yêu xe đạp thông qua marketplace của chúng tôi.</p>
                        </div>
                        <div class="col-lg-4 d-flex flex-column flex-sm-row gap-3 justify-content-lg-end">
                            <a href="seller/add-bike.php" class="btn btn-warning text-dark">Đăng xe của bạn</a>
                            <a href="#contact" class="btn btn-outline-light">Liên hệ hỗ trợ</a>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <footer id="contact">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="fw-bold mb-3">Bike Marketplace</h4>
                    <p>Một giao diện marketplace hiện đại giúp khám phá, so sánh và đăng bán xe đạp chất lượng một cách tự tin.</p>
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
                <small>&copy; 2026 Bike Marketplace. Trang chi tiết xe đạp được xây dựng với PHP, CSS, Bootstrap 5 và Bootstrap Icons.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
