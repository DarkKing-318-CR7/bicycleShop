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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $action = trim($_POST['action_type'] ?? '');
    $newStatus = trim($_POST['new_status'] ?? '');

    if ($orderId > 0) {
        if ($action === 'cancel') {
            $newStatus = 'cancelled';
        }

        if (in_array($newStatus, ['pending', 'confirmed', 'shipping', 'completed', 'cancelled'], true)) {
            $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $orderId);

            if ($stmt->execute()) {
                header('Location: orders.php?msg=updated');
                exit;
            } else {
                $message = 'Không thể cập nhật trạng thái đơn hàng.';
                $messageType = 'danger';
            }

            $stmt->close();
        } else {
            $message = 'Trạng thái đơn hàng không hợp lệ.';
            $messageType = 'danger';
        }
    } else {
        $message = 'Dữ liệu đơn hàng không hợp lệ.';
        $messageType = 'danger';
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $message = 'Đã cập nhật trạng thái đơn hàng thành công.';
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

    return isset($row['total']) ? (int)$row['total'] : $fallback;
}

$adminInitials = getInitials($adminName);

/**
 * THỐNG KÊ ĐƠN HÀNG
 */
$totalOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders");
$pendingOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'pending'");
$confirmedOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'confirmed'");
$shippingOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'shipping'");
$completedOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'completed'");
$cancelledOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'cancelled'");

/**
 * BỘ LỌC
 */
$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$sortFilter = trim($_GET['sort'] ?? 'latest');

/**
 * Nếu sau này bạn muốn lọc theo thời gian thì dùng thêm:
 * today / 7days / 30days
 */
$timeFilter = trim($_GET['time'] ?? '');

/**
 * QUERY DANH SÁCH ĐƠN
 */
$sql = "
    SELECT 
        o.id,
        o.buyer_id,
        o.bike_id,
        o.total_amount,
        o.status,
        o.shipping_name,
        o.shipping_phone,
        o.shipping_address,
        o.note,
        o.created_at,
        b.title AS bike_title,
        buyer.full_name AS buyer_name,
        buyer.email AS buyer_email,
        buyer.phone AS buyer_phone,
        seller.full_name AS seller_name
    FROM orders o
    LEFT JOIN bikes b ON o.bike_id = b.id
    LEFT JOIN users buyer ON o.buyer_id = buyer.id
    LEFT JOIN users seller ON b.seller_id = seller.id
    WHERE 1 = 1
";

$params = [];
$types = '';

if ($keyword !== '') {
    $sql .= " AND (
        b.title LIKE ?
        OR buyer.full_name LIKE ?
        OR seller.full_name LIKE ?
        OR CAST(o.id AS CHAR) LIKE ?
    )";
    $keywordLike = '%' . $keyword . '%';
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $types .= 'ssss';
}

