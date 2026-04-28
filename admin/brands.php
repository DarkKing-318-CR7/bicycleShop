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

function fetchCount(mysqli $conn, string $sql, int $fallback = 0): int
{
    $result = $conn->query($sql);

    if (!$result) {
        return $fallback;
    }

    $row = $result->fetch_assoc();

    return isset($row['total']) ? (int) $row['total'] : $fallback;
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

        $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return strtoupper($initials ?: 'AD');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_brand'])) {
    $brandName = trim($_POST['name'] ?? '');

    if ($brandName === '') {
        $message = 'Vui lòng nhập tên thương hiệu.';
        $messageType = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO brands (name) VALUES (?)");

        if ($stmt) {
            $stmt->bind_param("s", $brandName);

            if ($stmt->execute()) {
                header('Location: brands.php?msg=created');
                exit;
            }

            $message = 'Không thể thêm thương hiệu. Tên thương hiệu có thể đã tồn tại.';
            $messageType = 'danger';
            $stmt->close();
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
    $message = 'Đã thêm thương hiệu mới thành công.';
}

$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$sortFilter = trim($_GET['sort'] ?? 'latest');

$totalBrands = fetchCount($conn, "SELECT COUNT(*) AS total FROM brands");
$activeBrands = fetchCount($conn, "SELECT COUNT(*) AS total FROM (SELECT br.id FROM brands br INNER JOIN bikes b ON b.brand_id = br.id GROUP BY br.id) active_brands");
$popularBrands = fetchCount($conn, "SELECT COUNT(*) AS total FROM (SELECT br.id FROM brands br INNER JOIN bikes b ON b.brand_id = br.id GROUP BY br.id ORDER BY COUNT(b.id) DESC LIMIT 4) popular_brands");
$brandedBikes = fetchCount($conn, "SELECT COUNT(*) AS total FROM bikes WHERE brand_id IS NOT NULL");

$sql = "SELECT br.id, br.name, br.created_at, COUNT(b.id) AS bike_count FROM brands br LEFT JOIN bikes b ON b.brand_id = br.id WHERE 1 = 1";
$params = [];
$types = '';

if ($keyword !== '') {
    $sql .= " AND br.name LIKE ?";
    $keywordLike = '%' . $keyword . '%';
    $params[] = $keywordLike;
    $types .= 's';
}

$sql .= " GROUP BY br.id, br.name, br.created_at";

if ($statusFilter === 'active') {
    $sql .= " HAVING bike_count > 0";
} elseif ($statusFilter === 'hidden') {
    $sql .= " HAVING bike_count = 0";
}

switch ($sortFilter) {
    case 'oldest':
        $sql .= " ORDER BY br.created_at ASC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY br.name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY br.name DESC";
        break;
    case 'posts_desc':
        $sql .= " ORDER BY bike_count DESC, br.name ASC";
        break;
    default:
        $sql .= " ORDER BY br.created_at DESC";
        break;
}

$brandList = [];
$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $brandList[] = $row;
    }

    $stmt->close();
}

