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
    try {
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

        $stmt->bind_param("s", $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $exists = isset($row['total']) && (int) $row['total'] > 0;
        $stmt->close();

        return $exists;
    } catch (Throwable $error) {
        return false;
    }
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    try {
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

        $stmt->bind_param("ss", $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $exists = isset($row['total']) && (int) $row['total'] > 0;
        $stmt->close();

        return $exists;
    } catch (Throwable $error) {
        return false;
    }
}

function fetchValue(mysqli $conn, string $sql, int|float $fallback = 0): int|float
{
    try {
        $result = $conn->query($sql);
        if (!$result) {
            return $fallback;
        }

        $row = $result->fetch_assoc();
        if (!$row) {
            return $fallback;
        }

        $value = reset($row);

        return is_numeric($value) ? $value + 0 : $fallback;
    } catch (Throwable $error) {
        return $fallback;
    }
}

function fetchRows(mysqli $conn, string $sql): array
{
    $rows = [];

    try {
        $result = $conn->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    } catch (Throwable $error) {
        return [];
    }

    return $rows;
}

function moneyVnd(int|float $amount): string
{
    return number_format((float) $amount, 0, ',', '.') . 'đ';
}

function orderStatusLabel(string $status): string
{
    return match ($status) {
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'shipping' => 'Đang giao dịch',
        'completed' => 'Hoàn tất',
        'cancelled' => 'Đã hủy',
        default => $status,
    };
}

$adminInitials = getInitials($adminName);

$hasOrders = tableExists($conn, 'orders');
$hasBikes = tableExists($conn, 'bikes');
$hasUsers = tableExists($conn, 'users');

$revenueColumn = null;
if ($hasOrders) {
    foreach (['total_price', 'total_amount', 'price', 'amount'] as $candidate) {
        if (columnExists($conn, 'orders', $candidate)) {
            $revenueColumn = $candidate;
            break;
        }
    }
}

$totalRevenue = ($hasOrders && $revenueColumn !== null && columnExists($conn, 'orders', 'status'))
    ? fetchValue($conn, "SELECT COALESCE(SUM(`$revenueColumn`), 0) FROM orders WHERE status = 'completed'")
    : 0;

$totalOrders = $hasOrders ? fetchValue($conn, "SELECT COUNT(*) FROM orders") : 0;
$totalUsers = $hasUsers ? fetchValue($conn, "SELECT COUNT(*) FROM users") : 0;
$totalBikes = $hasBikes ? fetchValue($conn, "SELECT COUNT(*) FROM bikes") : 0;
$pendingBikes = ($hasBikes && columnExists($conn, 'bikes', 'status')) ? fetchValue($conn, "SELECT COUNT(*) FROM bikes WHERE status = 'pending'") : 0;
$rejectedBikes = ($hasBikes && columnExists($conn, 'bikes', 'status')) ? fetchValue($conn, "SELECT COUNT(*) FROM bikes WHERE status = 'rejected'") : 0;

$orderDateLabels = [];
$orderDateValues = [];
$orderDateMap = [];
$orderDateDetails = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $orderDateMap[$date] = 0;
}

