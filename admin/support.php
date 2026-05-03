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

$supportTableReady = ensureSupportMessagesTable($conn);

$currentUser = currentUser();
$adminName = $currentUser['full_name'] ?? 'Quản trị viên';
$message = $supportTableReady ? '' : 'Chưa thể khởi tạo bảng support_messages. Vui lòng kiểm tra quyền CREATE TABLE của tài khoản database.';
$messageType = $supportTableReady ? 'success' : 'danger';

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

function supportStatusText(string $status): string
{
    return match ($status) {
        'new' => 'Mới',
        'read' => 'Đã đọc',
        'resolved' => 'Đã xử lý',
        default => $status,
    };
}

function supportStatusClass(string $status): string
{
    return match ($status) {
        'new' => 'status-rejected',
        'read' => 'status-pending',
        'resolved' => 'status-approved',
        default => 'status-pending',
    };
}

function shortSupportMessage(string $message, int $length = 90): string
{
    $message = trim(preg_replace('/\s+/', ' ', $message));

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($message, 'UTF-8') > $length
            ? mb_substr($message, 0, $length, 'UTF-8') . '...'
            : $message;
    }

    return strlen($message) > $length ? substr($message, 0, $length) . '...' : $message;
}

function updateSupportMessageStatus(mysqli $conn, int $messageId, string $status): bool
{
    if ($messageId <= 0 || !in_array($status, ['read', 'resolved'], true)) {
        return false;
    }

    $stmt = $conn->prepare("UPDATE support_messages SET status = ? WHERE id = ?");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $status, $messageId);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

$selectedId = (int) ($_GET['id'] ?? 0);
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$allowedSupportStatuses = ['new', 'read', 'resolved'];

if ($statusFilter !== '' && !in_array($statusFilter, $allowedSupportStatuses, true)) {
    $statusFilter = '';
}

if ($supportTableReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $supportId = (int) ($_POST['support_id'] ?? 0);
    $action = $_POST['support_action'] ?? '';
    $newStatus = match ($action) {
        'mark_read' => 'read',
        'mark_resolved' => 'resolved',
        default => '',
    };

    if ($supportId > 0 && $newStatus !== '' && updateSupportMessageStatus($conn, $supportId, $newStatus)) {
        header('Location: support.php?id=' . $supportId . '&msg=updated');
        exit;
    }

    $message = 'Không thể cập nhật trạng thái tin nhắn.';
    $messageType = 'danger';
}

if ($supportTableReady && ($_GET['msg'] ?? '') === 'updated') {
    $message = 'Đã cập nhật trạng thái tin nhắn.';
}

$selectedMessage = null;

