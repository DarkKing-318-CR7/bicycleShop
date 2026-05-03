<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/support-messages.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (!hasRole('admin')) {
    redirect('../index.php');
}

requireRole('admin');

$currentUser = currentUser();
$adminName = $currentUser['full_name'] ?? 'Quản trị viên';
$message = '';
$messageType = 'success';

function getInitials(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'AD';
    }

    $parts = preg_split('/\s+/', $name);
    $initials = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return strtoupper($initials ?: 'AD');
}

function fetchCount(mysqli $conn, string $sql, int $fallback = 0): int
{
    $result = $conn->query($sql);

    if (!$result) {
        return $fallback;
    }

    $row = $result->fetch_assoc();

    return isset($row['total']) ? (int) $row['total'] : $fallback;
}

function adminBikeImageSrc(?string $path): string
{
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (preg_match('/^(https?:)?\/\//i', $path) || preg_match('/^data:image\//i', $path) || substr($path, 0, 1) === '/') {
        return $path;
    }

    return '../' . ltrim($path, '/');
}

function bikeConditionText(?string $status): string
{
    return match ($status) {
        'new' => 'Mới',
        'like_new' => 'Đã qua sử dụng - Rất tốt',
        'used' => 'Đã qua sử dụng - Tốt',
        default => $status ?: 'Chưa cập nhật',
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderate_bike'])) {
    $bikeId = (int) ($_POST['bike_id'] ?? 0);
    $action = $_POST['action_type'] ?? '';

    if ($bikeId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE bikes SET status = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'");

        if ($stmt) {
            $stmt->bind_param("si", $newStatus, $bikeId);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $stmt->close();
                header('Location: moderation.php?msg=' . ($action === 'approve' ? 'approved' : 'rejected'));
                exit;
            }

            $stmt->close();
            header('Location: moderation.php?msg=not_found');
            exit;
        } else {
            header('Location: moderation.php?msg=prepare_error');
            exit;
        }
    } else {
        header('Location: moderation.php?msg=invalid');
        exit;
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'approved') {
        $message = 'Đã duyệt tin đăng thành công.';
    } elseif ($_GET['msg'] === 'rejected') {
        $message = 'Đã từ chối tin đăng thành công.';
    } elseif ($_GET['msg'] === 'not_found') {
        $message = 'Không tìm thấy tin đang chờ duyệt hoặc tin đã được xử lý.';
        $messageType = 'warning';
    } elseif ($_GET['msg'] === 'prepare_error') {
        $message = 'Không thể chuẩn bị truy vấn cập nhật trạng thái.';
        $messageType = 'danger';
    } elseif ($_GET['msg'] === 'invalid') {
        $message = 'Dữ liệu kiểm duyệt không hợp lệ.';
        $messageType = 'danger';
    }
}

$adminInitials = getInitials($adminName);
$keyword = trim($_GET['keyword'] ?? '');
$categoryFilter = (int) ($_GET['category_id'] ?? 0);
$sortFilter = trim($_GET['sort'] ?? 'latest');
$highlightBikeId = (int) ($_GET['bike_id'] ?? 0);
$pendingTotal = fetchCount($conn, "SELECT COUNT(*) AS total FROM bikes WHERE status = 'pending'");

$categoryList = [];
$categoryResult = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categoryList[] = $row;
    }
}

$bikeList = [];
$sql = "
    SELECT
        b.id,
        b.slug,
        b.title,
        b.price,
        b.location,
        b.status,
        b.is_featured,
        b.view_count,
        b.created_at,
        b.updated_at,
        b.description,
        b.frame_size,
        b.wheel_size,
        b.color,
        b.condition_status,
        c.name AS category_name,
        br.name AS brand_name,
        u.full_name AS seller_name,
        u.email AS seller_email,
        u.phone AS seller_phone,
        u.address AS seller_address,
        u.role AS seller_role,
        u.status AS seller_status,
        u.created_at AS seller_created_at,
        (
            SELECT bi.image_url
            FROM bike_images bi
            WHERE bi.bike_id = b.id
            ORDER BY bi.is_primary DESC, bi.sort_order ASC, bi.id ASC
            LIMIT 1
        ) AS image_url
    FROM bikes b
    LEFT JOIN users u ON b.seller_id = u.id
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN brands br ON b.brand_id = br.id
    WHERE b.status = 'pending'
";

$params = [];
$types = '';

if ($keyword !== '') {
    $sql .= " AND (b.title LIKE ? OR u.full_name LIKE ? OR b.location LIKE ?)";
    $keywordLike = '%' . $keyword . '%';
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $types .= 'sss';
}

if ($categoryFilter > 0) {
    $sql .= " AND b.category_id = ?";
    $params[] = $categoryFilter;
    $types .= 'i';
}

