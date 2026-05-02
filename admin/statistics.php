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

function tableExists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

function dateColumn(mysqli $conn, string $table): ?string
{
    foreach (['created_at', 'order_date', 'created_date'] as $candidate) {
        if (columnExists($conn, $table, $candidate)) {
            return $candidate;
        }
    }

    return null;
}

function bindParams(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || empty($params)) {
        return;
    }

    $refs = [$types];
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function fetchValuePrepared(mysqli $conn, string $sql, string $types = '', array $params = [], int|float $fallback = 0): int|float
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return $fallback;
    }

    bindParams($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $fallback;
    }

    $value = reset($row);

    return is_numeric($value) ? $value + 0 : $fallback;
}

function fetchRowsPrepared(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $rows = [];
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return $rows;
    }

    bindParams($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $stmt->close();

    return $rows;
}

function moneyVnd(int|float $amount): string
{
    return number_format((float)$amount, 0, ',', '.') . 'đ';
}

function orderStatusLabel(string $status): string
{
    return match ($status) {
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'in_progress', 'shipping' => 'Đang giao dịch',
        'completed' => 'Hoàn tất',
        'cancelled' => 'Đã hủy',
        default => $status,
    };
}

function rangeMeta(string $range): array
{
    $today = new DateTimeImmutable('today');

    return match ($range) {
        'today' => [
            'label' => 'hôm nay',
            'start' => $today->format('Y-m-d 00:00:00'),
            'end' => $today->modify('+1 day')->format('Y-m-d 00:00:00'),
        ],
        'week' => [
            'label' => 'tuần này',
            'start' => $today->modify('monday this week')->format('Y-m-d 00:00:00'),
            'end' => $today->modify('monday next week')->format('Y-m-d 00:00:00'),
        ],
        'year' => [
            'label' => 'năm nay',
            'start' => $today->format('Y-01-01 00:00:00'),
            'end' => $today->modify('first day of january next year')->format('Y-m-d 00:00:00'),
        ],
        'all' => [
            'label' => 'tất cả',
            'start' => null,
            'end' => null,
        ],
        default => [
            'label' => 'tháng này',
            'start' => $today->format('Y-m-01 00:00:00'),
            'end' => $today->modify('first day of next month')->format('Y-m-d 00:00:00'),
        ],
    };
}

function timeCondition(?string $column, string $alias, array $rangeMeta): array
{
    if ($column === null || $rangeMeta['start'] === null || $rangeMeta['end'] === null) {
        return ['', '', []];
    }

    return [" AND {$alias}.`{$column}` >= ? AND {$alias}.`{$column}` < ?", 'ss', [$rangeMeta['start'], $rangeMeta['end']]];
}

function chartBuckets(string $range): array
{
    $today = new DateTimeImmutable('today');
    $buckets = [];

    if ($range === 'today') {
        for ($hour = 0; $hour < 24; $hour++) {
            $key = str_pad((string)$hour, 2, '0', STR_PAD_LEFT);
            $buckets[$key] = ['label' => $key . 'h', 'total' => 0];
        }

        return $buckets;
    }

    if ($range === 'week') {
        $start = $today->modify('monday this week');
        for ($i = 0; $i < 7; $i++) {
            $date = $start->modify("+{$i} day");
            $buckets[$date->format('Y-m-d')] = ['label' => $date->format('d/m'), 'total' => 0];
        }

        return $buckets;
    }

    if ($range === 'year') {
        for ($month = 1; $month <= 12; $month++) {
            $key = $today->format('Y-') . str_pad((string)$month, 2, '0', STR_PAD_LEFT);
            $buckets[$key] = ['label' => 'T' . $month, 'total' => 0];
        }

        return $buckets;
    }

    if ($range === 'all') {
        return [];
    }

    $start = $today->modify('first day of this month');
    $lastDay = (int)$start->format('t');
    $weekCount = (int)ceil($lastDay / 7);

    for ($week = 1; $week <= $weekCount; $week++) {
        $fromDay = (($week - 1) * 7) + 1;
        $toDay = min($week * 7, $lastDay);
        $buckets[(string)$week] = [
            'label' => 'Tuần ' . $week . ' (' . str_pad((string)$fromDay, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$toDay, 2, '0', STR_PAD_LEFT) . ')',
            'total' => 0,
        ];
    }

    return $buckets;
}