if ($supportTableReady && $selectedId > 0) {
    $stmt = $conn->prepare("
        SELECT sm.id, sm.user_id, sm.name, sm.email, sm.phone, sm.subject, sm.message, sm.status, sm.created_at, sm.updated_at,
               u.full_name AS user_name, u.email AS user_email, u.phone AS user_phone
        FROM support_messages sm
        LEFT JOIN users u ON u.id = sm.user_id
        WHERE sm.id = ?
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param('i', $selectedId);
        $stmt->execute();
        $result = $stmt->get_result();
        $selectedMessage = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}

$supportMessages = [];
if ($supportTableReady) {
    $supportListSql = "
        SELECT sm.id, sm.user_id, sm.name, sm.email, sm.phone, sm.subject, sm.message, sm.status, sm.created_at,
               u.full_name AS user_name, u.email AS user_email, u.phone AS user_phone
        FROM support_messages sm
        LEFT JOIN users u ON u.id = sm.user_id
    ";

    if ($statusFilter !== '') {
        $supportListSql .= " WHERE sm.status = ? ";
    }

    $supportListSql .= "
        ORDER BY sm.created_at DESC
        LIMIT 50
    ";

    $stmt = $conn->prepare($supportListSql);

    if ($stmt) {
        if ($statusFilter !== '') {
            $stmt->bind_param('s', $statusFilter);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $supportMessages[] = $row;
        }

        $stmt->close();
    }
}

$adminInitials = getInitials($adminName);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace Admin | Hỗ trợ</title>
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
                            <li><a class="menu-link" href="moderation.php"><i class="bi bi-shield-check"></i> Kiểm duyệt</a></li>
                            <li><a class="menu-link" href="statistics.php"><i class="bi bi-bar-chart"></i> Thống kê</a></li>
                            <li><a class="menu-link" href="settings.php"><i class="bi bi-gear"></i> Cài đặt</a></li>
                            <li><a class="menu-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a></li>
                        </ul>
                    </div>
                </aside>

                <div class="col-xl-10 col-lg-9">
                    <div class="page-breadcrumb">Admin / Hỗ trợ</div>
                    <div class="page-kicker">Hộp thư hỗ trợ</div>
                    <h1 class="section-title mb-2">Feedback và yêu cầu trợ giúp</h1>
                    <p class="section-subtitle mb-4">Theo dõi phản hồi từ người dùng và cập nhật trạng thái xử lý.</p>

                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
                    <?php endif; ?>

                    <?php if ($selectedMessage): ?>
                        <div class="content-card mb-4">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                                <div>
                                    <h2 class="section-heading mb-1"><?= e($selectedMessage['subject'] ?? '') ?></h2>
                                    <p class="text-muted mb-0">
                                        Gửi bởi <?= e(supportMessageSenderName($selectedMessage)) ?>
                                        lúc <?= e(formatSupportMessageTime($selectedMessage['created_at'] ?? null)) ?>
                                    </p>
                                </div>
                                <span class="status-badge <?= e(supportStatusClass($selectedMessage['status'] ?? 'new')) ?>">
                                    <?= e(supportStatusText($selectedMessage['status'] ?? 'new')) ?>
                                </span>
                            </div>

                            <div class="support-detail-grid mb-3">
                                <div><strong>Họ tên:</strong> <?= e(supportMessageSenderName($selectedMessage)) ?></div>
                                <div><strong>Email:</strong> <?= e($selectedMessage['email'] ?: ($selectedMessage['user_email'] ?? 'Không có')) ?></div>
                                <div><strong>SĐT:</strong> <?= e($selectedMessage['phone'] ?: ($selectedMessage['user_phone'] ?? 'Không có')) ?></div>
                                <div><strong>Ngày gửi:</strong> <?= e(formatSupportMessageTime($selectedMessage['created_at'] ?? null)) ?></div>
                            </div>

                            <div class="support-message-body mb-3"><?= nl2br(e($selectedMessage['message'] ?? '')) ?></div>

                            <div class="d-flex flex-wrap gap-2">
                                <?php if (($selectedMessage['status'] ?? '') === 'new'): ?>
                                    <form method="post">
                                        <input type="hidden" name="support_id" value="<?= e($selectedMessage['id'] ?? '') ?>">
                                        <input type="hidden" name="support_action" value="mark_read">
                                        <button type="submit" class="btn btn-outline-success rounded-pill px-3">Đánh dấu đã đọc</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (($selectedMessage['status'] ?? '') !== 'resolved'): ?>
                                    <form method="post">
                                        <input type="hidden" name="support_id" value="<?= e($selectedMessage['id'] ?? '') ?>">
                                        <input type="hidden" name="support_action" value="mark_resolved">
                                        <button type="submit" class="btn btn-success rounded-pill px-3">Đánh dấu đã xử lý</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($selectedId > 0): ?>
                        <div class="alert alert-warning">Không tìm thấy tin nhắn hỗ trợ.</div>
                    <?php endif; ?>

                    <div class="content-card">
                        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                            <div>
                                <h2 class="section-heading mb-1">Danh sách tin nhắn</h2>
                                <p class="text-muted mb-0">Hiển thị tối đa 50 feedback và yêu cầu hỗ trợ mới nhất.</p>
                            </div>
                            <form method="get" class="d-flex gap-2 align-items-center">
                                <?php if ($selectedId > 0): ?>
                                    <input type="hidden" name="id" value="<?= e((string) $selectedId) ?>">
                                <?php endif; ?>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Tất cả</option>
                                    <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>Mới</option>
                                    <option value="read" <?= $statusFilter === 'read' ? 'selected' : '' ?>>Đã đọc</option>
                                    <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Đã xử lý</option>
                                </select>
                            </form>
                        </div>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Người gửi</th>
                                        <th>Email</th>
                                        <th>SĐT</th>
                                        <th>Tiêu đề</th>
                                        <th>Nội dung ngắn</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày gửi</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($supportMessages)): ?>
                                        <?php foreach ($supportMessages as $support): ?>
                                            <tr>
                                                <td><?= e(supportMessageSenderName($support)) ?></td>
                                                <td><?= e($support['email'] ?: ($support['user_email'] ?? 'Không có')) ?></td>
                                                <td><?= e($support['phone'] ?: ($support['user_phone'] ?? 'Không có')) ?></td>
                                                <td><?= e($support['subject'] ?? '') ?></td>
                                                <td><?= e(shortSupportMessage($support['message'] ?? '')) ?></td>
                                                <td>
                                                    <span class="status-badge <?= e(supportStatusClass($support['status'] ?? 'new')) ?>">
                                                        <?= e(supportStatusText($support['status'] ?? 'new')) ?>
                                                    </span>
                                                </td>
                                                <td><?= e(formatSupportMessageTime($support['created_at'] ?? null)) ?></td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <a class="btn btn-sm btn-outline-dark rounded-pill px-3" href="support.php?id=<?= e($support['id'] ?? '') ?>">Xem chi tiết</a>
                                                        <?php if (($support['status'] ?? '') === 'new'): ?>
                                                            <form method="post">
                                                                <input type="hidden" name="support_id" value="<?= e($support['id'] ?? '') ?>">
                                                                <input type="hidden" name="support_action" value="mark_read">
                                                                <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-3">Đã đọc</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if (($support['status'] ?? '') !== 'resolved'): ?>
                                                            <form method="post">
                                                                <input type="hidden" name="support_id" value="<?= e($support['id'] ?? '') ?>">
                                                                <input type="hidden" name="support_action" value="mark_resolved">
                                                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">Đã xử lý</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Không có tin nhắn hỗ trợ.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bottom-note">© 2026 Bike Marketplace Admin Panel</div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="../js/admin-notifications.js"></script>
    <script src="../js/admin-global-search.js"></script>
</body>

</html>
