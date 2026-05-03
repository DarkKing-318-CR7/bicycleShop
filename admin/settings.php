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
$adminId = (int) ($currentUser['id'] ?? 0);
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

        $initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return strtoupper($initials ?: 'AD');
}

function ensureSettingsTable(mysqli $conn): bool
{
    try {
        return (bool) $conn->query("
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    } catch (Throwable $error) {
        return false;
    }
}

function settingDefaults(): array
{
    return [
        'site_name' => 'Bike Marketplace',
        'contact_email' => '',
        'contact_phone' => '',
        'require_approval' => '1',
        'min_bike_price' => '0',
        'require_bike_image' => '0',
        'allow_registration' => '1',
        'default_user_role' => 'buyer',
        'allow_order_cancel' => '1',
        'seller_daily_post_limit' => '0',
        'default_order_status' => 'pending',
    ];
}

function loadSettings(mysqli $conn, array $defaults): array
{
    $settings = $defaults;

    try {
        $result = $conn->query("SELECT setting_key, setting_value FROM settings");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $key = (string) ($row['setting_key'] ?? '');
                if (array_key_exists($key, $settings)) {
                    $settings[$key] = (string) ($row['setting_value'] ?? '');
                }
            }
        }
    } catch (Throwable $error) {
        return $settings;
    }

    return $settings;
}

function saveSetting(mysqli $conn, string $key, string $value): bool
{
    try {
        $stmt = $conn->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $key, $value);
        $saved = $stmt->execute();
        $stmt->close();

        return $saved;
    } catch (Throwable $error) {
        return false;
    }
}

function seedDefaultSetting(mysqli $conn, string $key, string $value): bool
{
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $key, $value);
        $saved = $stmt->execute();
        $stmt->close();

        return $saved;
    } catch (Throwable $error) {
        return false;
    }
}

