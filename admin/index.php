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

function percentOf(int $value, int $total): int
{
    if ($total <= 0) {
        return 0;
    }

    return (int) round(($value / $total) * 100);
}

$totalUsers = fetchCount($conn, "SELECT COUNT(*) AS total FROM users");
$totalBikes = fetchCount($conn, "SELECT COUNT(*) AS total FROM bikes");
$totalOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders");
$totalCategories = fetchCount($conn, "SELECT COUNT(*) AS total FROM categories");
$totalBrands = fetchCount($conn, "SELECT COUNT(*) AS total FROM brands");
$approvedTodayBikes = fetchCount($conn, "SELECT COUNT(*) AS total FROM bikes WHERE status = 'approved' AND DATE(updated_at) = CURDATE()");
$newUsersToday = fetchCount($conn, "SELECT COUNT(*) AS total FROM users WHERE DATE(created_at) = CURDATE()");

$bikeStatusCounts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'sold' => 0,
    'completed' => 0,
];
$bikeStatusResult = $conn->query("SELECT status, COUNT(*) AS total FROM bikes GROUP BY status");

if ($bikeStatusResult) {
    while ($row = $bikeStatusResult->fetch_assoc()) {
        $bikeStatusCounts[(string) $row['status']] = (int) $row['total'];
    }
}

$pendingBikes = $bikeStatusCounts['pending'] ?? 0;
$approvedBikes = $bikeStatusCounts['approved'] ?? 0;
$rejectedBikes = $bikeStatusCounts['rejected'] ?? 0;
$soldBikes = $bikeStatusCounts['sold'] ?? 0;
$completedBikes = $bikeStatusCounts['completed'] ?? 0;