if ($statusFilter !== '') {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($timeFilter === 'today') {
    $sql .= " AND DATE(o.created_at) = CURDATE()";
} elseif ($timeFilter === '7days') {
    $sql .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($timeFilter === '30days') {
    $sql .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

switch ($sortFilter) {
    case 'oldest':
        $sql .= " ORDER BY o.created_at ASC";
        break;
    case 'price_asc':
        $sql .= " ORDER BY o.total_amount ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY o.total_amount DESC";
        break;
    default:
        $sql .= " ORDER BY o.created_at DESC";
        break;
}

$sql .= " LIMIT 10";

$orderList = [];
$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orderList[] = $row;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace Admin | Quản lý đơn mua</title>
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
                    <input type="text" class="form-control admin-search" style="max-width: 320px;" placeholder="Tìm kiếm đơn mua, xe đạp, người dùng">
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
                            <li><a class="menu-link" href="bikes.php"><i class="bi bi-card-list"></i> Quản lý tin đăng</a></li>
                            <li><a class="menu-link" href="users.php"><i class="bi bi-people"></i> Quản lý người dùng</a></li>
                            <li><a class="menu-link active" href="orders.php"><i class="bi bi-receipt"></i> Quản lý đơn mua</a></li>
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
                    <div class="page-breadcrumb">Admin / Quản lý đơn mua</div>
                    <div class="page-kicker">Quản lý giao dịch</div>
                    <h1 class="section-title mb-2">Quản lý đơn mua</h1>
                    <p class="section-subtitle mb-4">Theo dõi và quản lý toàn bộ giao dịch mua bán xe đạp trên hệ thống.</p>
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
                    <?php endif; ?>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-receipt"></i></span>
                                <div><small>Tổng đơn</small><strong><?= e(number_format($totalOrders, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-hourglass-split"></i></span>
                                <div><small>Chờ xác nhận</small><strong><?= e(number_format($pendingOrders, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-patch-check"></i></span>
                                <div><small>Đã xác nhận</small><strong><?= e(number_format($confirmedOrders, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-arrow-repeat"></i></span>
                                <div><small>Đang giao dịch</small><strong><?= e(number_format($shippingOrders, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-bag-check"></i></span>
                                <div><small>Hoàn tất</small><strong><?= e(number_format($completedOrders, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-x-circle"></i></span>
                                <div><small>Đã hủy</small><strong><?= e(number_format($cancelledOrders, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="content-card mb-4">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex flex-column flex-xl-row gap-3 align-items-xl-center justify-content-between">
                                <div>
                                    <h2 class="section-heading mb-1">Bộ lọc đơn mua</h2>
                                    <p class="text-muted mb-0">Tìm kiếm và rà soát nhanh các giao dịch theo trạng thái, thời gian và giá trị.</p>
                                </div>
                                <a href="#" class="btn btn-outline-success"><i class="bi bi-download me-2"></i>Xuất dữ liệu</a>
                            </div>
                            <form method="get">
                                <div class="row g-3 align-items-center">
                                    <div class="col-xl-4 col-md-6">
                                        <input
                                            type="text"
                                            name="keyword"
                                            class="form-control"
                                            placeholder="Tìm theo mã đơn, tên xe, người mua hoặc người bán"
                                            value="<?= e($keyword) ?>">
                                    </div>

                                    <div class="col-xl-2 col-md-6">
                                        <select name="status" class="form-select">
                                            <option value="">Tất cả trạng thái</option>
                                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
                                            <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
                                            <option value="shipping" <?= $statusFilter === 'shipping' ? 'selected' : '' ?>>Đang giao dịch</option>
                                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Hoàn tất</option>
                                            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                                        </select>
                                    </div>

                                    <div class="col-xl-2 col-md-6">
                                        <select name="time" class="form-select">
                                            <option value="">Tất cả thời gian</option>
                                            <option value="today" <?= $timeFilter === 'today' ? 'selected' : '' ?>>Hôm nay</option>
                                            <option value="7days" <?= $timeFilter === '7days' ? 'selected' : '' ?>>7 ngày qua</option>
                                            <option value="30days" <?= $timeFilter === '30days' ? 'selected' : '' ?>>30 ngày qua</option>
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

                    <div class="content-card">
                        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                            <div>
                                <h2 class="section-heading mb-1">Danh sách đơn mua</h2>
                                <p class="text-muted mb-0">Theo dõi các giao dịch giữa người mua và người bán trên toàn bộ nền tảng.</p>
                            </div>
                            <div class="text-muted small">
                                Hiển thị <?= count($orderList) > 0 ? '1-' . count($orderList) : '0' ?> trong <?= e(number_format($totalOrders, 0, ',', '.')) ?> đơn mua
                            </div>
                        </div>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Mã đơn</th>
                                        <th>Xe đạp</th>
                                        <th>Người mua</th>
                                        <th>Người bán</th>
                                        <th>Giá trị</th>
                                        <th>Ngày tạo</th>
                                        <th>Trạng thái</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($orderList)): ?>
                                        <?php foreach ($orderList as $order): ?>
                                            <?php
                                            $statusText = match ($order['status']) {
                                                'pending' => 'Chờ xác nhận',
                                                'confirmed' => 'Đã xác nhận',
                                                'shipping' => 'Đang giao dịch',
                                                'completed' => 'Hoàn tất',
                                                'cancelled' => 'Đã hủy',
                                                default => $order['status']
                                            };

                                            $statusClass = match ($order['status']) {
                                                'pending' => 'status-pending',
                                                'confirmed' => 'status-approved',
                                                'shipping' => 'status-pending',
                                                'completed' => 'status-approved',
                                                'cancelled' => 'status-rejected',
                                                default => 'status-pending'
                                            };
                                            ?>
                                            <tr>
                                                <td>DH<?= str_pad((string)$order['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                                <td><?= e($order['bike_title'] ?? 'Không rõ') ?></td>
                                                <td><?= e($order['buyer_name'] ?? 'Không rõ') ?></td>
                                                <td><?= e($order['seller_name'] ?? 'Không rõ') ?></td>
                                                <td><?= e(number_format((float)$order['total_amount'], 0, ',', '.')) ?>đ</td>
                                                <td><?= e(date('d/m/Y', strtotime($order['created_at']))) ?></td>
                                                <td>
                                                    <span class="status-badge <?= e($statusClass) ?>">
                                                        <?= e($statusText) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-dark rounded-pill px-3"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#orderViewModal<?= (int)$order['id'] ?>">
                                                            Xem
                                                        </button>

                                                        <?php if (($order['status'] ?? '') !== 'completed' && ($order['status'] ?? '') !== 'cancelled'): ?>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-success rounded-pill px-3"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#orderUpdateModal<?= (int)$order['id'] ?>">
                                                                Cập nhật
                                                            </button>

                                                            <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn hủy đơn này?');">
                                                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                                                <input type="hidden" name="action_type" value="cancel">
                                                                <button type="submit" name="update_order_status" class="btn btn-sm btn-danger rounded-pill px-3">
                                                                    Hủy đơn
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Không có đơn mua phù hợp.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty state alternative for PHP rendering when result is empty
                        <div class="helper-card mt-4 text-center">
                            <div class="stats-icon mx-auto mb-3"><i class="bi bi-inbox"></i></div>
                            <h3 class="section-heading">Không có đơn mua nào phù hợp với bộ lọc hiện tại</h3>
                            <p class="text-muted mb-3">Hãy điều chỉnh lại bộ lọc để xem thêm kết quả giao dịch trên hệ thống.</p>
                            <a href="#" class="btn btn-success">Xóa bộ lọc</a>
                        </div>
                        -->

                        <nav aria-label="Điều hướng trang" class="mt-4">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item disabled"><a class="page-link" href="#">Trước</a></li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item"><a class="page-link" href="#">Sau</a></li>
                            </ul>
                        </nav>
                    </div>

                    <div class="bottom-note">© 2026 Bike Marketplace Admin Panel</div>
                </div>
            </div>
        </div>
    </main>


    <?php if (!empty($orderList)): ?>
        <?php foreach ($orderList as $order): ?>
            <?php
            $statusText = match ($order['status']) {
                'pending' => 'Chờ xác nhận',
                'confirmed' => 'Đã xác nhận',
                'shipping' => 'Đang giao dịch',
                'completed' => 'Hoàn tất',
                'cancelled' => 'Đã hủy',
                default => $order['status']
            };
            ?>
            <div class="modal fade" id="orderViewModal<?= (int)$order['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Chi tiết đơn mua</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <strong>Mã đơn:</strong>
                                    <div>DH<?= str_pad((string)$order['id'], 6, '0', STR_PAD_LEFT) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Trạng thái:</strong>
                                    <div><?= e($statusText) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Xe đạp:</strong>
                                    <div><?= e($order['bike_title'] ?? 'Không rõ') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Giá trị:</strong>
                                    <div><?= e(number_format((float)$order['total_amount'], 0, ',', '.')) ?>đ</div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Người mua:</strong>
                                    <div><?= e($order['buyer_name'] ?? 'Không rõ') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Email người mua:</strong>
                                    <div><?= e($order['buyer_email'] ?? '') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Số điện thoại:</strong>
                                    <div><?= e($order['shipping_phone'] ?: ($order['buyer_phone'] ?? '')) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Người bán:</strong>
                                    <div><?= e($order['seller_name'] ?? 'Không rõ') ?></div>
                                </div>
                                <div class="col-12">
                                    <strong>Người nhận:</strong>
                                    <div><?= e($order['shipping_name'] ?? '') ?></div>
                                </div>
                                <div class="col-12">
                                    <strong>Địa chỉ giao hàng:</strong>
                                    <div><?= e($order['shipping_address'] ?? '') ?></div>
                                </div>
                                <div class="col-12">
                                    <strong>Ghi chú:</strong>
                                    <div><?= nl2br(e($order['note'] ?? '')) ?></div>
                                </div>
                                <div class="col-12">
                                    <strong>Ngày tạo:</strong>
                                    <div><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">
                                Đóng
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>


    <?php if (!empty($orderList)): ?>
        <?php foreach ($orderList as $order): ?>
            <div class="modal fade" id="orderUpdateModal<?= (int)$order['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <div class="modal-header">
                                <h5 class="modal-title">Cập nhật trạng thái đơn</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                                <div class="mb-3">
                                    <label class="form-label">Mã đơn</label>
                                    <input type="text" class="form-control" value="DH<?= str_pad((string)$order['id'], 6, '0', STR_PAD_LEFT) ?>" readonly>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Trạng thái mới</label>
                                    <select name="new_status" class="form-select" required>
                                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
                                        <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
                                        <option value="shipping" <?= $order['status'] === 'shipping' ? 'selected' : '' ?>>Đang giao dịch</option>
                                        <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Hoàn tất</option>
                                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="update_order_status" class="btn btn-sm btn-success rounded-pill px-3">
                                    Lưu
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">
                                    Đóng
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>