$adminInitials = getInitials($adminName);
$allowedRanges = ['today', 'week', 'month', 'year', 'all'];
$range = $_GET['range'] ?? 'month';

if (!in_array($range, $allowedRanges, true)) {
    $range = 'month';
}

$rangeMeta = rangeMeta($range);
$hasOrders = tableExists($conn, 'orders');
$hasBikes = tableExists($conn, 'bikes');
$hasUsers = tableExists($conn, 'users');
$orderDateColumn = $hasOrders ? dateColumn($conn, 'orders') : null;
$bikeDateColumn = $hasBikes ? dateColumn($conn, 'bikes') : null;
$userDateColumn = $hasUsers ? dateColumn($conn, 'users') : null;
$hasOrderStatus = $hasOrders && columnExists($conn, 'orders', 'status');

$valueColumn = null;
if ($hasOrders) {
    foreach (['total_price', 'total_amount', 'offered_price', 'price', 'amount'] as $candidate) {
        if (columnExists($conn, 'orders', $candidate)) {
            $valueColumn = $candidate;
            break;
        }
    }
}

$quantityExpression = ($hasOrders && columnExists($conn, 'orders', 'quantity')) ? 'COALESCE(o.quantity, 1)' : '1';
[$orderTimeSql, $orderTimeTypes, $orderTimeParams] = timeCondition($orderDateColumn, 'o', $rangeMeta);
[$bikeTimeSql, $bikeTimeTypes, $bikeTimeParams] = timeCondition($bikeDateColumn, 'b', $rangeMeta);
[$userTimeSql, $userTimeTypes, $userTimeParams] = timeCondition($userDateColumn, 'u', $rangeMeta);

$transactionValue = 0;
$approvedBikes = 0;
$primaryMetricLabel = 'Tin đã duyệt';
$primaryMetricValue = '0';
$primaryMetricIcon = 'bi-patch-check';

if ($hasOrders && $valueColumn !== null && $hasOrderStatus) {
    $transactionValue = fetchValuePrepared(
        $conn,
        "SELECT COALESCE(SUM(o.`{$valueColumn}` * {$quantityExpression}), 0) FROM orders o WHERE o.status = 'completed' {$orderTimeSql}",
        $orderTimeTypes,
        $orderTimeParams
    );
    $primaryMetricLabel = 'Giá trị giao dịch';
    $primaryMetricValue = moneyVnd($transactionValue);
    $primaryMetricIcon = 'bi-cash-coin';
} elseif ($hasBikes && columnExists($conn, 'bikes', 'status')) {
    $approvedBikes = fetchValuePrepared(
        $conn,
        "SELECT COUNT(*) FROM bikes b WHERE b.status = 'approved' {$bikeTimeSql}",
        $bikeTimeTypes,
        $bikeTimeParams
    );
    $primaryMetricValue = number_format((int)$approvedBikes, 0, ',', '.');
}

$ordersChartType = 'line';
$totalOrders = $hasOrders
    ? fetchValuePrepared($conn, "SELECT COUNT(*) FROM orders o WHERE 1 = 1 {$orderTimeSql}", $orderTimeTypes, $orderTimeParams)
    : 0;
$totalUsers = $hasUsers
    ? fetchValuePrepared($conn, "SELECT COUNT(*) FROM users u WHERE 1 = 1 {$userTimeSql}", $userTimeTypes, $userTimeParams)
    : 0;
$totalBikes = $hasBikes
    ? fetchValuePrepared($conn, "SELECT COUNT(*) FROM bikes b WHERE 1 = 1 {$bikeTimeSql}", $bikeTimeTypes, $bikeTimeParams)
    : 0;
$pendingBikes = ($hasBikes && columnExists($conn, 'bikes', 'status'))
    ? fetchValuePrepared($conn, "SELECT COUNT(*) FROM bikes b WHERE b.status = 'pending' {$bikeTimeSql}", $bikeTimeTypes, $bikeTimeParams)
    : 0;
