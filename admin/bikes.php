<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderate_bike'])) {
    $bikeId = (int)($_POST['bike_id'] ?? 0);
    $action = $_POST['action_type'] ?? '';

    if ($bikeId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        $stmt = $conn->prepare("UPDATE bikes SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $bikeId);

        if ($stmt->execute()) {
            header('Location: bikes.php?msg=' . ($action === 'approve' ? 'approved' : 'rejected'));
            exit;
        } else {
            $message = 'Có lỗi xảy ra khi cập nhật trạng thái tin đăng.';
            $messageType = 'danger';
        }

        $stmt->close();
    } else {
        $message = 'Dữ liệu không hợp lệ.';
        $messageType = 'danger';
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'approved') {
        $message = 'Đã duyệt tin đăng thành công.';
    } elseif ($_GET['msg'] === 'rejected') {
        $message = 'Đã từ chối tin đăng thành công.';
    }
}

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

        $initials .= function_exists('mb_substr')
            ? mb_substr($part, 0, 1, 'UTF-8')
            : substr($part, 0, 1);

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

$adminInitials = getInitials($adminName);

$totalBikes = fetchCount($conn, "SELECT COUNT(*) AS total FROM bikes");
$pendingBikes = fetchCount($conn, "SELECT COUNT(*) AS total FROM bikes WHERE status = 'pending'");
$approvedBikes = fetchCount($conn, "SELECT COUNT(*) AS total FROM bikes WHERE status = 'approved'");
$soldBikes = fetchCount($conn, "SELECT COUNT(*) AS total FROM bikes WHERE status IN ('sold', 'completed')");

$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = (int)($_GET['category_id'] ?? 0);
$sortFilter = trim($_GET['sort'] ?? 'latest');

$categoryList = [];
$categoryResult = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categoryList[] = $row;
    }
}

$viewCountSelect = tableColumnExists($conn, 'bikes', 'view_count') ? 'b.view_count' : '0';

$sql = "
    SELECT 
        b.id,
        b.title,
        b.price,
        b.location,
        b.status,
        b.created_at,
        {$viewCountSelect} AS view_count,
        b.description,
        b.frame_size,
        b.wheel_size,
        b.color,
        b.condition_status,
        c.name AS category_name,
        u.full_name AS seller_name,
        u.email AS seller_email,
        u.phone AS seller_phone,
        (
            SELECT bi.image_url
            FROM bike_images bi
            WHERE bi.bike_id = b.id
            ORDER BY bi.is_primary DESC, bi.sort_order ASC, bi.id ASC
            LIMIT 1
        ) AS image_url
    FROM bikes b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN users u ON b.seller_id = u.id
    WHERE 1 = 1
";

$params = [];
$types = '';

if ($keyword !== '') {
    $sql .= " AND (b.title LIKE ? OR u.full_name LIKE ?)";
    $keywordLike = '%' . $keyword . '%';
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $types .= 'ss';
}