function setSettingsFlash(string $type, string $message): void
{
    $_SESSION['settings_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function normalizeMoneyInput(string $value): int
{
    $normalized = str_replace(['.', ',', 'đ', 'vnđ', 'vnd', ' '], '', strtolower($value));
    return is_numeric($normalized) ? max(0, (int) $normalized) : 0;
}

$tableReady = ensureSettingsTable($conn);
$defaults = settingDefaults();

if ($tableReady) {
    foreach ($defaults as $key => $value) {
        seedDefaultSetting($conn, $key, $value);
    }
}

$settings = loadSettings($conn, $defaults);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'save_settings') {
        $updatedSettings = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'contact_phone' => trim($_POST['contact_phone'] ?? ''),
            'require_approval' => isset($_POST['require_approval']) ? '1' : '0',
            'min_bike_price' => (string) normalizeMoneyInput((string) ($_POST['min_bike_price'] ?? '0')),
            'require_bike_image' => isset($_POST['require_bike_image']) ? '1' : '0',
            'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
            'default_user_role' => in_array($_POST['default_user_role'] ?? 'buyer', ['buyer', 'seller'], true) ? $_POST['default_user_role'] : 'buyer',
            'allow_order_cancel' => isset($_POST['allow_order_cancel']) ? '1' : '0',
            'seller_daily_post_limit' => (string) max(0, (int) ($_POST['seller_daily_post_limit'] ?? 0)),
            'default_order_status' => in_array($_POST['default_order_status'] ?? 'pending', ['pending', 'confirmed', 'shipping', 'completed', 'cancelled'], true) ? $_POST['default_order_status'] : 'pending',
        ];

        if ($updatedSettings['site_name'] === '') {
            $updatedSettings['site_name'] = $defaults['site_name'];
        }

        $failed = [];
        if ($tableReady) {
            foreach ($updatedSettings as $key => $value) {
                if (!saveSetting($conn, $key, (string) $value)) {
                    $failed[] = $key;
                }
            }
        } else {
            $failed[] = 'settings_table';
        }

        if (empty($failed)) {
            setSettingsFlash('success', 'Đã lưu cài đặt hệ thống thành công.');
        } else {
            setSettingsFlash('danger', 'Không thể lưu một số cài đặt. Vui lòng kiểm tra database.');
        }

        redirect('settings.php');
    }

    if ($action === 'change_password') {
        $oldPassword = (string) ($_POST['old_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
            setSettingsFlash('danger', 'Vui lòng nhập đầy đủ thông tin đổi mật khẩu.');
            redirect('settings.php#security');
        }

        if (strlen($newPassword) < 6) {
            setSettingsFlash('danger', 'Mật khẩu mới phải có ít nhất 6 ký tự.');
            redirect('settings.php#security');
        }

        if ($newPassword !== $confirmPassword) {
            setSettingsFlash('danger', 'Mật khẩu xác nhận không khớp.');
            redirect('settings.php#security');
        }

        try {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
            if (!$stmt) {
                setSettingsFlash('danger', 'Không thể kiểm tra mật khẩu admin.');
                redirect('settings.php#security');
            }

            $stmt->bind_param('i', $adminId);
            $stmt->execute();
            $result = $stmt->get_result();
            $adminRow = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            $currentHash = (string) ($adminRow['password'] ?? '');
            if ($currentHash === '' || !password_verify($oldPassword, $currentHash)) {
                setSettingsFlash('danger', 'Mật khẩu cũ không đúng.');
                redirect('settings.php#security');
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ? AND role = 'admin'");
            if (!$updateStmt) {
                setSettingsFlash('danger', 'Không thể chuẩn bị cập nhật mật khẩu.');
                redirect('settings.php#security');
            }

            $updateStmt->bind_param('si', $newHash, $adminId);
            $updated = $updateStmt->execute();
            $updateStmt->close();

            setSettingsFlash($updated ? 'success' : 'danger', $updated ? 'Đã đổi mật khẩu admin thành công.' : 'Không thể đổi mật khẩu admin.');
        } catch (Throwable $error) {
            setSettingsFlash('danger', 'Không thể đổi mật khẩu admin do cấu trúc dữ liệu không phù hợp.');
        }

        redirect('settings.php#security');
    }
}

$flash = $_SESSION['settings_flash'] ?? null;
unset($_SESSION['settings_flash']);

$adminInitials = getInitials($adminName);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace Admin | Cài đặt hệ thống</title>
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
                            <li><a class="menu-link active" href="settings.php"><i class="bi bi-gear"></i> Cài đặt</a></li>
                            <li><a class="menu-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a></li>
                        </ul>
                    </div>
                </aside>

                <div class="col-xl-10 col-lg-9">
                    <div class="page-breadcrumb">Admin / Cài đặt</div>
                    <div class="page-kicker">Cài đặt hệ thống</div>
                    <h1 class="section-title mb-2">Cài đặt hệ thống</h1>
                    <p class="section-subtitle mb-4">Quản lý luật đăng tin, người dùng, đơn hàng và bảo mật tài khoản quản trị.</p>

                    <?php if (!$tableReady): ?>
                        <div class="alert alert-danger">Không thể tạo hoặc đọc bảng settings. Vui lòng kiểm tra quyền database.</div>
                    <?php endif; ?>

                    <?php if ($flash): ?>
                        <div class="alert alert-<?= e($flash['type'] ?? 'success') ?>"><?= e($flash['message'] ?? '') ?></div>
                    <?php endif; ?>

                    <form method="post" action="settings.php">
                        <input type="hidden" name="form_action" value="save_settings">

                        <div class="row g-4">
                            <div class="col-xl-6">
                                <div class="content-card h-100">
                                    <h2 class="section-heading">Cài đặt chung</h2>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Tên website</label>
                                        <input type="text" class="form-control" name="site_name" value="<?= e($settings['site_name']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Email liên hệ</label>
                                        <input type="email" class="form-control" name="contact_email" value="<?= e($settings['contact_email']) ?>">
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold">Số điện thoại</label>
                                        <input type="text" class="form-control" name="contact_phone" value="<?= e($settings['contact_phone']) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6">
                                <div class="content-card h-100">
                                    <h2 class="section-heading">Cài đặt đăng tin</h2>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch" id="requireApproval" name="require_approval" <?= $settings['require_approval'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="requireApproval">Bật kiểm duyệt tin đăng</label>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Giá tối thiểu khi đăng xe</label>
                                        <input type="number" min="0" step="1000" class="form-control" name="min_bike_price" value="<?= e($settings['min_bike_price']) ?>">
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch" id="requireImage" name="require_bike_image" <?= $settings['require_bike_image'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="requireImage">Bắt buộc có ảnh xe</label>
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold">Số tin tối đa mỗi ngày cho seller</label>
                                        <input type="number" min="0" class="form-control" name="seller_daily_post_limit" value="<?= e($settings['seller_daily_post_limit']) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6">
                                <div class="content-card h-100">
                                    <h2 class="section-heading">Cài đặt người dùng</h2>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch" id="allowRegistration" name="allow_registration" <?= $settings['allow_registration'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="allowRegistration">Cho phép đăng ký tài khoản</label>
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold">Role mặc định khi đăng ký</label>
                                        <select class="form-select" name="default_user_role">
                                            <option value="buyer" <?= $settings['default_user_role'] === 'buyer' ? 'selected' : '' ?>>Buyer</option>
                                            <option value="seller" <?= $settings['default_user_role'] === 'seller' ? 'selected' : '' ?>>Seller</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6">
                                <div class="content-card h-100">
                                    <h2 class="section-heading">Cài đặt đơn hàng</h2>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch" id="allowOrderCancel" name="allow_order_cancel" <?= $settings['allow_order_cancel'] === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="allowOrderCancel">Cho phép hủy đơn</label>
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold">Trạng thái đơn mặc định</label>
                                        <select class="form-select" name="default_order_status">
                                            <option value="pending" <?= $settings['default_order_status'] === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
                                            <option value="confirmed" <?= $settings['default_order_status'] === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
                                            <option value="shipping" <?= $settings['default_order_status'] === 'shipping' ? 'selected' : '' ?>>Đang giao dịch</option>
                                            <option value="completed" <?= $settings['default_order_status'] === 'completed' ? 'selected' : '' ?>>Hoàn tất</option>
                                            <option value="cancelled" <?= $settings['default_order_status'] === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-success px-4"><i class="bi bi-save me-2"></i>Lưu cài đặt</button>
                        </div>
                    </form>

                    <div class="content-card mt-4" id="security">
                        <h2 class="section-heading">Bảo mật admin</h2>
                        <form method="post" action="settings.php#security" class="row g-3">
                            <input type="hidden" name="form_action" value="change_password">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Mật khẩu cũ</label>
                                <input type="password" class="form-control" name="old_password" autocomplete="current-password">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Mật khẩu mới</label>
                                <input type="password" class="form-control" name="new_password" autocomplete="new-password">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Nhập lại mật khẩu mới</label>
                                <input type="password" class="form-control" name="confirm_password" autocomplete="new-password">
                            </div>
                            <div class="col-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-outline-success"><i class="bi bi-shield-lock me-2"></i>Đổi mật khẩu</button>
                            </div>
                        </form>
                    </div>

                    <div class="bottom-note">© 2026 Bike Marketplace Admin Panel</div>
                </div>
            </div>
        </div>
    </main>
    <script src="../js/admin-notifications.js"></script>
    <script src="../js/admin-global-search.js"></script>
</body>

</html>