$rejectedBikes = ($hasBikes && columnExists($conn, 'bikes', 'status'))
    ? fetchValuePrepared($conn, "SELECT COUNT(*) FROM bikes b WHERE b.status = 'rejected' {$bikeTimeSql}", $bikeTimeTypes, $bikeTimeParams)
    : 0;

$orderDateLabels = [];
$orderDateValues = [];
$orderDateDetails = [];
$buckets = chartBuckets($range);

if ($hasOrders && $orderDateColumn !== null) {
    if ($range === 'today') {
        $groupSelect = "DATE_FORMAT(o.`{$orderDateColumn}`, '%H')";
        $orderBy = 'bucket ASC';
    } elseif ($range === 'year') {
        $groupSelect = "DATE_FORMAT(o.`{$orderDateColumn}`, '%Y-%m')";
        $orderBy = 'bucket ASC';
    } elseif ($range === 'all') {
        $groupSelect = "DATE_FORMAT(o.`{$orderDateColumn}`, '%Y')";
        $orderBy = 'bucket ASC';
    } elseif ($range === 'month') {
        $groupSelect = "CAST(FLOOR((DAYOFMONTH(o.`{$orderDateColumn}`) - 1) / 7) + 1 AS CHAR)";
        $orderBy = 'bucket ASC';
    } else {
        $groupSelect = "DATE(o.`{$orderDateColumn}`)";
        $orderBy = 'bucket ASC';
    }

    $rows = fetchRowsPrepared(
        $conn,
        "SELECT {$groupSelect} AS bucket, COUNT(*) AS total
         FROM orders o
         WHERE 1 = 1 {$orderTimeSql}
         GROUP BY bucket
         ORDER BY {$orderBy}",
        $orderTimeTypes,
        $orderTimeParams
    );

    if ($range === 'all') {
        foreach ($rows as $row) {
            $year = (string)$row['bucket'];
            $buckets[$year] = [
                'label' => $year,
                'total' => (int)$row['total'],
            ];
        }

        if (count($buckets) === 1) {
            $onlyYear = array_key_first($buckets);
            $onlyTotal = $buckets[$onlyYear]['total'];

            $buckets = [
                (string)((int)$onlyYear - 1) => [
                    'label' => (string)((int)$onlyYear - 1),
                    'total' => 0,
                ],
                $onlyYear => [
                    'label' => $onlyYear,
                    'total' => $onlyTotal,
                ],
            ];
        }
    } else {
        foreach ($rows as $row) {
            $bucket = (string)$row['bucket'];
            if (isset($buckets[$bucket])) {
                $buckets[$bucket]['total'] = (int)$row['total'];
            }
        }
    }
}

foreach ($buckets as $key => $bucket) {
    $orderDateLabels[] = $bucket['label'];
    $orderDateValues[] = (int)$bucket['total'];
    $orderDateDetails[] = [
        'date' => $bucket['label'],
        'total' => (int)$bucket['total'],
    ];
}

$orderStatusLabels = [];
$orderStatusValues = [];
$orderStatusDetails = [];
if ($hasOrderStatus) {
    $rows = fetchRowsPrepared(
        $conn,
        "SELECT o.status, COUNT(*) AS total
         FROM orders o
         WHERE 1 = 1 {$orderTimeSql}
         GROUP BY o.status
         ORDER BY total DESC",
        $orderTimeTypes,
        $orderTimeParams
    );

    foreach ($rows as $row) {
        $statusLabel = orderStatusLabel((string)$row['status']);
        $orderStatusLabels[] = $statusLabel;
        $orderStatusValues[] = (int)$row['total'];
        $orderStatusDetails[] = [
            'status' => $statusLabel,
            'total' => (int)$row['total'],
        ];
    }
}