if ($hasOrders && columnExists($conn, 'orders', 'created_at')) {
    $rows = fetchRows($conn, "
        SELECT DATE(created_at) AS order_date, COUNT(*) AS total
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY order_date ASC
    ");

    foreach ($rows as $row) {
        $orderDateMap[$row['order_date']] = (int) $row['total'];
    }
}

foreach ($orderDateMap as $date => $total) {
    $orderDateLabels[] = date('d/m', strtotime($date));
    $orderDateValues[] = $total;
    $orderDateDetails[] = [
        'date' => date('d/m/Y', strtotime($date)),
        'total' => (int) $total,
    ];
}

$orderStatusLabels = [];
$orderStatusValues = [];
$orderStatusDetails = [];
if ($hasOrders && columnExists($conn, 'orders', 'status')) {
    $rows = fetchRows($conn, "
        SELECT status, COUNT(*) AS total
        FROM orders
        GROUP BY status
        ORDER BY total DESC
    ");

    foreach ($rows as $row) {
        $statusLabel = orderStatusLabel((string) $row['status']);
        $orderStatusLabels[] = $statusLabel;
        $orderStatusValues[] = (int) $row['total'];
        $orderStatusDetails[] = [
            'status' => $statusLabel,
            'total' => (int) $row['total'],
        ];
    }
}

$topBikeLabels = [];
$topBikeValues = [];
$topBikeDetails = [];
if ($hasOrders && $hasBikes && columnExists($conn, 'orders', 'bike_id')) {
    $rows = fetchRows($conn, "
        SELECT o.bike_id, COALESCE(b.title, CONCAT('Xe #', o.bike_id)) AS bike_title, COUNT(*) AS total
        FROM orders o
        LEFT JOIN bikes b ON o.bike_id = b.id
        WHERE o.bike_id IS NOT NULL
        GROUP BY o.bike_id, b.title
        ORDER BY total DESC
        LIMIT 5
    ");

    foreach ($rows as $index => $row) {
        $topBikeLabels[] = (string) $row['bike_title'];
        $topBikeValues[] = (int) $row['total'];
        $topBikeDetails[] = [
            'rank' => $index + 1,
            'bike_id' => (int) $row['bike_id'],
            'bike_title' => (string) $row['bike_title'],
            'total' => (int) $row['total'],
        ];
    }
}

$topSellerLabels = [];
$topSellerValues = [];
$topSellerDetails = [];
if ($hasBikes && columnExists($conn, 'bikes', 'seller_id')) {
    $joinUsers = $hasUsers ? "LEFT JOIN users u ON b.seller_id = u.id" : "";
    $sellerName = $hasUsers ? "COALESCE(u.full_name, CONCAT('Seller #', b.seller_id))" : "CONCAT('Seller #', b.seller_id)";
    $rows = fetchRows($conn, "
        SELECT b.seller_id, $sellerName AS seller_name, COUNT(*) AS total
        FROM bikes b
        $joinUsers
        WHERE b.seller_id IS NOT NULL
        GROUP BY b.seller_id" . ($hasUsers ? ", u.full_name" : "") . "
        ORDER BY total DESC
        LIMIT 5
    ");

    foreach ($rows as $index => $row) {
        $topSellerLabels[] = (string) $row['seller_name'];
        $topSellerValues[] = (int) $row['total'];
        $topSellerDetails[] = [
            'rank' => $index + 1,
            'seller_id' => (int) $row['seller_id'],
            'seller_name' => (string) $row['seller_name'],
            'total' => (int) $row['total'],
        ];
    }
}

$hasMissingCoreData = !$hasOrders || !$hasBikes || !$hasUsers || $revenueColumn === null;
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
                            <li><a class="menu-link active" href="statistics.php"><i class="bi bi-bar-chart"></i> Thống kê</a></li>
                            <li><a class="menu-link" href="settings.php"><i class="bi bi-gear"></i> Cài đặt</a></li>
                            <li><a class="menu-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a></li>
                        </ul>
                    </div>
                </aside>

                <div class="col-xl-10 col-lg-9">
                    <div class="page-breadcrumb">Admin / Thống kê</div>
                    <div class="page-kicker">Thống kê</div>
                    <h1 class="section-title mb-2">Thống kê hệ thống</h1>
                    <p class="section-subtitle mb-4">Theo dõi doanh thu, đơn hàng, tin đăng và hoạt động người bán trên marketplace.</p>

                    <?php if ($hasMissingCoreData): ?>
                        <div class="alert alert-warning">
                            Một số bảng hoặc cột thống kê chưa có đủ trong database, các biểu đồ liên quan sẽ hiển thị rỗng.
                        </div>
                    <?php endif; ?>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-cash-coin"></i></span>
                                <div><small>Tổng doanh thu</small><strong><?= e(moneyVnd($totalRevenue)) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-receipt"></i></span>
                                <div><small>Tổng đơn hàng</small><strong><?= e(number_format((int) $totalOrders, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-people"></i></span>
                                <div><small>Tổng người dùng</small><strong><?= e(number_format((int) $totalUsers, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-card-list"></i></span>
                                <div><small>Tổng tin đăng</small><strong><?= e(number_format((int) $totalBikes, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-hourglass-split"></i></span>
                                <div><small>Tin chờ duyệt</small><strong><?= e(number_format((int) $pendingBikes, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4 col-xxl-2">
                            <div class="stats-card"><span class="stats-icon"><i class="bi bi-x-circle"></i></span>
                                <div><small>Tin bị từ chối</small><strong><?= e(number_format((int) $rejectedBikes, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-xl-8">
                            <div class="chart-card">
                                <h2 class="section-heading">Đơn hàng theo ngày</h2>
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
                                <h2 class="section-heading">Top 5 xe bán chạy</h2>
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
        const orderStatusLabels = <?= json_encode($orderStatusLabels, JSON_UNESCAPED_UNICODE) ?>;
        const orderStatusValues = <?= json_encode($orderStatusValues, JSON_UNESCAPED_UNICODE) ?>;
        const topBikeLabels = <?= json_encode($topBikeLabels, JSON_UNESCAPED_UNICODE) ?>;
        const topBikeValues = <?= json_encode($topBikeValues, JSON_UNESCAPED_UNICODE) ?>;
        const topSellerLabels = <?= json_encode($topSellerLabels, JSON_UNESCAPED_UNICODE) ?>;
        const topSellerValues = <?= json_encode($topSellerValues, JSON_UNESCAPED_UNICODE) ?>;
        const chartDetailData = {
            ordersByDayChart: {
                title: 'Bảng đơn hàng theo ngày',
                columns: ['Ngày', 'Số đơn hàng'],
                rows: <?= json_encode(array_map(fn($row) => [$row['date'], number_format($row['total'], 0, ',', '.')], $orderDateDetails), JSON_UNESCAPED_UNICODE) ?>
            },
            orderStatusChart: {
                title: 'Bảng trạng thái đơn hàng',
                columns: ['Trạng thái', 'Số đơn hàng'],
                rows: <?= json_encode(array_map(fn($row) => [$row['status'], number_format($row['total'], 0, ',', '.')], $orderStatusDetails), JSON_UNESCAPED_UNICODE) ?>
            },
            topBikesChart: {
                title: 'Bảng xếp hạng Top 5 xe bán chạy',
                columns: ['Hạng', 'Mã xe', 'Tên xe', 'Số đơn'],
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
            if (canvas) {
                canvas.style.cursor = 'pointer';
                const chart = new Chart(canvas, {
                    ...config,
                    options: config.options
                });
                canvas.addEventListener('click', () => openChartDetail(id));
                return chart;
            }
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
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

            head.innerHTML = hasRows
                ? `<tr>${detail.columns.map((column) => `<th>${escapeHtml(column)}</th>`).join('')}</tr>`
                : '';
            body.innerHTML = hasRows
                ? detail.rows.map((row) => `<tr>${row.map((cell) => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`).join('')
                : '';

            empty.classList.toggle('d-none', hasRows);
            detailModal.show();
        }

        renderChart('ordersByDayChart', {
            type: 'line',
            data: {
                labels: ordersByDayLabels,
                datasets: [{
                    label: 'Số đơn hàng',
                    data: ordersByDayValues,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.12)',
                    fill: true,
                    tension: 0.35
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        renderChart('orderStatusChart', {
            type: 'doughnut',
            data: {
                labels: orderStatusLabels,
                datasets: [{ data: orderStatusValues, backgroundColor: chartColors }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        renderChart('topBikesChart', {
            type: 'bar',
            data: {
                labels: topBikeLabels,
                datasets: [{ label: 'Số đơn', data: topBikeValues, backgroundColor: '#198754' }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        renderChart('topSellersChart', {
            type: 'bar',
            data: {
                labels: topSellerLabels,
                datasets: [{ label: 'Số tin đăng', data: topSellerValues, backgroundColor: '#0d6efd' }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    </script>
</body>

</html>