if ($statusFilter !== '') {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
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

$sql .= " LIMIT 10";

$bikeList = [];
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
    <title>Bike Marketplace Admin | Quản lý tin đăng</title>
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
                    <input type="text" class="form-control admin-search" style="max-width: 320px;" placeholder="Tìm kiếm tin đăng, người đăng, trạng thái">
                    <div class="d-flex align-items-center gap-2">
                        <button class="admin-icon-btn" type="button"><i class="bi bi-bell"></i></button>
                        <button class="admin-icon-btn" type="button"><i class="bi bi-chat-dots"></i></button>
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
                            <li><a class="menu-link active" href="bikes.php"><i class="bi bi-card-list"></i> Quản lý tin đăng</a></li>
                            <li><a class="menu-link" href="users.php"><i class="bi bi-people"></i> Quản lý người dùng</a></li>
                            <li><a class="menu-link" href="orders.php"><i class="bi bi-receipt"></i> Quản lý đơn mua</a></li>
                            <li><a class="menu-link" href="categories.php"><i class="bi bi-tags"></i> Danh mục xe</a></li>
                            <li><a class="menu-link" href="brands.php"><i class="bi bi-award"></i> Thương hiệu</a></li>
                            <li><a class="menu-link" href="moderation.php"><i class="bi bi-shield-check"></i> Kiểm duyệt</a></li>
                            <li><a class="menu-link" href="statistics.php"><i class="bi bi-bar-chart"></i> Thống kê</a></li>
                            <li><a class="menu-link" href="settings.php"><i class="bi bi-gear"></i> Cài đặt</a></li>
                            <li><a class="menu-link" href="../login.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a></li>
                        </ul>
                    </div>
                </aside>

                <div class="col-xl-10 col-lg-9">
                    <div class="page-breadcrumb">Admin / Quản lý tin đăng</div>
                    <div class="page-kicker">Quản lý tin đăng</div>
                    <h1 class="section-title mb-2">Quản lý tin đăng xe đạp</h1>
                    <p class="section-subtitle mb-4">Kiểm tra, duyệt và quản lý toàn bộ tin đăng xe đạp trên hệ thống.</p>
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
                    <?php endif; ?>
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-card-list"></i></span>
                                <div><small>Tổng tin đăng</small><strong><?= e(number_format($totalBikes, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-hourglass-split"></i></span>
                                <div><small>Chờ duyệt</small><strong><?= e(number_format($pendingBikes, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-patch-check"></i></span>
                                <div><small>Đã duyệt</small><strong><?= e(number_format($approvedBikes, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-bag-check"></i></span>
                                <div><small>Đã bán</small><strong>80</strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="content-card mb-4">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex flex-column flex-xl-row gap-3 align-items-xl-center justify-content-between">
                                <div>
                                    <h2 class="section-heading mb-1">Bộ lọc kiểm duyệt</h2>
                                    <p class="text-muted mb-0">Lọc nhanh tin đăng theo trạng thái, danh mục và giá để xử lý thuận tiện hơn.</p>
                                </div>
                                <a href="#" class="btn btn-outline-success"><i class="bi bi-download me-2"></i>Xuất danh sách</a>
                            </div>
                            <form method="get">
                                <div class="row g-3">
                                    <div class="col-xl-4 col-md-6">
                                        <input
                                            type="text"
                                            name="keyword"
                                            class="form-control"
                                            placeholder="Tìm theo tên xe hoặc người đăng"
                                            value="<?= e($keyword) ?>">
                                    </div>

                                    <div class="col-xl-2 col-md-6">
                                        <select name="status" class="form-select">
                                            <option value="">Tất cả</option>
                                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Chờ duyệt</option>
                                            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Đã duyệt</option>
                                            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Từ chối</option>
                                            <option value="sold" <?= $statusFilter === 'sold' ? 'selected' : '' ?>>Đã bán</option>
                                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Hoàn tất</option>
                                        </select>
                                    </div>

                                    <div class="col-xl-2 col-md-6">
                                        <select name="category_id" class="form-select">
                                            <option value="0">Danh mục xe</option>
                                            <?php foreach ($categoryList as $category): ?>
                                                <option value="<?= (int)$category['id'] ?>" <?= $categoryFilter === (int)$category['id'] ? 'selected' : '' ?>>
                                                    <?= e($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-xl-2 col-md-6">
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

                    <div class="row g-4">
                        <div class="col-xl-8">
                            <div class="content-card">
                                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                                    <div>
                                        <h2 class="section-heading mb-1">Danh sách tin đăng</h2>
                                        <p class="text-muted mb-0">Theo dõi 10 tin đăng gần nhất cần kiểm tra hoặc cập nhật trạng thái.</p>
                                    </div>
                                    <div class="text-muted">
                                        Hiển thị <?= count($bikeList) > 0 ? '1-' . count($bikeList) : '0' ?> trong <?= e($totalBikes) ?> tin đăng
                                    </div>
                                </div>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Ảnh</th>
                                                <th>Tên xe</th>
                                                <th>Người đăng</th>
                                                <th>Danh mục</th>
                                                <th>Giá</th>
                                                <th>Khu vực</th>
                                                <th>Ngày đăng</th>
                                                <th>Trạng thái</th>
                                                <th>Lượt xem</th>
                                                <th>Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($bikeList)): ?>
                                                <?php foreach ($bikeList as $bike): ?>
                                                    <?php
                                                    $statusText = match ($bike['status']) {
                                                        'pending' => 'Chờ duyệt',
                                                        'approved' => 'Đã duyệt',
                                                        'rejected' => 'Từ chối',
                                                        'sold' => 'Đã bán',
                                                        'completed' => 'Hoàn tất',
                                                        default => $bike['status']
                                                    };

                                                    $statusClass = match ($bike['status']) {
                                                        'pending' => 'status-pending',
                                                        'approved' => 'status-approved',
                                                        'rejected' => 'status-rejected',
                                                        'sold', 'completed' => 'status-sold',
                                                        default => 'status-pending'
                                                    };

                                                    $imageUrl = !empty($bike['image_url'])
                                                        ? $bike['image_url']
                                                        : 'https://via.placeholder.com/72x52?text=No+Image';
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <img
                                                                src="<?= e($imageUrl) ?>"
                                                                alt="<?= e($bike['title']) ?>"
                                                                width="72"
                                                                height="52"
                                                                class="rounded-3 object-fit-cover">
                                                        </td>
                                                        <td><?= e($bike['title']) ?></td>
                                                        <td><?= e($bike['seller_name'] ?? 'Không rõ') ?></td>
                                                        <td><?= e($bike['category_name'] ?? 'Chưa phân loại') ?></td>
                                                        <td><?= e(number_format((float)$bike['price'], 0, ',', '.')) ?>đ</td>
                                                        <td><?= e($bike['location'] ?? '') ?></td>
                                                        <td><?= e(date('d/m/Y', strtotime($bike['created_at']))) ?></td>
                                                        <td>
                                                            <span class="status-badge <?= e($statusClass) ?>">
                                                                <?= e($statusText) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= e((string)($bike['view_count'] ?? 0)) ?></td>
                                                        <td>
                                                            <div class="d-flex flex-wrap gap-2">
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-outline-dark"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#bikeModal<?= (int)$bike['id'] ?>">
                                                                    Xem
                                                                </button>

                                                                <?php if (($bike['status'] ?? '') === 'pending'): ?>
                                                                    <form method="post" class="d-inline">
                                                                        <input type="hidden" name="bike_id" value="<?= (int)$bike['id'] ?>">
                                                                        <input type="hidden" name="action_type" value="approve">
                                                                        <button type="submit" name="moderate_bike" class="btn btn-sm btn-success">
                                                                            Duyệt
                                                                        </button>
                                                                    </form>

                                                                    <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn từ chối tin đăng này?');">
                                                                        <input type="hidden" name="bike_id" value="<?= (int)$bike['id'] ?>">
                                                                        <input type="hidden" name="action_type" value="reject">
                                                                        <button type="submit" name="moderate_bike" class="btn btn-sm btn-outline-danger">
                                                                            Từ chối
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center text-muted py-4">Không có tin đăng phù hợp.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4">
                            <div class="content-card mb-4">
                                <h2 class="section-heading">Hướng dẫn kiểm duyệt</h2>
                                <div class="mini-status">
                                    <div class="mini-status-item"><strong>Kiểm tra ảnh và mô tả rõ ràng</strong>
                                        <div class="text-muted mt-1">Ảnh phải đúng sản phẩm, mô tả cần đủ tình trạng, cấu hình và lịch sử sử dụng.</div>
                                    </div>
                                    <div class="mini-status-item"><strong>Xác minh giá bán hợp lý</strong>
                                        <div class="text-muted mt-1">So sánh với thị trường để hạn chế tin đăng có giá bất thường hoặc gây hiểu nhầm.</div>
                                    </div>
                                    <div class="mini-status-item"><strong>Loại bỏ tin sai thông tin</strong>
                                        <div class="text-muted mt-1">Từ chối tin dùng ảnh không liên quan, mô tả thiếu trung thực hoặc sai danh mục xe.</div>
                                    </div>
                                    <div class="mini-status-item"><strong>Ưu tiên tin đầy đủ thông số</strong>
                                        <div class="text-muted mt-1">Tin có đủ khung, bánh, phanh, truyền động sẽ giúp người mua tin tưởng hơn.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="content-card">
                                <h2 class="section-heading">Rà soát nhanh hôm nay</h2>
                                <div class="mini-status">
                                    <div class="mini-status-item"><strong>9 tin cần duyệt gấp</strong>
                                        <div class="text-muted mt-1">Các tin đăng mới trong 6 giờ gần nhất đang chờ xử lý để hiển thị công khai.</div>
                                    </div>
                                    <div class="mini-status-item"><strong>3 tin bị báo cáo</strong>
                                        <div class="text-muted mt-1">Cần kiểm tra lại chất lượng ảnh và mô tả trước khi quyết định giữ hoặc gỡ tin.</div>
                                    </div>
                                    <div class="mini-status-item"><strong>2 tin bị từ chối hôm nay</strong>
                                        <div class="text-muted mt-1">Lý do phổ biến là thiếu ảnh thật và thông tin giá chưa rõ ràng.</div>
                                    </div>
                                    <div class="mini-status-item"><strong>Nhắc nhở</strong>
                                        <div class="text-muted mt-1">Ưu tiên xử lý nhóm chờ duyệt trước 24 giờ để đảm bảo trải nghiệm cho người bán.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <nav aria-label="Điều hướng trang" class="mt-4">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item disabled"><a class="page-link" href="#">Trước</a></li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#">Sau</a></li>
                        </ul>
                    </nav>

                    <div class="bottom-note">© 2026 Bike Marketplace Admin Panel</div>
                </div>
            </div>
        </div>
    </main>




    <?php if (!empty($bikeList)): ?>
        <?php foreach ($bikeList as $bike): ?>
            <div class="modal fade" id="bikeModal<?= (int)$bike['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Chi tiết tin đăng</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <strong>Tên xe:</strong>
                                    <div><?= e($bike['title']) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Giá:</strong>
                                    <div><?= e(number_format((float)$bike['price'], 0, ',', '.')) ?>đ</div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Người đăng:</strong>
                                    <div><?= e($bike['seller_name'] ?? 'Không rõ') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Email:</strong>
                                    <div><?= e($bike['seller_email'] ?? '') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Số điện thoại:</strong>
                                    <div><?= e($bike['seller_phone'] ?? '') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Địa điểm:</strong>
                                    <div><?= e($bike['location'] ?? '') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Danh mục:</strong>
                                    <div><?= e($bike['category_name'] ?? 'Chưa phân loại') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Lượt xem:</strong>
                                    <div><?= e((string)($bike['view_count'] ?? 0)) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Frame size:</strong>
                                    <div><?= e($bike['frame_size'] ?? '') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Wheel size:</strong>
                                    <div><?= e($bike['wheel_size'] ?? '') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Màu sắc:</strong>
                                    <div><?= e($bike['color'] ?? '') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Tình trạng:</strong>
                                    <div><?= e($bike['condition_status'] ?? '') ?></div>
                                </div>
                                <div class="col-12">
                                    <strong>Mô tả:</strong>
                                    <div class="mt-2"><?= nl2br(e($bike['description'] ?? '')) ?></div>
                                </div>
                                <div class="col-12">
                                    <strong>Ngày đăng:</strong>
                                    <div><?= e(date('d/m/Y H:i', strtotime($bike['created_at']))) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <?php if (($bike['status'] ?? '') === 'pending'): ?>
                                <a href="moderation.php?bike_id=<?= (int)$bike['id'] ?>" class="btn btn-outline-dark">
                                    Xem chi tiết
                                </a>

                                <form method="post" class="d-inline">
                                    <input type="hidden" name="bike_id" value="<?= (int)$bike['id'] ?>">
                                    <input type="hidden" name="action_type" value="approve">
                                    <button type="submit" name="moderate_bike" class="btn btn-success">Duyệt</button>
                                </form>

                                <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn từ chối tin đăng này?');">
                                    <input type="hidden" name="bike_id" value="<?= (int)$bike['id'] ?>">
                                    <input type="hidden" name="action_type" value="reject">
                                    <button type="submit" name="moderate_bike" class="btn btn-outline-danger">Từ chối</button>
                                </form>
                            <?php endif; ?>

                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

</body>

</html>