$topBikeLabels = [];
$topBikeValues = [];
$topBikeDetails = [];
if ($hasOrders && $hasBikes && columnExists($conn, 'orders', 'bike_id')) {
    $completedOrderSql = $hasOrderStatus ? " AND o.status = 'completed'" : '';
    $soldQuantityExpression = columnExists($conn, 'orders', 'quantity') ? 'COALESCE(o.quantity, 1)' : '1';
    $rows = fetchRowsPrepared(
        $conn,
        "SELECT o.bike_id, COALESCE(b.title, CONCAT('Xe #', o.bike_id)) AS bike_title, SUM({$soldQuantityExpression}) AS total
         FROM orders o
         LEFT JOIN bikes b ON o.bike_id = b.id
         WHERE o.bike_id IS NOT NULL {$completedOrderSql} {$orderTimeSql}
         GROUP BY o.bike_id, b.title
         ORDER BY total DESC
         LIMIT 5",
        $orderTimeTypes,
        $orderTimeParams
    );

    foreach ($rows as $index => $row) {
        $topBikeLabels[] = (string)$row['bike_title'];
        $topBikeValues[] = (int)$row['total'];
        $topBikeDetails[] = [
            'rank' => $index + 1,
            'bike_id' => (int)$row['bike_id'],
            'bike_title' => (string)$row['bike_title'],
            'total' => (int)$row['total'],
        ];
    }
}

$topSellerLabels = [];
$topSellerValues = [];
$topSellerDetails = [];
if ($hasBikes && columnExists($conn, 'bikes', 'seller_id')) {
    $joinUsers = $hasUsers ? 'LEFT JOIN users u ON b.seller_id = u.id' : '';
    $sellerName = $hasUsers ? "COALESCE(u.full_name, CONCAT('Seller #', b.seller_id))" : "CONCAT('Seller #', b.seller_id)";
    $rows = fetchRowsPrepared(
        $conn,
        "SELECT b.seller_id, {$sellerName} AS seller_name, COUNT(*) AS total
         FROM bikes b
         {$joinUsers}
         WHERE b.seller_id IS NOT NULL {$bikeTimeSql}
         GROUP BY b.seller_id" . ($hasUsers ? ', u.full_name' : '') . "
         ORDER BY total DESC
         LIMIT 5",
        $bikeTimeTypes,
        $bikeTimeParams
    );

    foreach ($rows as $index => $row) {
        $topSellerLabels[] = (string)$row['seller_name'];
        $topSellerValues[] = (int)$row['total'];
        $topSellerDetails[] = [
            'rank' => $index + 1,
            'seller_id' => (int)$row['seller_id'],
            'seller_name' => (string)$row['seller_name'],
            'total' => (int)$row['total'],
        ];
    }
}

