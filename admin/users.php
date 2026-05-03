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

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_status'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    if ($userId > 0 && in_array($newStatus, ['active', 'inactive', 'banned'], true)) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $userId);

        if ($stmt->execute()) {
            header('Location: users.php?msg=updated');
            exit;
        } else {
            $message = 'Không thể cập nhật trạng thái tài khoản.';
            $messageType = 'danger';
        }

        $stmt->close();
    } else {
        $message = 'Dữ liệu cập nhật không hợp lệ.';
        $messageType = 'danger';
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $message = 'Đã cập nhật trạng thái tài khoản thành công.';
}

$totalUsers = fetchCount($conn, "SELECT COUNT(*) AS total FROM users");
$totalBuyers = fetchCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'buyer'");
$totalSellers = fetchCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'seller'");
$totalBanned = fetchCount($conn, "SELECT COUNT(*) AS total FROM users WHERE status = 'banned'");

$keyword = trim($_GET['keyword'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$sortFilter = trim($_GET['sort'] ?? 'latest');

$sql = "
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.role,
        u.status,
        u.created_at,
        COUNT(b.id) AS total_bikes
    FROM users u
    LEFT JOIN bikes b ON b.seller_id = u.id
    WHERE 1 = 1
";

$params = [];
$types = '';

if ($keyword !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $keywordLike = '%' . $keyword . '%';
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $types .= 'ss';
}

if ($roleFilter !== '') {
    $sql .= " AND u.role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}

if ($statusFilter !== '') {
    $sql .= " AND u.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$sql .= " GROUP BY u.id, u.full_name, u.email, u.phone, u.role, u.status, u.created_at ";

switch ($sortFilter) {
    case 'oldest':
        $sql .= " ORDER BY u.created_at ASC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY u.full_name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY u.full_name DESC";
        break;
    default:
        $sql .= " ORDER BY u.created_at DESC";
        break;
}

$sql .= " LIMIT 10";

$userList = [];
$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $userList[] = $row;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace Admin | Quản lý người dùng</title>
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
                            <li><a class="menu-link active" href="users.php"><i class="bi bi-people"></i> Quản lý người dùng</a></li>
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
                    <div class="page-breadcrumb">Admin / Quản lý người dùng</div>
                    <div class="page-kicker">Quản lý tài khoản</div>
                    <h1 class="section-title mb-2">Quản lý người dùng</h1>
                    <p class="section-subtitle mb-4">Theo dõi, tìm kiếm và quản lý tài khoản người dùng trên hệ thống.</p>
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
                    <?php endif; ?>
                    <div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stats-card">
            <span class="stats-icon"><i class="bi bi-people"></i></span>
            <div>
                <small>Tổng số tài khoản</small>
                <strong><?= e(number_format($totalUsers, 0, ',', '.')) ?></strong>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stats-card">
            <span class="stats-icon"><i class="bi bi-bag"></i></span>
            <div>
                <small>Người mua</small>
                <strong><?= e(number_format($totalBuyers, 0, ',', '.')) ?></strong>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stats-card">
            <span class="stats-icon"><i class="bi bi-shop"></i></span>
            <div>
                <small>Người bán</small>
                <strong><?= e(number_format($totalSellers, 0, ',', '.')) ?></strong>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stats-card">
            <span class="stats-icon"><i class="bi bi-person-lock"></i></span>
            <div>
                <small>Tài khoản bị khóa</small>
                <strong><?= e(number_format($totalBanned, 0, ',', '.')) ?></strong>
            </div>
        </div>
    </div>
</div>

                    <div class="content-card mb-4">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex flex-column flex-xl-row gap-3 align-items-xl-center justify-content-between">
                                <div>
                                    <h2 class="section-heading mb-1">Bộ lọc người dùng</h2>
                                    <p class="text-muted mb-0">Tìm nhanh theo họ tên, email, vai trò và trạng thái tài khoản.</p>
                                </div>
                                <a href="#" class="btn btn-success"><i class="bi bi-person-plus me-2"></i>Thêm tài khoản</a>
                            </div>
                            <form method="get">
                                <div class="row g-3 align-items-center">
                                    <div class="col-xl-4 col-md-6">
                                        <input
                                            type="text"
                                            name="keyword"
                                            class="form-control"
                                            placeholder="Tìm theo họ tên hoặc email"
                                            value="<?= e($keyword) ?>">
                                    </div>

                                    <div class="col-xl-2 col-md-6">
                                        <select name="role" class="form-select">
                                            <option value="">Tất cả vai trò</option>
                                            <option value="buyer" <?= $roleFilter === 'buyer' ? 'selected' : '' ?>>Người mua</option>
                                            <option value="seller" <?= $roleFilter === 'seller' ? 'selected' : '' ?>>Người bán</option>
                                            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                    </div>

                                    <div class="col-xl-2 col-md-6">
                                        <select name="status" class="form-select">
                                            <option value="">Tất cả trạng thái</option>
                                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Không hoạt động</option>
                                            <option value="banned" <?= $statusFilter === 'banned' ? 'selected' : '' ?>>Bị khóa</option>
                                        </select>
                                    </div>

                                    <div class="col-xl-2 col-md-6">
                                        <select name="sort" class="form-select">
                                            <option value="latest" <?= $sortFilter === 'latest' ? 'selected' : '' ?>>Mới nhất</option>
                                            <option value="oldest" <?= $sortFilter === 'oldest' ? 'selected' : '' ?>>Cũ nhất</option>
                                            <option value="name_asc" <?= $sortFilter === 'name_asc' ? 'selected' : '' ?>>Tên A-Z</option>
                                            <option value="name_desc" <?= $sortFilter === 'name_desc' ? 'selected' : '' ?>>Tên Z-A</option>
                                        </select>
                                    </div>

                                    <div class="col-xl-2 col-md-6">
                                        <button type="submit" class="btn btn-outline-success w-100">
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
                                        <h2 class="section-heading mb-1">Danh sách tài khoản</h2>
                                        <p class="text-muted mb-0">Hiển thị 10 tài khoản gần đây để quản trị và xử lý nhanh.</p>
                                    </div>
                                    <div class="text-muted small">Hiển thị 1-10 trong 1.284 tài khoản</div>
                                </div>
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Ảnh đại diện</th>
                                                <th>Họ tên</th>
                                                <th>Email</th>
                                                <th>Số điện thoại</th>
                                                <th>Vai trò</th>
                                                <th>Ngày tham gia</th>
                                                <th>Trạng thái</th>
                                                <th>Số tin đăng</th>
                                                <th>Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($userList)): ?>
                                                <?php foreach ($userList as $user): ?>
                                                    <?php
                                                    $initials = getInitials($user['full_name'] ?? 'U');

                                                    $roleText = match ($user['role']) {
                                                        'admin' => 'Admin',
                                                        'seller' => 'Người bán',
                                                        'buyer' => 'Người mua',
                                                        default => $user['role']
                                                    };

                                                    $statusText = match ($user['status']) {
                                                        'active' => 'Hoạt động',
                                                        'inactive' => 'Không hoạt động',
                                                        'banned' => 'Bị khóa',
                                                        default => $user['status']
                                                    };

                                                    $statusClass = match ($user['status']) {
                                                        'active' => 'status-approved',
                                                        'inactive' => 'status-pending',
                                                        'banned' => 'status-rejected',
                                                        default => 'status-pending'
                                                    };
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <span class="admin-avatar"><?= e($initials) ?></span>
                                                        </td>
                                                        <td><?= e($user['full_name']) ?></td>
                                                        <td><?= e($user['email']) ?></td>
                                                        <td><?= e($user['phone'] ?? '') ?></td>
                                                        <td><?= e($roleText) ?></td>
                                                        <td><?= e(date('d/m/Y', strtotime($user['created_at']))) ?></td>
                                                        <td>
                                                            <span class="status-badge <?= e($statusClass) ?>">
                                                                <?= e($statusText) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= e((string)($user['total_bikes'] ?? 0)) ?></td>
                                                        <td>
                                                            <div class="d-flex flex-wrap gap-2">
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-outline-dark rounded-pill px-3"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#userModal<?= (int)$user['id'] ?>">
                                                                    Xem
                                                                </button>

                                                                <?php if (($user['status'] ?? '') !== 'active'): ?>
                                                                    <form method="post" class="d-inline">
                                                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                                        <input type="hidden" name="new_status" value="active">
                                                                        <button type="submit" name="update_user_status" class="btn btn-sm btn-success rounded-pill px-3">
                                                                            Mở
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>

                                                                <?php if (($user['status'] ?? '') !== 'banned'): ?>
                                                                    <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn khóa tài khoản này?');">
                                                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                                        <input type="hidden" name="new_status" value="banned">
                                                                        <button type="submit" name="update_user_status" class="btn btn-sm btn-danger rounded-pill px-3">
                                                                            Khóa
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted py-4">Không có tài khoản phù hợp.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4">
                            <div class="content-card mb-4">
                                <h2 class="section-heading">Lưu ý quản lý tài khoản</h2>
                                <div class="mini-status">
                                    <div class="mini-status-item"><strong>Kiểm tra email và vai trò người dùng</strong>
                                        <div class="text-muted mt-1">Đảm bảo tài khoản được phân đúng nhóm để tránh nhầm quyền truy cập.</div>
                                    </div>
                                    <div class="mini-status-item"><strong>Khóa tài khoản vi phạm quy định</strong>
                                        <div class="text-muted mt-1">Áp dụng với các trường hợp đăng sai nội dung, spam hoặc có hành vi gây rủi ro.</div>
                                    </div>
                                    <div class="mini-status-item"><strong>Theo dõi người bán có nhiều tin đăng</strong>
                                        <div class="text-muted mt-1">Ưu tiên kiểm tra nhóm người bán hoạt động nhiều để duy trì chất lượng marketplace.</div>
                                    </div>
                                    <div class="mini-status-item"><strong>Xử lý hỗ trợ nhanh chóng</strong>
                                        <div class="text-muted mt-1">Phản hồi sớm các yêu cầu mở khóa hoặc cập nhật hồ sơ để giữ trải nghiệm ổn định.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="content-card">
                                <h2 class="section-heading">Tổng quan nhanh</h2>
                                <div class="mini-status">
                                    <div class="mini-status-item"><strong>16 người dùng mới hôm nay</strong>
                                        <div class="text-muted mt-1">Tăng nhẹ so với hôm qua, chủ yếu đến từ nhóm người mua mới đăng ký.</div>
                                    </div>
                                    <div class="mini-status-item"><strong>34 tài khoản bị khóa</strong>
                                        <div class="text-muted mt-1">Cần theo dõi lịch sử xử lý để tránh bỏ sót các trường hợp cần mở lại.</div>
                                    </div>
                                    <div class="mini-status-item"><strong>438 người bán đang hoạt động</strong>
                                        <div class="text-muted mt-1">Đây là nhóm tạo phần lớn tin đăng mới và cần được giám sát liên tục.</div>
                                    </div>
                                    <div class="mini-status-item"><strong>Nhắc quản trị</strong>
                                        <div class="text-muted mt-1">Ưu tiên kiểm tra tài khoản chờ xác minh và phản hồi các yêu cầu hỗ trợ trong ngày.</div>
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


    <?php if (!empty($userList)): ?>
        <?php foreach ($userList as $user): ?>
            <?php
            $roleText = match ($user['role']) {
                'admin' => 'Admin',
                'seller' => 'Người bán',
                'buyer' => 'Người mua',
                default => $user['role']
            };

            $statusText = match ($user['status']) {
                'active' => 'Hoạt động',
                'inactive' => 'Không hoạt động',
                'banned' => 'Bị khóa',
                default => $user['status']
            };
            ?>
            <div class="modal fade" id="userModal<?= (int)$user['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Chi tiết tài khoản</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <strong>Họ tên:</strong>
                                    <div><?= e($user['full_name']) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Email:</strong>
                                    <div><?= e($user['email']) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Số điện thoại:</strong>
                                    <div><?= e($user['phone'] ?? '') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Vai trò:</strong>
                                    <div><?= e($roleText) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Trạng thái:</strong>
                                    <div><?= e($statusText) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <strong>Số tin đăng:</strong>
                                    <div><?= e((string)($user['total_bikes'] ?? 0)) ?></div>
                                </div>
                                <div class="col-12">
                                    <strong>Ngày tham gia:</strong>
                                    <div><?= e(date('d/m/Y H:i', strtotime($user['created_at']))) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <?php if (($user['status'] ?? '') !== 'active'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                    <input type="hidden" name="new_status" value="active">
                                    <button type="submit" name="update_user_status" class="btn btn-sm btn-success rounded-pill px-3">
                                        Mở
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (($user['status'] ?? '') !== 'banned'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn khóa tài khoản này?');">
                                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                    <input type="hidden" name="new_status" value="banned">
                                    <button type="submit" name="update_user_status" class="btn btn-sm btn-danger rounded-pill px-3">
                                        Khóa
                                    </button>
                                </form>
                            <?php endif; ?>

                            <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">
                                Đóng
                            </button>
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