$activityByDate = [];
$activityResult = $conn->query("
    SELECT DATE(created_at) AS day_key, COUNT(*) AS total
    FROM bikes
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
");

if ($activityResult) {
    while ($row = $activityResult->fetch_assoc()) {
        $activityByDate[$row['day_key']] = (int) $row['total'];
    }
}

$bikeActivity = [];
for ($i = 6; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-{$i} days"));
    $bikeActivity[] = [
        'label' => date('d/m', strtotime($dateKey)),
        'total' => $activityByDate[$dateKey] ?? 0,
    ];
}

$maxActivity = max(1, ...array_column($bikeActivity, 'total'));

$statusDistribution = [
    ['label' => 'Chưa kiểm duyệt', 'total' => $pendingBikes, 'color' => '#ffb703', 'class' => 'status-pending'],
    ['label' => 'Đã duyệt', 'total' => $approvedBikes, 'color' => '#2f7d32', 'class' => 'status-approved'],
    ['label' => 'Từ chối', 'total' => $rejectedBikes, 'color' => '#dc3545', 'class' => 'status-rejected'],
    ['label' => 'Đã bán', 'total' => $soldBikes + $completedBikes, 'color' => '#0d6efd', 'class' => 'status-sold'],
];
$statusTotal = array_sum(array_column($statusDistribution, 'total'));
$donutStops = [];
$donutStart = 0;

foreach ($statusDistribution as $statusItem) {
    $slice = $statusTotal > 0 ? (($statusItem['total'] / $statusTotal) * 100) : 0;
    $donutEnd = $donutStart + $slice;

    if ($slice > 0) {
        $donutStops[] = "{$statusItem['color']} {$donutStart}% {$donutEnd}%";
    }

    $donutStart = $donutEnd;
}

$donutGradient = !empty($donutStops)
    ? 'conic-gradient(' . implode(', ', $donutStops) . ')'
    : 'conic-gradient(#e8eee8 0 100%)';


$recentBikeList = [];
$bikeResult = $conn->query("
    SELECT 
        b.id,
        b.title,
        b.price,
        b.location,
        b.frame_size,
        b.wheel_size,
        b.color,
        b.condition_status,
        b.description,
        b.created_at,
        b.status,
        u.full_name AS seller_name,
        u.email AS seller_email,
        u.phone AS seller_phone
    FROM bikes b
    LEFT JOIN users u ON b.seller_id = u.id
    ORDER BY b.created_at DESC
    LIMIT 10
");

if ($bikeResult) {
    while ($row = $bikeResult->fetch_assoc()) {
        $recentBikeList[] = $row;
    }
}

$recentUserList = [];
$userResult = $conn->query("
    SELECT full_name, email, role, status, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 4
");

if ($userResult) {
    while ($row = $userResult->fetch_assoc()) {
        $recentUserList[] = $row;
    }
}

function bikeStatusText(string $status): string
{
    return match ($status) {
        'pending' => 'Chưa kiểm duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
        'sold' => 'Đã bán',
        'completed' => 'Hoàn tất',
        default => $status
    };
}

function bikeStatusClass(string $status): string
{
    return match ($status) {
        'pending' => 'status-pending',
        'approved' => 'status-approved',
        'rejected' => 'status-rejected',
        'sold', 'completed' => 'status-sold',
        default => 'status-pending'
    };
}

function userRoleText(string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'seller' => 'Người bán',
        'buyer' => 'Người mua',
        'inspector' => 'Kiểm định viên',
        default => $role
    };
}

function userStatusText(string $status): string
{
    return match ($status) {
        'active' => 'Hoạt động',
        'inactive' => 'Không hoạt động',
        'banned' => 'Bị khóa',
        default => $status
    };
}

function userStatusClass(string $status): string
{
    return match ($status) {
        'active' => 'status-approved',
        'inactive' => 'status-pending',
        'banned' => 'status-rejected',
        default => 'status-pending'
    };
}

$adminInitials = getInitials($adminName);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace Admin | Bảng điều khiển</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/bike-marketplace.css">
</head>





<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> -->


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
                            <li><a class="menu-link active" href="index.php"><i class="bi bi-grid"></i> Tổng quan</a></li>
                            <li><a class="menu-link" href="bikes.php"><i class="bi bi-card-list"></i> Quản lý tin đăng</a></li>
                            <li><a class="menu-link" href="users.php"><i class="bi bi-people"></i> Quản lý người dùng</a></li>
                            <li><a class="menu-link" href="orders.php"><i class="bi bi-receipt"></i> Quản lý đơn mua</a></li>
                            <li><a class="menu-link" href="categories.php"><i class="bi bi-tags"></i> Danh mục xe</a></li>
                            <li><a class="menu-link" href="brands.php"><i class="bi bi-award"></i> Thương hiệu</a></li>
                            <li><a class="menu-link" href="moderation.php"><i class="bi bi-shield-check"></i> Kiểm duyệt</a></li>
                            <li><a class="menu-link" href="statistics.php"><i class="bi bi-bar-chart"></i> Thống kê</a></li>
                            <li><a class="menu-link" href="settings.php"><i class="bi bi-gear"></i> Cài đặt</a></li>
                            <li><a class="menu-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a></li>
                        </ul>
                    </div>
                </aside>

                <div class="col-xl-10 col-lg-9">
                    <div class="page-breadcrumb">Admin / Tổng quan</div>
                    <div class="page-kicker">Bảng điều khiển</div>
                    <h1 class="section-title mb-2">Bảng điều khiển quản trị</h1>
                    <p class="section-subtitle mb-4">Theo dõi hoạt động hệ thống mua bán xe đạp thể thao cũ.</p>
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
                    <?php endif; ?>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-card-list"></i></span>
                                <div><small>Tổng số tin đăng</small><strong><?= e(number_format($totalBikes, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-hourglass-split"></i></span>
                                <div><small>Tin đang chờ duyệt</small><strong><?= e(number_format($pendingBikes, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-people"></i></span>
                                <div><small>Tổng người dùng</small><strong><?= e(number_format($totalUsers, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-bag-check"></i></span>
                                <div><small>Tổng đơn mua</small><strong><?= e(number_format($totalOrders, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-cash-coin"></i></span>
                                <div><small>Tổng danh mục</small><strong><?= e(number_format($totalCategories, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-graph-up-arrow"></i></span>
                                <div><small>Tổng thương hiệu</small><strong><?= e(number_format($totalBrands, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-xl-8">
                            <div class="content-card">
                                <h2 class="section-heading">Tin đăng gần đây</h2>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Tên xe</th>
                                                <th>Người đăng</th>
                                                <th>Giá</th>
                                                <th>Ngày đăng</th>
                                                <th>Trạng thái</th>
                                                <th>Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recentBikeList)): ?>
                                                <?php foreach ($recentBikeList as $bike): ?>
                                                    <?php $bikeStatus = $bike['status'] ?? ''; ?>
                                                    <tr>
                                                        <td><?= e($bike['title']) ?></td>
                                                        <td><?= e($bike['seller_name'] ?? 'Không rõ') ?></td>
                                                        <td><?= e(number_format((float) $bike['price'], 0, ',', '.')) ?>đ</td>
                                                        <td><?= e(date('d/m/Y', strtotime($bike['created_at']))) ?></td>
                                                        <td>
                                                            <span class="status-badge <?= e(bikeStatusClass($bikeStatus)) ?>"><?= e(bikeStatusText($bikeStatus)) ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-wrap gap-2">
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-outline-dark"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#bikeModal<?= (int) $bike['id'] ?>">
                                                                    Xem
                                                                </button>

                                                                <?php if ($bikeStatus === 'pending'): ?>
                                                                    <a href="moderation.php?bike_id=<?= (int) $bike['id'] ?>" class="btn btn-sm btn-success rounded-pill px-3" title="Đi kiểm duyệt">
                                                                        <i class="bi bi-shield-check me-1"></i>Kiểm duyệt
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">Hiện chưa có tin đăng nào.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4">
                            <div class="content-card">
                                <h2 class="section-heading">Tổng quan kiểm duyệt</h2>
                                <div class="mini-status">
                                    <div class="mini-status-item"><strong><?= e(number_format($pendingBikes, 0, ',', '.')) ?> tin đang chờ duyệt</strong>
                                        <div class="text-muted mt-1">Cần xem xét để xuất hiện công khai trên marketplace.</div>
                                    </div>
                                    <div class="mini-status-item"><strong><?= e(number_format($rejectedBikes, 0, ',', '.')) ?> tin bị từ chối</strong>
                                        <div class="text-muted mt-1">Nên kiểm tra lý do và gửi hướng dẫn chỉnh sửa cho người bán.</div>
                                    </div>
                                    <div class="mini-status-item"><strong><?= e(number_format($approvedTodayBikes, 0, ',', '.')) ?> tin được duyệt hôm nay</strong>
                                        <div class="text-muted mt-1">Khối lượng duyệt hôm nay đang ở mức ổn định.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-4 mt-1">
                        <div class="col-xl-7">
                            <div class="content-card">
                                <h2 class="section-heading">Người dùng mới đăng ký</h2>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Họ tên</th>
                                                <th>Email</th>
                                                <th>Vai trò</th>
                                                <th>Ngày tham gia</th>
                                                <th>Trạng thái</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recentUserList)): ?>
                                                <?php foreach ($recentUserList as $user): ?>
                                                    <?php $userStatus = $user['status'] ?? ''; ?>
                                                    <tr>
                                                        <td><?= e($user['full_name']) ?></td>
                                                        <td><?= e($user['email']) ?></td>
                                                        <td><?= e(userRoleText($user['role'] ?? '')) ?></td>
                                                        <td><?= e(date('d/m/Y', strtotime($user['created_at']))) ?></td>
                                                        <td><span class="status-badge <?= e(userStatusClass($userStatus)) ?>"><?= e(userStatusText($userStatus)) ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-4">Hiện chưa có người dùng nào.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-5">
                            <div class="quick-card">
                                <h2 class="section-heading">Thao tác nhanh</h2>
                                <div class="quick-grid">
                                    <a href="bikes.php" class="btn btn-success">Xem tất cả tin đăng</a>
                                    <a href="moderation.php" class="btn btn-outline-dark">Duyệt tin mới</a>
                                    <a href="users.php" class="btn btn-outline-dark">Quản lý người dùng</a>
                                    <a href="statistics.php" class="btn btn-outline-success">Xem báo cáo thống kê</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mt-1">
                        <div class="col-xl-6">
                            <div class="chart-card">
                                <h2 class="section-heading">Hoạt động tin đăng</h2>
                                <div class="chart-bars">
                                    <?php foreach ($bikeActivity as $item): ?>
                                        <?php $barHeight = max(8, percentOf((int) $item['total'], $maxActivity)); ?>
                                        <div class="chart-bar-item">
                                            <div class="chart-bar-track">
                                                <div class="chart-bar-fill" style="height: <?= (int) $barHeight ?>%;"></div>
                                            </div>
                                            <strong><?= e((string) $item['total']) ?></strong>
                                            <span><?= e($item['label']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3">
                            <div class="chart-card">
                                <h2 class="section-heading">Phân bố trạng thái</h2>
                                <div class="donut-wrap">
                                    <div class="donut" style="background: <?= e($donutGradient) ?>;"></div>
                                </div>
                                <div class="status-legend mt-3">
                                    <?php foreach ($statusDistribution as $statusItem): ?>
                                        <div class="status-legend-item">
                                            <span style="background: <?= e($statusItem['color']) ?>;"></span>
                                            <div><?= e($statusItem['label']) ?></div>
                                            <strong><?= e(number_format((int) $statusItem['total'], 0, ',', '.')) ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3">
                            <div class="notice-card">
                                <h2 class="section-heading">Thông báo hệ thống</h2>
                                <div class="notice-list">
                                    <div class="notice-item">Có <?= e(number_format($pendingBikes, 0, ',', '.')) ?> tin đăng đang chờ duyệt.</div>
                                    <div class="notice-item"><?= e(number_format($newUsersToday, 0, ',', '.')) ?> tài khoản mới đăng ký hôm nay.</div>
                                    <div class="notice-item">Hệ thống hoạt động ổn định.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bottom-note">© 2026 Bike Marketplace Admin Panel</div>
                </div>
            </div>
        </div>
    </main>




    <?php if (!empty($recentBikeList)): ?>
        <?php foreach ($recentBikeList as $bike): ?>
            <div class="modal fade" id="bikeModal<?= (int) $bike['id'] ?>" tabindex="-1" aria-hidden="true">
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
                                    <div><?= e(number_format((float) $bike['price'], 0, ',', '.')) ?>đ</div>
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
                                <a href="moderation.php?bike_id=<?= (int) $bike['id'] ?>" class="btn btn-success rounded-pill px-3">
                                    <i class="bi bi-shield-check me-1"></i>Kiểm duyệt
                                </a>
                            <?php endif; ?>

                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="../js/admin-notifications.js"></script>
    <script src="../js/admin-global-search.js"></script>
</body>

</html>