$hasMissingCoreData = !$hasOrders || !$hasBikes || !$hasUsers;
$rangeOptions = [
    'today' => 'Hôm nay',
    'week' => 'Tuần này',
    'month' => 'Tháng này',
    'year' => 'Năm nay',
    'all' => 'Tất cả',
];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace Admin | Thống kê hệ thống</title>
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
                    <input type="text" class="form-control admin-search" style="max-width: 320px;" placeholder="Tìm kiếm thống kê, đơn hàng, tin đăng">
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
                            <li><a class="menu-link" href="orders.php"><i class="bi bi-receipt"></i> Quản lý đơn mua</a></li>
                            <li><a class="menu-link" href="categories.php"><i class="bi bi-tags"></i> Danh mục xe</a></li>
                            <li><a class="menu-link" href="brands.php"><i class="bi bi-award"></i> Thương hiệu</a></li>
                            <li><a class="menu-link" href="moderation.php"><i class="bi bi-shield-check"></i> Kiểm duyệt</a></li>
                            <li><a class="menu-link active" href="analytics.php"><i class="bi bi-bar-chart"></i> Thống kê</a></li>
                            <li><a class="menu-link" href="settings.php"><i class="bi bi-gear"></i> Cài đặt</a></li>
                            <li><a class="menu-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a></li>
                        </ul>
                    </div>
                </aside>

                <div class="col-xl-10 col-lg-9">
                    <div class="page-breadcrumb">Admin / Thống kê</div>
                    <div class="page-kicker">Thống kê</div>
                    <div class="d-flex flex-column flex-xl-row align-items-xl-end justify-content-between gap-3 mb-4">
                        <div>
                            <h1 class="section-title mb-2">Thống kê hệ thống</h1>
                            <p class="section-subtitle mb-0">Thống kê <?= e($rangeMeta['label']) ?> về đơn hàng, tin đăng và hoạt động người bán trên marketplace.</p>
                        </div>
                        <form method="get" action="analytics.php" class="d-flex flex-wrap gap-2 align-items-center">
                            <label class="text-muted small" for="range">Khoảng thời gian</label>
                            <select id="range" name="range" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($rangeOptions as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $range === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <?php if ($hasMissingCoreData): ?>
                        <div class="alert alert-warning">
                            Một số bảng thống kê chưa có trong database, các biểu đồ liên quan sẽ hiển thị rỗng.
                        </div>
                    <?php endif; ?>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi <?= e($primaryMetricIcon) ?>"></i></span>
                                <div><small><?= e($primaryMetricLabel) ?></small><strong><?= e($primaryMetricValue) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-receipt"></i></span>
                                <div><small>Tổng đơn hàng</small><strong><?= e(number_format((int)$totalOrders, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-people"></i></span>
                                <div><small>Tổng người dùng mới</small><strong><?= e(number_format((int)$totalUsers, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-card-list"></i></span>
                                <div><small>Tổng tin đăng</small><strong><?= e(number_format((int)$totalBikes, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-hourglass-split"></i></span>
                                <div><small>Tin chờ duyệt</small><strong><?= e(number_format((int)$pendingBikes, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-x-circle"></i></span>
                                <div><small>Tin bị từ chối</small><strong><?= e(number_format((int)$rejectedBikes, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-xl-8">
                            <div class="chart-card">
                                <h2 class="section-heading">Đơn hàng theo thời gian</h2>
                                <?php if (array_sum($orderDateValues) > 0): ?>
                                    <div class="chart-placeholder"><canvas id="ordersByDayChart"></canvas></div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">Chưa có dữ liệu thống kê</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="chart-card">
                                <h2 class="section-heading">Trạng thái đơn hàng</h2>
                                <?php if (array_sum($orderStatusValues) > 0): ?>
                                    <div class="chart-placeholder"><canvas id="orderStatusChart"></canvas></div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">Chưa có dữ liệu thống kê</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mt-1">
                        <div class="col-xl-6">
                            <div class="chart-card">
                                <h2 class="section-heading">Top 5 xe giao dịch thành công</h2>
                                <?php if (array_sum($topBikeValues) > 0): ?>
                                    <div class="chart-placeholder"><canvas id="topBikesChart"></canvas></div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">Chưa có dữ liệu thống kê</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-xl-6">
                            <div class="chart-card">
                                <h2 class="section-heading">Top seller đăng nhiều tin</h2>
                                <?php if (array_sum($topSellerValues) > 0): ?>
                                    <div class="chart-placeholder"><canvas id="topSellersChart"></canvas></div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">Chưa có dữ liệu thống kê</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="bottom-note">© 2026 Bike Marketplace Admin Panel</div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="chartDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="chartDetailTitle">Chi tiết thống kê</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead id="chartDetailHead"></thead>
                            <tbody id="chartDetailBody"></tbody>
                        </table>
                    </div>
                    <div class="text-center text-muted py-4 d-none" id="chartDetailEmpty">Chưa có dữ liệu thống kê</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ordersByDayLabels = <?= json_encode($orderDateLabels, JSON_UNESCAPED_UNICODE) ?>;
        const ordersByDayValues = <?= json_encode($orderDateValues, JSON_UNESCAPED_UNICODE) ?>;
        const ordersChartType = <?= json_encode($ordersChartType, JSON_UNESCAPED_UNICODE) ?>;
        const orderStatusLabels = <?= json_encode($orderStatusLabels, JSON_UNESCAPED_UNICODE) ?>;
        const orderStatusValues = <?= json_encode($orderStatusValues, JSON_UNESCAPED_UNICODE) ?>;
        const topBikeLabels = <?= json_encode($topBikeLabels, JSON_UNESCAPED_UNICODE) ?>;
        const topBikeValues = <?= json_encode($topBikeValues, JSON_UNESCAPED_UNICODE) ?>;
        const topSellerLabels = <?= json_encode($topSellerLabels, JSON_UNESCAPED_UNICODE) ?>;
        const topSellerValues = <?= json_encode($topSellerValues, JSON_UNESCAPED_UNICODE) ?>;
        const chartDetailData = {
            ordersByDayChart: {
                title: 'Bảng đơn hàng theo thời gian',
                columns: ['Mốc thời gian', 'Số đơn hàng'],
                rows: <?= json_encode(array_map(fn($row) => [$row['date'], number_format($row['total'], 0, ',', '.')], $orderDateDetails), JSON_UNESCAPED_UNICODE) ?>
            },
            orderStatusChart: {
                title: 'Bảng trạng thái đơn hàng',
                columns: ['Trạng thái', 'Số đơn hàng'],
                rows: <?= json_encode(array_map(fn($row) => [$row['status'], number_format($row['total'], 0, ',', '.')], $orderStatusDetails), JSON_UNESCAPED_UNICODE) ?>
            },
            topBikesChart: {
                title: 'Bảng xếp hạng Top 5 xe giao dịch thành công',
                columns: ['Hạng', 'Mã xe', 'Tên xe', 'Số lượng hoàn tất'],
                rows: <?= json_encode(array_map(fn($row) => [$row['rank'], '#' . $row['bike_id'], $row['bike_title'], number_format($row['total'], 0, ',', '.')], $topBikeDetails), JSON_UNESCAPED_UNICODE) ?>
            },
            topSellersChart: {
                title: 'Bảng xếp hạng seller đăng nhiều tin',
                columns: ['Hạng', 'Mã seller', 'Người bán', 'Số tin đăng'],
                rows: <?= json_encode(array_map(fn($row) => [$row['rank'], '#' . $row['seller_id'], $row['seller_name'], number_format($row['total'], 0, ',', '.')], $topSellerDetails), JSON_UNESCAPED_UNICODE) ?>
            }
        };

        const chartColors = ['#198754', '#0d6efd', '#ffc107', '#dc3545', '#6f42c1', '#20c997'];
        const detailModalElement = document.getElementById('chartDetailModal');
        const detailModal = detailModalElement ? new bootstrap.Modal(detailModalElement) : null;

        function renderChart(id, config) {
            const canvas = document.getElementById(id);
            if (!canvas) {
                return null;
            }

            canvas.style.cursor = 'pointer';
            const chart = new Chart(canvas, {
                ...config,
                options: config.options
            });
            canvas.addEventListener('click', () => openChartDetail(id));
            return chart;
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [char]));
        }

        function openChartDetail(chartId) {
            const detail = chartDetailData[chartId];
            if (!detail || !detailModal) {
                return;
            }

            document.getElementById('chartDetailTitle').textContent = detail.title;
            const head = document.getElementById('chartDetailHead');
            const body = document.getElementById('chartDetailBody');
            const empty = document.getElementById('chartDetailEmpty');
            const hasRows = Array.isArray(detail.rows) && detail.rows.length > 0;

            head.innerHTML = hasRows ?
                `<tr>${detail.columns.map((column) => `<th>${escapeHtml(column)}</th>`).join('')}</tr>` :
                '';
            body.innerHTML = hasRows ?
                detail.rows.map((row) => `<tr>${row.map((cell) => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`).join('') :
                '';

            empty.classList.toggle('d-none', hasRows);
            detailModal.show();
        }

        renderChart('ordersByDayChart', {
            type: ordersChartType,
            data: {
                labels: ordersByDayLabels,
                datasets: [{
                    label: 'Số đơn hàng',
                    data: ordersByDayValues,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                }]
            },
            // options: { responsive: true, maintainAspectRatio: false }
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            stepSize: 1,
                            callback: function(value) {
                                return Number.isInteger(value) ? value : null;
                            }
                        }
                    }
                },
                elements: {
                    line: {
                        tension: 0.35
                    },
                    point: {
                        radius: 4
                    }
                }
            }
        });

        renderChart('orderStatusChart', {
            type: 'doughnut',
            data: {
                labels: orderStatusLabels,
                datasets: [{
                    data: orderStatusValues,
                    backgroundColor: chartColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        renderChart('topBikesChart', {
            type: 'bar',
            data: {
                labels: topBikeLabels,
                datasets: [{
                    label: 'Số lượng hoàn tất',
                    data: topBikeValues,
                    backgroundColor: '#198754'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        renderChart('topSellersChart', {
            type: 'bar',
            data: {
                labels: topSellerLabels,
                datasets: [{
                    label: 'Số tin đăng',
                    data: topSellerValues,
                    backgroundColor: '#0d6efd'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
</body>

</html>