switch ($sortFilter) {
    case 'price_asc':
        $sql .= " ORDER BY b.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY b.price DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY b.created_at ASC";
        break;
    default:
        $sql .= " ORDER BY b.created_at DESC";
        break;
}

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $bikeList[] = $row;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace Admin | Kiểm duyệt tin đăng</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/bike-marketplace.css">
</head>

<body class="admin-dashboard-page">
    <header class="admin-topbar">
        <div class="container-fluid px-3 px-lg-4 py-3">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="brand-mark"><i class="bi bi-bicycle"></i></span>
                    <div class="brand-title">Bike Marketplace Admin</div>
                </div>
                <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3 w-100 justify-content-lg-end">
                    <div class="admin-search-wrap" data-global-search-root>
                        <input type="text" class="form-control admin-search" placeholder="Tìm kiếm hệ thống…" autocomplete="off" data-global-search-input data-global-search-url="../includes/global-search.php">
                        <div class="admin-global-search-dropdown" data-global-search-results></div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php renderAdminNotificationDropdown($conn); ?>
                        <?php renderAdminSupportDropdown($conn); ?>
                        <div class="d-flex align-items-center gap-2">
                            <span class="admin-avatar"><?= e($adminInitials) ?></span>
                            <div class="small">
                                <div class="fw-bold"><?= e($adminName) ?></div>
                                <div class="text-muted">Admin hệ thống</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="admin-shell">
        <div class="container-fluid px-3 px-lg-4">
            <div class="row g-4">
                <aside class="col-xl-2 col-lg-3">
                    <div class="sidebar-card admin-sidebar">
                        <ul class="menu-list">
                            <li><a class="menu-link" href="index.php"><i class="bi bi-grid"></i> Tổng quan</a></li>
                            <li><a class="menu-link" href="bikes.php"><i class="bi bi-card-list"></i> Quản lý tin đăng</a></li>
                            <li><a class="menu-link" href="users.php"><i class="bi bi-people"></i> Quản lý người dùng</a></li>
                            <li><a class="menu-link" href="orders.php"><i class="bi bi-receipt"></i> Quản lý đơn mua</a></li>
                            <li><a class="menu-link" href="categories.php"><i class="bi bi-tags"></i> Danh mục xe</a></li>
                            <li><a class="menu-link" href="brands.php"><i class="bi bi-award"></i> Thương hiệu</a></li>
                            <li><a class="menu-link active" href="moderation.php"><i class="bi bi-shield-check"></i> Kiểm duyệt</a></li>
                            <li><a class="menu-link" href="statistics.php"><i class="bi bi-bar-chart"></i> Thống kê</a></li>
                            <li><a class="menu-link" href="settings.php"><i class="bi bi-gear"></i> Cài đặt</a></li>
                            <li><a class="menu-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a></li>
                        </ul>
                    </div>
                </aside>

                <div class="col-xl-10 col-lg-9">
                    <div class="page-breadcrumb">Admin / Kiểm duyệt</div>
                    <div class="page-kicker">Kiểm duyệt tin đăng</div>
                    <h1 class="section-title mb-2">Kiểm duyệt tin đăng xe đạp</h1>
                    <p class="section-subtitle mb-4">Duyệt hoặc từ chối các tin đăng mới trước khi hiển thị trong hệ thống.</p>

                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
                    <?php endif; ?>

                    <div class="content-card mb-4">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex flex-column flex-xl-row gap-3 align-items-xl-center justify-content-between">
                                <div>
                                    <h2 class="section-heading mb-1">Bộ lọc kiểm duyệt</h2>
                                    <p class="text-muted mb-0">Lọc nhanh các tin đang chờ duyệt theo tên xe, người đăng, khu vực hoặc danh mục.</p>
                                </div>
                                <div class="text-muted">
                                    Có <?= e(number_format($pendingTotal, 0, ',', '.')) ?> tin chưa kiểm duyệt
                                </div>
                            </div>
                            <form method="get">
                                <div class="row g-3">
                                    <?php if ($highlightBikeId > 0): ?>
                                        <input type="hidden" name="bike_id" value="<?= (int) $highlightBikeId ?>">
                                    <?php endif; ?>
                                    <div class="col-xl-4 col-md-6">
                                        <input
                                            type="text"
                                            name="keyword"
                                            class="form-control"
                                            placeholder="Tìm theo tên xe, người đăng hoặc khu vực"
                                            value="<?= e($keyword) ?>">
                                    </div>
                                    <div class="col-xl-3 col-md-6">
                                        <select name="category_id" class="form-select">
                                            <option value="0">Tất cả danh mục</option>
                                            <?php foreach ($categoryList as $category): ?>
                                                <option value="<?= (int) $category['id'] ?>" <?= $categoryFilter === (int) $category['id'] ? 'selected' : '' ?>>
                                                    <?= e($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-xl-3 col-md-6">
                                        <select name="sort" class="form-select">
                                            <option value="latest" <?= $sortFilter === 'latest' ? 'selected' : '' ?>>Mới nhất</option>
                                            <option value="oldest" <?= $sortFilter === 'oldest' ? 'selected' : '' ?>>Cũ nhất</option>
                                            <option value="price_asc" <?= $sortFilter === 'price_asc' ? 'selected' : '' ?>>Giá tăng dần</option>
                                            <option value="price_desc" <?= $sortFilter === 'price_desc' ? 'selected' : '' ?>>Giá giảm dần</option>
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-md-6">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="bi bi-funnel me-2"></i>Lọc
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                            <div>
                                <h2 class="section-heading mb-1">Tin đăng đang chờ duyệt</h2>
                                <p class="text-muted mb-0">Chỉ hiển thị các tin có trạng thái Chưa kiểm duyệt.</p>
                            </div>
                            <div class="text-muted small">
                                Hiển thị <?= e((string) count($bikeList)) ?> tin chờ duyệt
                            </div>
                        </div>

                        <?php if (!empty($bikeList)): ?>
                            <div class="moderation-grid">
                                <?php foreach ($bikeList as $bike): ?>
                                    <?php $imageUrl = adminBikeImageSrc($bike['image_url'] ?? ''); ?>
                                    <article class="moderation-card <?= $highlightBikeId === (int) $bike['id'] ? 'is-highlighted' : '' ?>">
                                        <div class="moderation-thumb">
                                            <?php if ($imageUrl !== ''): ?>
                                                <img
                                                    src="<?= e($imageUrl) ?>"
                                                    alt="<?= e($bike['title']) ?>"
                                                    onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
                                                <div class="moderation-thumb-placeholder d-none"><i class="bi bi-image"></i></div>
                                            <?php else: ?>
                                                <div class="moderation-thumb-placeholder"><i class="bi bi-image"></i></div>
                                            <?php endif; ?>
                                            <span class="status-badge status-pending">Chưa kiểm duyệt</span>
                                        </div>
                                        <div class="moderation-body">
                                            <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                                                <div>
                                                    <h3 class="moderation-title"><?= e($bike['title']) ?></h3>
                                                    <div class="moderation-sub"><i class="bi bi-person me-1"></i><?= e($bike['seller_name'] ?? 'Không rõ') ?></div>
                                                </div>
                                                <div class="moderation-price"><?= e(number_format((float) $bike['price'], 0, ',', '.')) ?>đ</div>
                                            </div>
                                            <div class="moderation-meta">
                                                <span><i class="bi bi-tags"></i><?= e($bike['category_name'] ?? 'Chưa phân loại') ?></span>
                                                <span><i class="bi bi-geo-alt"></i><?= e($bike['location'] ?? 'Chưa cập nhật') ?></span>
                                                <span><i class="bi bi-calendar3"></i><?= e(date('d/m/Y', strtotime($bike['created_at']))) ?></span>
                                            </div>
                                            <div class="moderation-actions">
                                                <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#bikeModal<?= (int) $bike['id'] ?>">
                                                    <i class="bi bi-eye me-1"></i>Xem
                                                </button>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="bike_id" value="<?= (int) $bike['id'] ?>">
                                                    <input type="hidden" name="action_type" value="approve">
                                                    <button type="submit" name="moderate_bike" class="btn btn-sm btn-success">
                                                        <i class="bi bi-check2-circle me-1"></i>Duyệt
                                                    </button>
                                                </form>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn từ chối tin đăng này?');">
                                                    <input type="hidden" name="bike_id" value="<?= (int) $bike['id'] ?>">
                                                    <input type="hidden" name="action_type" value="reject">
                                                    <button type="submit" name="moderate_bike" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-x-circle me-1"></i>Từ chối
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <div class="stats-icon mx-auto mb-3"><i class="bi bi-inbox"></i></div>
                                Không có tin đăng nào đang chờ duyệt.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bottom-note">© 2026 Bike Marketplace Admin Panel</div>
                </div>
            </div>
        </div>
    </main>

    <?php foreach ($bikeList as $bike): ?>
        <?php $modalImageUrl = adminBikeImageSrc($bike['image_url'] ?? ''); ?>
        <div class="modal fade" id="bikeModal<?= (int) $bike['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Chi tiết tin đăng</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-4">
                            <div class="col-lg-5">
                                <div class="moderation-modal-image">
                                    <?php if ($modalImageUrl !== ''): ?>
                                        <img
                                            src="<?= e($modalImageUrl) ?>"
                                            alt="<?= e($bike['title']) ?>"
                                            onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
                                        <div class="moderation-thumb-placeholder d-none"><i class="bi bi-image"></i></div>
                                    <?php else: ?>
                                        <div class="moderation-thumb-placeholder"><i class="bi bi-image"></i></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                    <span class="status-badge status-pending">Chưa kiểm duyệt</span>
                                    <span class="text-muted small">Mã tin #<?= (int) $bike['id'] ?></span>
                                </div>
                                <h4 class="fw-bold mb-2"><?= e($bike['title']) ?></h4>
                                <div class="fs-4 fw-bold text-success mb-3"><?= e(number_format((float) $bike['price'], 0, ',', '.')) ?>đ</div>
                                <div class="moderation-detail-list">
                                    <div><span>Danh mục</span><strong><?= e($bike['category_name'] ?? 'Chưa phân loại') ?></strong></div>
                                    <div><span>Thương hiệu</span><strong><?= e($bike['brand_name'] ?? 'Chưa cập nhật') ?></strong></div>
                                    <div><span>Khu vực</span><strong><?= e($bike['location'] ?? 'Chưa cập nhật') ?></strong></div>
                                    <div><span>Ngày đăng</span><strong><?= e(date('d/m/Y H:i', strtotime($bike['created_at']))) ?></strong></div>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="fw-bold mb-3">Thông tin tin đăng</h6>
                                <div class="moderation-detail-list">
                                    <div><span>Slug</span><strong><?= e($bike['slug'] ?? '') ?></strong></div>
                                    <div><span>Trạng thái</span><strong>Chưa kiểm duyệt</strong></div>
                                    <div><span>Tình trạng xe</span><strong><?= e(bikeConditionText($bike['condition_status'] ?? '')) ?></strong></div>
                                    <div><span>Frame size</span><strong><?= e($bike['frame_size'] ?? 'Chưa cập nhật') ?></strong></div>
                                    <div><span>Wheel size</span><strong><?= e($bike['wheel_size'] ?? 'Chưa cập nhật') ?></strong></div>
                                    <div><span>Màu sắc</span><strong><?= e($bike['color'] ?? 'Chưa cập nhật') ?></strong></div>
                                    <div><span>Lượt xem</span><strong><?= e((string)($bike['view_count'] ?? 0)) ?></strong></div>
                                    <div><span>Tin nổi bật</span><strong><?= !empty($bike['is_featured']) ? 'Có' : 'Không' ?></strong></div>
                                    <div><span>Cập nhật</span><strong><?= !empty($bike['updated_at']) ? e(date('d/m/Y H:i', strtotime($bike['updated_at']))) : 'Chưa cập nhật' ?></strong></div>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="fw-bold mb-3">Thông tin người đăng</h6>
                                <div class="moderation-detail-list">
                                    <div><span>Họ tên</span><strong><?= e($bike['seller_name'] ?? 'Không rõ') ?></strong></div>
                                    <div><span>Email</span><strong><?= e($bike['seller_email'] ?? 'Chưa cập nhật') ?></strong></div>
                                    <div><span>Số điện thoại</span><strong><?= e($bike['seller_phone'] ?? 'Chưa cập nhật') ?></strong></div>
                                    <div><span>Địa chỉ</span><strong><?= e($bike['seller_address'] ?? 'Chưa cập nhật') ?></strong></div>
                                    <div><span>Vai trò</span><strong><?= e($bike['seller_role'] ?? 'seller') ?></strong></div>
                                    <div><span>Trạng thái tài khoản</span><strong><?= e($bike['seller_status'] ?? 'Chưa cập nhật') ?></strong></div>
                                    <div><span>Ngày tham gia</span><strong><?= !empty($bike['seller_created_at']) ? e(date('d/m/Y H:i', strtotime($bike['seller_created_at']))) : 'Chưa cập nhật' ?></strong></div>
                                </div>
                            </div>

                            <div class="col-12">
                                <h6 class="fw-bold mb-2">Mô tả</h6>
                                <div class="moderation-description"><?= nl2br(e($bike['description'] ?? '')) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="bike_id" value="<?= (int) $bike['id'] ?>">
                            <input type="hidden" name="action_type" value="approve">
                            <button type="submit" name="moderate_bike" class="btn btn-success">Duyệt</button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn từ chối tin đăng này?');">
                            <input type="hidden" name="bike_id" value="<?= (int) $bike['id'] ?>">
                            <input type="hidden" name="action_type" value="reject">
                            <button type="submit" name="moderate_bike" class="btn btn-outline-danger">Từ chối</button>
                        </form>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="../js/admin-notifications.js"></script>
    <script src="../js/admin-global-search.js"></script>
</body>

</html>