$adminInitials = getInitials($adminName);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace Admin | Quản lý thương hiệu xe</title>
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
                    <input type="text" class="form-control admin-search" style="max-width: 320px;" placeholder="Tìm kiếm thương hiệu, trạng thái">
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
                            <li><a class="menu-link active" href="brands.php"><i class="bi bi-award"></i> Thương hiệu</a></li>
                            <li><a class="menu-link" href="moderation.php"><i class="bi bi-shield-check"></i> Kiểm duyệt</a></li>
                            <li><a class="menu-link" href="statistics.php"><i class="bi bi-bar-chart"></i> Thống kê</a></li>
                            <li><a class="menu-link" href="settings.php"><i class="bi bi-gear"></i> Cài đặt</a></li>
                            <li><a class="menu-link" href="../login.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a></li>
                        </ul>
                    </div>
                </aside>

                <div class="col-xl-10 col-lg-9">
                    <div class="page-breadcrumb">Admin / Thương hiệu</div>
                    <div class="page-kicker">Thương hiệu hệ thống</div>
                    <h1 class="section-title mb-2">Quản lý thương hiệu xe</h1>
                    <p class="section-subtitle mb-4">Thêm, chỉnh sửa và quản lý các thương hiệu xe đạp trên hệ thống.</p>
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
                    <?php endif; ?>

                    <div class="row g-4 mb-4">
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-award"></i></span>
                                <div><small>Tổng số thương hiệu</small><strong><?= e(number_format($totalBrands, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-patch-check"></i></span>
                                <div><small>Thương hiệu đang hoạt động</small><strong><?= e(number_format($activeBrands, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-stars"></i></span>
                                <div><small>Thương hiệu phổ biến</small><strong><?= e(number_format($popularBrands, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="stats-card">
                                <span class="stats-icon"><i class="bi bi-collection"></i></span>
                                <div><small>Số tin đăng theo thương hiệu</small><strong><?= e(number_format($brandedBikes, 0, ',', '.')) ?></strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="content-card mb-4">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex flex-column flex-xl-row gap-3 align-items-xl-center justify-content-between">
                                <div>
                                    <h2 class="section-heading mb-1">Bộ lọc thương hiệu</h2>
                                    <p class="text-muted mb-0">Theo dõi và cập nhật danh sách thương hiệu để việc lọc xe đạp chính xác hơn.</p>
                                </div>
                                <a href="#add-brand-form" class="btn btn-success"><i class="bi bi-plus-circle me-2"></i>Thêm thương hiệu</a>
                            </div>
                            <form method="get">
                                <div class="row g-3">
                                    <div class="col-xl-5 col-md-6">
                                        <input type="text" name="keyword" class="form-control" placeholder="Tìm theo tên thương hiệu" value="<?= e($keyword) ?>">
                                    </div>
                                    <div class="col-xl-2 col-md-6">
                                        <select name="status" class="form-select">
                                            <option value="">Tất cả trạng thái</option>
                                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
                                            <option value="hidden" <?= $statusFilter === 'hidden' ? 'selected' : '' ?>>Tạm ẩn</option>
                                        </select>
                                    </div>
                                    <div class="col-xl-3 col-md-6">
                                        <select name="sort" class="form-select">
                                            <option value="latest" <?= $sortFilter === 'latest' ? 'selected' : '' ?>>Mới nhất</option>
                                            <option value="oldest" <?= $sortFilter === 'oldest' ? 'selected' : '' ?>>Cũ nhất</option>
                                            <option value="name_asc" <?= $sortFilter === 'name_asc' ? 'selected' : '' ?>>Tên A-Z</option>
                                            <option value="name_desc" <?= $sortFilter === 'name_desc' ? 'selected' : '' ?>>Tên Z-A</option>
                                            <option value="posts_desc" <?= $sortFilter === 'posts_desc' ? 'selected' : '' ?>>Nhiều tin nhất</option>
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-md-6 d-grid">
                                        <button type="submit" class="btn btn-outline-success"><i class="bi bi-funnel me-2"></i>Lọc</button>
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
                                        <h2 class="section-heading mb-1">Danh sách thương hiệu</h2>
                                        <p class="text-muted mb-0">Quản lý các thương hiệu xe đang được sử dụng trên Bike Marketplace.</p>
                                    </div>
                                    <div class="text-muted small">Hiển thị <?= count($brandList) > 0 ? '1-' . count($brandList) : '0' ?> trong <?= e(number_format($totalBrands, 0, ',', '.')) ?> thương hiệu</div>
                                </div>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>STT</th>
                                                <th>Tên thương hiệu</th>
                                                <th>Mã thương hiệu</th>
                                                <th>Số tin đăng</th>
                                                <th>Trạng thái</th>
                                                <th>Ngày tạo</th>
                                                <th>Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($brandList)): ?>
                                                <?php foreach ($brandList as $index => $brand): ?>
                                                    <?php
                                                    $bikeCount = (int) ($brand['bike_count'] ?? 0);
                                                    $statusText = $bikeCount > 0 ? 'Đang hoạt động' : 'Tạm ẩn';
                                                    $statusClass = $bikeCount > 0 ? 'status-approved' : 'status-rejected';
                                                    ?>
                                                    <tr>
                                                        <td><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></td>
                                                        <td><?= e($brand['name']) ?></td>
                                                        <td>#<?= e((string) $brand['id']) ?></td>
                                                        <td><?= e(number_format($bikeCount, 0, ',', '.')) ?></td>
                                                        <td><span class="status-badge <?= e($statusClass) ?>"><?= e($statusText) ?></span></td>
                                                        <td><?= e(date('d/m/Y', strtotime($brand['created_at']))) ?></td>
                                                        <td><div class="d-flex flex-wrap gap-2"><a href="bikes.php?brand_id=<?= (int) $brand['id'] ?>" class="btn btn-sm btn-outline-dark">Xem</a></div></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-4">Không có thương hiệu phù hợp.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4">
                            <div class="content-card mb-4" id="add-brand-form">
                                <h2 class="section-heading">Thêm thương hiệu mới</h2>
                                <form method="post" class="d-flex flex-column gap-3">
                                    <div>
                                        <label class="form-label">Tên thương hiệu</label>
                                        <input type="text" name="name" class="form-control" placeholder="Ví dụ: Bianchi" required>
                                    </div>
                                    <button type="submit" name="add_brand" class="btn btn-success w-100">Lưu thương hiệu</button>
                                </form>
                            </div>

                            <div class="content-card">
                                <h2 class="section-heading">Lưu ý quản lý thương hiệu</h2>
                                <div class="mini-status">
                                    <div class="mini-status-item"><strong>Tên thương hiệu nên chính xác</strong><div class="text-muted mt-1">Giữ đúng cách viết để tránh nhầm lẫn khi người dùng tìm kiếm sản phẩm.</div></div>
                                    <div class="mini-status-item"><strong>Tránh tạo trùng thương hiệu</strong><div class="text-muted mt-1">Nên kiểm tra danh sách hiện có trước khi thêm mới để tránh phân mảnh dữ liệu.</div></div>
                                    <div class="mini-status-item"><strong>Thương hiệu hỗ trợ lọc tốt hơn</strong><div class="text-muted mt-1">Thông tin thương hiệu rõ ràng giúp trang listing và bộ lọc chính xác hơn.</div></div>
                                    <div class="mini-status-item"><strong>Theo dõi thương hiệu chưa có tin</strong><div class="text-muted mt-1">Các thương hiệu chưa được gán cho tin đăng sẽ được xem như tạm ẩn trên trang quản trị.</div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <nav aria-label="Điều hướng trang" class="mt-4">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item disabled"><a class="page-link" href="#">Trước</a></li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item disabled"><a class="page-link" href="#">Sau</a></li>
                        </ul>
                    </nav>

                    <div class="bottom-note">© 2026 Bike Marketplace Admin Panel</div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

