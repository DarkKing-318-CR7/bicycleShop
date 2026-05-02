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
    $cancelReason = trim($_POST['cancel_reason'] ?? '');

    if ($orderId > 0) {
        if ($action === 'cancel') {
            $newStatus = 'cancelled';
        }

        if (in_array($newStatus, ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'], true)) {
            if ($newStatus === 'cancelled' && $cancelReason === '') {
                $message = 'Vui lòng nhập lý do hủy đơn.';
                $messageType = 'danger';
            } elseif (updateAdminOrderStatus($conn, $orderId, $newStatus, $cancelReason)) {
                header('Location: orders.php?msg=updated');
                exit;
            } else {
                $message = 'Không thể cập nhật đơn hàng. Đơn có thể đã hoàn tất hoặc đã hủy.';
                $messageType = 'danger';
            }
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

function paginationUrl(int $page): string
{
    $query = $_GET;
    $query['page'] = $page;

    return '?' . http_build_query($query);
}

function getOrderAmountColumn(mysqli $conn): string
{
    $columns = [];
    $result = $conn->query('SHOW COLUMNS FROM orders');

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $field = $row['Field'] ?? '';

            if ($field !== '') {
                $columns[] = $field;
            }
        }

        $result->free();
    }

    if (in_array('offered_price', $columns, true)) {
        return 'offered_price';
    }

    if (in_array('total_price', $columns, true)) {
        return 'total_price';
    }

    return 'total_amount';
}

function ensureOrderCancelReasonColumn(mysqli $conn): bool
{
    if (tableColumnExists($conn, 'orders', 'cancel_reason')) {
        return true;
    }

    $conn->query("ALTER TABLE orders ADD COLUMN cancel_reason text NULL AFTER buyer_note");

    return tableColumnExists($conn, 'orders', 'cancel_reason');
}

function updateAdminOrderStatus(mysqli $conn, int $orderId, string $newStatus, string $cancelReason = ''): bool
{
    if (!in_array($newStatus, ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'], true)) {
        return false;
    }

    if ($newStatus === 'cancelled' && trim($cancelReason) === '') {
        return false;
    }

    if ($newStatus === 'cancelled' && !ensureOrderCancelReasonColumn($conn)) {
        return false;
    }

    $conn->begin_transaction();

    if ($newStatus === 'cancelled') {
        $stmt = $conn->prepare("
            UPDATE orders
            SET status = ?, cancel_reason = ?, updated_at = NOW()
            WHERE id = ?
              AND status NOT IN ('completed', 'cancelled')
        ");
    } else {
        $stmt = $conn->prepare("
            UPDATE orders
            SET status = ?, updated_at = NOW()
            WHERE id = ?
              AND status NOT IN ('completed', 'cancelled')
        ");
    }

    if (!$stmt) {
        $conn->rollback();
        return false;
    }

    if ($newStatus === 'cancelled') {
        $stmt->bind_param("ssi", $newStatus, $cancelReason, $orderId);
    } else {
        $stmt->bind_param("si", $newStatus, $orderId);
    }
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$updated) {
        $conn->rollback();
        return false;
    }

    if ($newStatus === 'completed') {
        $bikeStmt = $conn->prepare("
            UPDATE bikes b
            INNER JOIN orders o ON o.bike_id = b.id
            SET b.status = 'sold', b.updated_at = NOW()
            WHERE o.id = ?
        ");

        if (!$bikeStmt) {
            $conn->rollback();
            return false;
        }

        $bikeStmt->bind_param("i", $orderId);
        $bikeStmt->execute();
        $bikeStmt->close();

        $cancelOtherStmt = $conn->prepare("
            UPDATE orders
            SET status = 'cancelled', updated_at = NOW()
            WHERE bike_id = (
                SELECT bike_id FROM (
                    SELECT bike_id FROM orders WHERE id = ?
                ) AS completed_order
            )
              AND id <> ?
              AND status IN ('pending', 'confirmed', 'in_progress')
        ");

        if (!$cancelOtherStmt) {
            $conn->rollback();
            return false;
        }

        $cancelOtherStmt->bind_param("ii", $orderId, $orderId);
        $cancelOtherStmt->execute();
        $cancelOtherStmt->close();
    }

    $conn->commit();
    return true;
}

$adminInitials = getInitials($adminName);
$amountColumn = getOrderAmountColumn($conn);
$hasCancelReasonColumn = ensureOrderCancelReasonColumn($conn);
$cancelReasonSelect = $hasCancelReasonColumn ? 'o.cancel_reason' : "''";

/**
 * THỐNG KÊ ĐƠN HÀNG
 */
$totalOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders");
$pendingOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'pending'");
$confirmedOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'confirmed'");
$shippingOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'in_progress'");
$completedOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'completed'");
$cancelledOrders = fetchCount($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'cancelled'");

/**
 * BỘ LỌC
 */
$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$sortFilter = trim($_GET['sort'] ?? 'latest');
$itemsPerPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

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
        o.order_code,
        o.{$amountColumn} AS total_amount,
        o.status,
        o.contact_method,
        o.meeting_location,
        o.buyer_note,
        {$cancelReasonSelect} AS cancel_reason,
        o.payment_method,
        o.payment_status,
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
$whereSql = '';

if ($keyword !== '') {
    $whereSql .= " AND (
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
    $whereSql .= " AND o.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($timeFilter === 'today') {
    $whereSql .= " AND DATE(o.created_at) = CURDATE()";
} elseif ($timeFilter === '7days') {
    $whereSql .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($timeFilter === '30days') {
    $whereSql .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$countSql = "
    SELECT COUNT(*) AS total
    FROM orders o
    LEFT JOIN bikes b ON o.bike_id = b.id
    LEFT JOIN users buyer ON o.buyer_id = buyer.id
    LEFT JOIN users seller ON b.seller_id = seller.id
    WHERE 1 = 1
    {$whereSql}
";

$filteredTotal = 0;
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }

    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult ? $countResult->fetch_assoc() : null;
    $filteredTotal = isset($countRow['total']) ? (int)$countRow['total'] : 0;
    $countStmt->close();
}

$totalPages = max(1, (int)ceil($filteredTotal / $itemsPerPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $itemsPerPage;

$sql .= $whereSql;

switch ($sortFilter) {
    case 'oldest':
        $sql .= " ORDER BY o.created_at ASC";
        break;
    case 'price_asc':
        $sql .= " ORDER BY o.{$amountColumn} ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY o.{$amountColumn} DESC";
        break;
    default:
        $sql .= " ORDER BY o.created_at DESC";
        break;
}

$sql .= " LIMIT ? OFFSET ?";

$orderList = [];
$stmt = $conn->prepare($sql);

if ($stmt) {
    $queryParams = $params;
    $queryTypes = $types . 'ii';
    $queryParams[] = $itemsPerPage;
    $queryParams[] = $offset;

    $stmt->bind_param($queryTypes, ...$queryParams);

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orderList[] = $row;
    }

    $stmt->close();
}

$firstItem = $filteredTotal > 0 ? $offset + 1 : 0;
$lastItem = min($offset + count($orderList), $filteredTotal);
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
                                            <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>Đang giao dịch</option>
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
                                Hiển thị <?= e((string)$firstItem) ?>-<?= e((string)$lastItem) ?> trong <?= e(number_format($filteredTotal, 0, ',', '.')) ?> đơn mua
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
                                                'in_progress' => 'Đang giao dịch',
                                                'completed' => 'Hoàn tất',
                                                'cancelled' => 'Đã hủy',
                                                default => $order['status']
                                            };

                                            $statusClass = match ($order['status']) {
                                                'pending' => 'status-pending',
                                                'confirmed' => 'status-approved',
                                                'in_progress' => 'status-pending',
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

                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-danger rounded-pill px-3"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#orderCancelModal<?= (int)$order['id'] ?>">
                                                                Hủy đơn
                                                            </button>
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
                                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $currentPage <= 1 ? '#' : e(paginationUrl($currentPage - 1)) ?>">Trước</a>
                                </li>
                                <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                    <li class="page-item <?= $page === $currentPage ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= e(paginationUrl($page)) ?>"><?= e((string)$page) ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $currentPage >= $totalPages ? '#' : e(paginationUrl($currentPage + 1)) ?>">Sau</a>
                                </li>
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
                'in_progress' => 'Đang giao dịch',
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
                                    <div><?= e($order['buyer_phone'] ?? '') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Người bán:</strong>
                                    <div><?= e($order['seller_name'] ?? 'Không rõ') ?></div>
                                </div>
                                <div class="col-12">
                                    <strong>Người nhận:</strong>
                                    <div><?= e($order['contact_method'] ?? '') ?></div>
                                </div>
                                <div class="col-12">
                                    <strong>Địa chỉ giao hàng:</strong>
                                    <div><?= e($order['meeting_location'] ?? '') ?></div>
                                </div>
                                <div class="col-12">
                                    <strong>Ghi chú:</strong>
                                    <div><?= nl2br(e($order['buyer_note'] ?? '')) ?></div>
                                </div>
                                <?php if (($order['status'] ?? '') === 'cancelled' && trim((string)($order['cancel_reason'] ?? '')) !== ''): ?>
                                    <div class="col-12">
                                        <strong>Lý do hủy:</strong>
                                        <div><?= nl2br(e($order['cancel_reason'])) ?></div>
                                    </div>
                                <?php endif; ?>
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
                                    <select name="new_status" class="form-select order-status-select" data-reason-target="#cancelReasonUpdate<?= (int)$order['id'] ?>" required>
                                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
                                        <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
                                        <option value="in_progress" <?= $order['status'] === 'in_progress' ? 'selected' : '' ?>>Đang giao dịch</option>
                                        <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Hoàn tất</option>
                                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                                    </select>
                                </div>
                                <div class="mb-3 d-none" id="cancelReasonUpdate<?= (int)$order['id'] ?>">
                                    <label class="form-label">Lý do hủy</label>
                                    <textarea name="cancel_reason" class="form-control" rows="3" placeholder="Nhập lý do hủy đơn để lưu lại cho việc đối soát."></textarea>
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

    <?php if (!empty($orderList)): ?>
        <?php foreach ($orderList as $order): ?>
            <?php if (($order['status'] ?? '') !== 'completed' && ($order['status'] ?? '') !== 'cancelled'): ?>
                <div class="modal fade" id="orderCancelModal<?= (int)$order['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="post">
                                <div class="modal-header">
                                    <h5 class="modal-title">Hủy đơn mua</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <input type="hidden" name="action_type" value="cancel">
                                    <input type="hidden" name="new_status" value="cancelled">

                                    <div class="mb-3">
                                        <label class="form-label">Mã đơn</label>
                                        <input type="text" class="form-control" value="DH<?= str_pad((string)$order['id'], 6, '0', STR_PAD_LEFT) ?>" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Lý do hủy <span class="text-danger">*</span></label>
                                        <textarea name="cancel_reason" class="form-control" rows="4" required placeholder="Ví dụ: Người mua yêu cầu hủy, không liên hệ được người mua, thông tin đơn không hợp lệ..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" name="update_order_status" class="btn btn-sm btn-danger rounded-pill px-3">
                                        Xác nhận hủy
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">
                                        Đóng
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        document.querySelectorAll('.order-status-select').forEach((select) => {
            const target = document.querySelector(select.dataset.reasonTarget || '');
            const textarea = target ? target.querySelector('textarea[name="cancel_reason"]') : null;

            const syncReasonField = () => {
                const shouldShow = select.value === 'cancelled';
                if (target) {
                    target.classList.toggle('d-none', !shouldShow);
                }
                if (textarea) {
                    textarea.required = shouldShow;
                }
            };

            select.addEventListener('change', syncReasonField);
            syncReasonField();
        });
    </script>
</body>

</html>
