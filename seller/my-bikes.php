<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (!hasRole('seller')) {
    redirect('../bikes.php#contact');
}

$currentUser = currentUser();
$sellerId = (int) ($currentUser['id'] ?? 0);
$sellerName = $currentUser['full_name'] ?? 'Tài khoản';
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80';

$bikes = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'sold' => 0,
];
$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$categoryId = (int) ($_GET['category_id'] ?? 0);
$sort = $_GET['sort'] ?? 'newest';

$allowedStatuses = ['all', 'pending', 'approved', 'rejected', 'sold'];
$allowedSorts = ['newest', 'oldest', 'price_asc', 'price_desc'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'newest';
}

$categories = [];

function formatBikePrice($price): string
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

function getBikeStatusMeta(string $status): array
{
    switch ($status) {
        case 'approved':
        case 'active':
        case 'published':
            return ['class' => 'status-approved', 'label' => 'Đã duyệt'];

        case 'rejected':
            return ['class' => 'status-rejected', 'label' => 'Từ chối'];

        case 'sold':
        case 'completed':
            return ['class' => 'status-sold', 'label' => 'Đã bán'];

        case 'pending':
        default:
            return ['class' => 'status-pending', 'label' => 'Chờ duyệt'];
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

function getLocalBikeImagePath(string $imageUrl): ?string
{
    $imageUrl = trim($imageUrl);

    if ($imageUrl === '' || preg_match('/^https?:\/\//i', $imageUrl)) {
        return null;
    }

    $relativePath = str_replace('\\', '/', ltrim($imageUrl, '/'));

    if (strpos($relativePath, '../') === 0) {
        $relativePath = substr($relativePath, 3);
    }

    if (strpos($relativePath, 'uploads/bikes/') !== 0) {
        return null;
    }

    $projectRoot = realpath(__DIR__ . '/..');
    $uploadRoot = realpath(__DIR__ . '/../uploads/bikes');

    if ($projectRoot === false || $uploadRoot === false) {
        return null;
    }

    $fullPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $realFullPath = realpath($fullPath);

    if ($realFullPath === false || strpos($realFullPath, $uploadRoot) !== 0) {
        return null;
    }

    return $realFullPath;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_bike') {
    $deleteBikeId = (int) ($_POST['bike_id'] ?? 0);

    if ($deleteBikeId <= 0) {
        $_SESSION['error_message'] = 'Tin đăng không hợp lệ.';
        redirect('my-bikes.php');
    }

    $imagePaths = [];
    $imageStmt = $conn->prepare("
        SELECT bi.image_url
        FROM bike_images bi
        INNER JOIN bikes b ON b.id = bi.bike_id
        WHERE bi.bike_id = ? AND b.seller_id = ?
    ");

    if ($imageStmt) {
        $imageStmt->bind_param('ii', $deleteBikeId, $sellerId);
        $imageStmt->execute();
        $imageResult = $imageStmt->get_result();

        if ($imageResult) {
            while ($row = $imageResult->fetch_assoc()) {
                $localPath = getLocalBikeImagePath((string) ($row['image_url'] ?? ''));

                if ($localPath !== null) {
                    $imagePaths[] = $localPath;
                }
            }
        }

        $imageStmt->close();
    }

    $conn->begin_transaction();
    $deleted = false;

    $deleteFavoritesStmt = $conn->prepare("DELETE FROM favorites WHERE bike_id = ?");
    if ($deleteFavoritesStmt) {
        $deleteFavoritesStmt->bind_param('i', $deleteBikeId);
        $deleteFavoritesStmt->execute();
        $deleteFavoritesStmt->close();
    }

    $deleteImagesStmt = $conn->prepare("DELETE FROM bike_images WHERE bike_id = ?");
    if ($deleteImagesStmt) {
        $deleteImagesStmt->bind_param('i', $deleteBikeId);
        $deleteImagesStmt->execute();
        $deleteImagesStmt->close();
    }

    $deleteBikeStmt = $conn->prepare("DELETE FROM bikes WHERE id = ? AND seller_id = ?");
    if ($deleteBikeStmt) {
        $deleteBikeStmt->bind_param('ii', $deleteBikeId, $sellerId);
        $deleteBikeStmt->execute();
        $deleted = $deleteBikeStmt->affected_rows > 0;
        $deleteBikeStmt->close();
    }

    if ($deleted) {
        $conn->commit();

        foreach (array_unique($imagePaths) as $imagePath) {
            if (is_file($imagePath)) {
                @unlink($imagePath);
            }
        }

        $_SESSION['success_message'] = 'Đã xóa tin đăng thành công.';
    } else {
        $conn->rollback();
        $_SESSION['error_message'] = 'Không thể xóa tin đăng. Tin có thể không tồn tại hoặc không thuộc tài khoản của bạn.';
    }

    redirect('my-bikes.php');
}

$categorySql = "
    SELECT
        c.id,
        c.name,
        COUNT(b.id) AS bike_count
    FROM categories c
    LEFT JOIN bikes b
        ON b.category_id = c.id
        AND b.seller_id = ?
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
";
$categoryStmt = $conn->prepare($categorySql);

if ($categoryStmt) {
    $categoryStmt->bind_param('i', $sellerId);
    $categoryStmt->execute();
    $categoryResult = $categoryStmt->get_result();

    if ($categoryResult) {
        while ($row = $categoryResult->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    $categoryStmt->close();
}

$statsStmt = $conn->prepare("SELECT status, COUNT(*) AS total FROM bikes WHERE seller_id = ? GROUP BY status");

if ($statsStmt) {
    $statsStmt->bind_param('i', $sellerId);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();

    if ($statsResult) {
        while ($row = $statsResult->fetch_assoc()) {
            $count = (int) ($row['total'] ?? 0);
            $status = strtolower((string) ($row['status'] ?? 'pending'));

            $stats['total'] += $count;

            if ($status === 'pending') {
                $stats['pending'] += $count;
            } elseif (in_array($status, ['approved', 'active', 'published'], true)) {
                $stats['approved'] += $count;
            } elseif (in_array($status, ['sold', 'completed'], true)) {
                $stats['sold'] += $count;
            }
        }
    }

    $statsStmt->close();
}

$where = ["b.seller_id = ?"];
$types = 'i';
$params = [$sellerId];

if ($keyword !== '') {
    $where[] = "(b.title LIKE ? OR b.location LIKE ? OR c.name LIKE ? OR br.name LIKE ?)";
    $keywordLike = '%' . $keyword . '%';
    $types .= 'ssss';
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
}

if ($statusFilter === 'approved') {
    $where[] = "b.status IN ('approved', 'active', 'published')";
} elseif ($statusFilter === 'sold') {
    $where[] = "b.status IN ('sold', 'completed')";
} elseif ($statusFilter !== 'all') {
    $where[] = "b.status = ?";
    $types .= 's';
    $params[] = $statusFilter;
}

if ($categoryId > 0) {
    $where[] = "b.category_id = ?";
    $types .= 'i';
    $params[] = $categoryId;
}

$orderBy = "b.created_at DESC, b.id DESC";

if ($sort === 'oldest') {
    $orderBy = "b.created_at ASC, b.id ASC";
} elseif ($sort === 'price_asc') {
    $orderBy = "b.price ASC, b.id DESC";
} elseif ($sort === 'price_desc') {
    $orderBy = "b.price DESC, b.id DESC";
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT
        b.id,
        b.title,
        b.price,
        b.status,
        b.created_at,
        b.location,
        {$viewCountSelect} AS view_count,
        COALESCE(c.name, 'Danh mục khác') AS category_name,
        COALESCE(br.name, '') AS brand_name,
        COALESCE(img.image_url, ?) AS image_url,
        COALESCE(fav.favorite_count, 0) AS favorite_count
    FROM bikes b
    LEFT JOIN categories c ON c.id = b.category_id
    LEFT JOIN brands br ON br.id = b.brand_id
    LEFT JOIN bike_images img ON img.id = (
        SELECT bi.id
        FROM bike_images bi
        WHERE bi.bike_id = b.id
        ORDER BY bi.id ASC
        LIMIT 1
    )
    LEFT JOIN (
        SELECT bike_id, COUNT(*) AS favorite_count
        FROM favorites
        GROUP BY bike_id
    ) fav ON fav.bike_id = b.id
    WHERE {$whereSql}
    ORDER BY {$orderBy}
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $bikeTypes = 's' . $types;
    $bikeParams = array_merge([$fallbackImage], $params);

    bindDynamicParams($stmt, $bikeTypes, $bikeParams);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bikes[] = $row;
        }
    }

    $stmt->close();
}

$filteredCount = count($bikes);
$hasActiveFilters = $keyword !== '' || $statusFilter !== 'all' || $categoryId > 0 || $sort !== 'newest';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Tin đăng của tôi</title>
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
                    <a href="../profile.php" class="btn btn-outline-dark"><?= e($sellerName) ?></a>
                    <a href="../logout.php" class="btn btn-success">Đăng xuất</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Người bán <span>/</span> Tin đã đăng</div>
                <h1 class="section-title text-white mb-2">Tin đăng của tôi</h1>
                <p class="mb-0 text-white-50">Quản lý các xe đạp bạn đã đăng bán trên hệ thống.</p>
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
                <div class="col-md-6 col-xl-3">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-grid"></i></span>
                        <div><small>Tổng tin đăng</small><strong><?= e(number_format($stats['total'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-hourglass-split"></i></span>
                        <div><small>Đang chờ duyệt</small><strong><?= e(number_format($stats['pending'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-patch-check"></i></span>
                        <div><small>Đã được duyệt</small><strong><?= e(number_format($stats['approved'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stats-card">
                        <span class="stats-icon"><i class="bi bi-bag-check"></i></span>
                        <div><small>Đã bán</small><strong><?= e(number_format($stats['sold'], 0, ',', '.')) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <div class="manage-card">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                            <div>
                                <h2 class="section-title fs-2 mb-2">Quản lý tin đăng</h2>
                                <p class="section-subtitle mb-0">Tìm kiếm, lọc và cập nhật trạng thái các xe đạp bạn đang rao bán.</p>
                            </div>
                        </div>

                        <form class="toolbar-row" method="get" action="my-bikes.php">
                            <input
                                type="text"
                                name="keyword"
                                class="form-control"
                                placeholder="Tìm theo tên xe, hãng, địa điểm"
                                value="<?= e($keyword) ?>"
                            >
                            <select class="form-select" name="status">
                                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tất cả</option>
                                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Chờ duyệt</option>
                                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Đã duyệt</option>
                                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Từ chối</option>
                                <option value="sold" <?= $statusFilter === 'sold' ? 'selected' : '' ?>>Đã bán</option>
                            </select>
                            <select class="form-select" name="category_id">
                                <option value="0">Tất cả danh mục</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= e((int) $category['id']) ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                                        <?= e($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select" name="sort">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Cũ nhất</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Giá tăng dần</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Giá giảm dần</option>
                            </select>
                            <button class="btn btn-outline-dark" type="submit">Lọc</button>
                            <?php if ($hasActiveFilters): ?>
                                <a href="my-bikes.php" class="btn btn-outline-dark">Xóa lọc</a>
                            <?php endif; ?>
                            <a href="add-bike.php" class="btn btn-success">Đăng tin mới</a>
                        </form>

                        <p class="text-muted mb-3">
                            Hiển thị <?= e(number_format($filteredCount, 0, ',', '.')) ?> tin đăng<?= $hasActiveFilters ? ' theo bộ lọc hiện tại' : '' ?>.
                        </p>

                        <div class="listing-list">
                            <?php if (!empty($bikes)): ?>
                                <?php foreach ($bikes as $bike): ?>
                                    <?php
                                    $statusMeta = getBikeStatusMeta((string) ($bike['status'] ?? 'pending'));
                                    $bikeTitle = $bike['title'] ?? 'Xe đạp thể thao';
                                    $categoryName = $bike['category_name'] ?? 'Danh mục khác';
                                    $brandName = trim((string) ($bike['brand_name'] ?? ''));
                                    $listingSub = $brandName !== '' ? $categoryName . ' • ' . $brandName : $categoryName;
                                    $location = $bike['location'] ?? 'Đang cập nhật';
                                    $viewCount = (int) ($bike['view_count'] ?? 0);
                                    $favoriteCount = (int) ($bike['favorite_count'] ?? 0);
                                    $imageUrl = $bike['image_url'] ?? $fallbackImage;
                                    $bikeId = (int) ($bike['id'] ?? 0);
                                    $statusValue = strtolower((string) ($bike['status'] ?? 'pending'));
                                    ?>
                                    <article class="listing-item">
                                        <div class="listing-grid">
                                            <img class="listing-thumb" src="<?= e($imageUrl) ?>" alt="<?= e($bikeTitle) ?>">
                                            <div>
                                                <div class="listing-title"><?= e($bikeTitle) ?></div>
                                                <div class="listing-sub mb-2"><?= e($listingSub) ?></div>
                                                <div class="listing-meta">
                                                    <span><i class="bi bi-cash me-1"></i> <?= e(formatBikePrice($bike['price'] ?? 0)) ?></span>
                                                    <span><i class="bi bi-geo-alt me-1"></i> <?= e($location) ?></span>
                                                    <span><i class="bi bi-calendar-event me-1"></i> <?= e(formatBikeDate($bike['created_at'] ?? null)) ?></span>
                                                </div>
                                            </div>
                                            <div class="listing-side">
                                                <span class="status-badge <?= e($statusMeta['class']) ?>"><?= e($statusMeta['label']) ?></span>
                                                <div class="listing-meta">
                                                    <span><i class="bi bi-eye me-1"></i> <?= e(number_format($viewCount, 0, ',', '.')) ?> lượt xem</span>
                                                    <span><i class="bi bi-heart me-1"></i> <?= e(number_format($favoriteCount, 0, ',', '.')) ?> yêu thích</span>
                                                </div>
                                            </div>
                                            <div class="listing-actions">
                                                <a href="../bike-detail.php?id=<?= e($bikeId) ?>" class="btn btn-outline-dark">Xem</a>
                                                <a href="edit-bike.php?id=<?= e($bikeId) ?>" class="btn btn-outline-success">Sửa</a>
                                                <form method="post" action="my-bikes.php" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa tin đăng này?');">
                                                    <input type="hidden" name="action" value="delete_bike">
                                                    <input type="hidden" name="bike_id" value="<?= e($bikeId) ?>">
                                                    <button type="submit" class="btn btn-outline-dark">Xóa</button>
                                                </form>
                                                <?php if (!in_array($statusValue, ['sold', 'completed'], true)): ?>
                                                    <a href="#" class="btn btn-success">Đánh dấu đã bán</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="helper-card text-center">
                                    <div class="stats-icon mx-auto mb-3"><i class="bi bi-bicycle"></i></div>
                                    <h3 class="section-heading">Bạn chưa có tin đăng nào</h3>
                                    <p class="mb-3">Hãy đăng chiếc xe đầu tiên để bắt đầu tiếp cận người mua trên hệ thống.</p>
                                    <a href="add-bike.php" class="btn btn-success">Đăng tin mới</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($filteredCount > 0): ?>
                            <div class="pagination-wrap">
                                <span class="text-muted">Đã tải toàn bộ <?= e(number_format($filteredCount, 0, ',', '.')) ?> tin đăng phù hợp.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="helper-card">
                        <h3 class="section-heading">Trạng thái tin đăng</h3>
                        <p class="mb-2"><strong>Chờ duyệt:</strong> Tin đang được hệ thống kiểm tra trước khi hiển thị công khai.</p>
                        <p class="mb-2"><strong>Đã duyệt:</strong> Tin đã xuất hiện công khai trên marketplace và người mua có thể xem.</p>
                        <p class="mb-2"><strong>Từ chối:</strong> Tin cần chỉnh sửa thêm nội dung hoặc hình ảnh trước khi đăng lại.</p>
                        <p class="mb-0"><strong>Đã bán:</strong> Xe đã hoàn tất giao dịch và không còn hiển thị như một tin đang mở bán.</p>
                    </div>

                    <div class="tips-card mt-4">
                        <h3 class="section-heading">Mẹo quản lý tin đăng</h3>
                        <ul class="tip-list">
                            <li><i class="bi bi-check-circle-fill"></i><span>Cập nhật mô tả rõ ràng khi có thay đổi về tình trạng xe.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Thêm ảnh chất lượng cao để tăng mức độ quan tâm từ người mua.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Kiểm tra giá bán hợp lý để nâng cao cơ hội chốt giao dịch.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Theo dõi trạng thái duyệt để chỉnh sửa tin bị từ chối kịp thời.</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
        <section class="container">
            <div class="cta-band">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <h2 class="fw-bold mb-2">Muốn đăng thêm xe mới?</h2>
                        <p class="mb-0">Tạo tin đăng chất lượng để tiếp cận nhiều người mua hơn.</p>
                    </div>
                    <div class="col-lg-4 d-flex flex-column flex-sm-row gap-3 justify-content-lg-end">
                        <a href="add-bike.php" class="btn btn-warning text-dark">Đăng tin ngay</a>
                        <a href="../bikes.php" class="btn btn-outline-light">Xem trang xe đạp</a>
                    </div>
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
                            <h2 class="section-title text-white mb-3">Cần hỗ trợ quản lý tin bán?</h2>
                            <p class="contact-copy">Người bán có thể gửi yêu cầu hỗ trợ kiểm duyệt, cập nhật tin đăng, xử lý giao dịch hoặc trao đổi về các vấn đề phát sinh với người mua.</p>
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
                                        <p>seller-support@bikemarketplace.com</p>
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
                        <form class="contact-form h-100" action="my-bikes.php#contact" method="post">
                            <input type="hidden" name="contact_form" value="1">
                            <?php if ($contactSent): ?>
                                <div class="alert alert-success" role="alert">
                                    Cảm ơn bạn đã liên hệ. Bike Marketplace sẽ phản hồi trong thời gian sớm nhất.
                                </div>
                            <?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_name">Họ và tên</label>
                                    <input type="text" class="form-control" id="contact_name" name="contact_name" value="<?= e($sellerName) ?>" placeholder="Nhập họ và tên">
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
                                        <option>Hỗ trợ đăng tin</option>
                                        <option>Kiểm duyệt tin bán</option>
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
                    <p>Nền tảng mua bán xe đạp hiện đại giúp người bán theo dõi, chỉnh sửa và quản lý toàn bộ tin đăng trong một giao diện rõ ràng.</p>
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
                <small>&copy; 2026 Bike Marketplace. Trang quản lý tin đăng được xây dựng với HTML, CSS, Bootstrap 5 và Bootstrap Icons.</small>
            </div>
        </div>
    </footer>

    <?php require __DIR__ . '/../includes/chat-widget.php'; ?>
    <script src="<?= e(baseUrl('js/chat-widget.js')) ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
