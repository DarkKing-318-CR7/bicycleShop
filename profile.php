<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = currentUser();
$userId = (int) ($currentUser['id'] ?? 0);
$userRole = $currentUser['role'] ?? '';

if ($userId <= 0) {
    redirect('login.php');
}

$hasPhoneColumn = tableColumnExists($conn, 'users', 'phone');
$hasAddressColumn = tableColumnExists($conn, 'users', 'address');
$hasUpdatedAtColumn = tableColumnExists($conn, 'users', 'updated_at');

$profileErrors = [];
$passwordErrors = [];
$profileSuccess = '';
$passwordSuccess = '';

function bindProfileParams(mysqli_stmt $stmt, string $types, array &$params): void
{
    $bindValues = [$types];

    foreach ($params as $key => &$value) {
        $bindValues[] = &$value;
    }

    call_user_func_array([$stmt, 'bind_param'], $bindValues);
}

function profileHomeLink(string $role): string
{
    if ($role === 'seller') {
        return 'seller/my-bikes.php';
    }

    if ($role === 'buyer') {
        return 'buyer/my-orders.php';
    }

    if ($role === 'admin') {
        return 'admin/index.php';
    }

    return 'index.php';
}

function profileRoleLabel(string $role): string
{
    switch ($role) {
        case 'seller':
            return 'Người bán';
        case 'buyer':
            return 'Người mua';
        case 'admin':
            return 'Quản trị viên';
        default:
            return 'Tài khoản';
    }
}

function loadProfile(mysqli $conn, int $userId, bool $hasPhoneColumn, bool $hasAddressColumn): ?array
{
    $phoneSelect = $hasPhoneColumn ? 'phone' : "'' AS phone";
    $addressSelect = $hasAddressColumn ? 'address' : "'' AS address";
    $sql = "
        SELECT id, full_name, email, password, role, status, {$phoneSelect}, {$addressSelect}
        FROM users
        WHERE id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $profile ?: null;
}

$profile = loadProfile($conn, $userId, $hasPhoneColumn, $hasAddressColumn);

if (!$profile) {
    $_SESSION = [];
    session_destroy();
    redirect('login.php');
}

$formData = [
    'full_name' => $profile['full_name'] ?? '',
    'email' => $profile['email'] ?? '',
    'phone' => $profile['phone'] ?? '',
    'address' => $profile['address'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'profile') {
        $formData['full_name'] = trim($_POST['full_name'] ?? '');
        $formData['email'] = trim($_POST['email'] ?? '');
        $formData['phone'] = trim($_POST['phone'] ?? '');
        $formData['address'] = trim($_POST['address'] ?? '');

        if ($formData['full_name'] === '') {
            $profileErrors[] = 'Vui lòng nhập họ và tên.';
        }

        if ($formData['email'] === '') {
            $profileErrors[] = 'Vui lòng nhập email.';
        } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $profileErrors[] = 'Email không đúng định dạng.';
        }

        if (empty($profileErrors)) {
            $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');

            if ($checkStmt) {
                $checkStmt->bind_param('si', $formData['email'], $userId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult && $checkResult->fetch_assoc()) {
                    $profileErrors[] = 'Email này đã được sử dụng bởi tài khoản khác.';
                }

                $checkStmt->close();
            } else {
                $profileErrors[] = 'Không thể kiểm tra email lúc này.';
            }
        }

        if (empty($profileErrors)) {
            $setParts = ['full_name = ?', 'email = ?'];
            $types = 'ss';
            $params = [$formData['full_name'], $formData['email']];

            if ($hasPhoneColumn) {
                $setParts[] = 'phone = ?';
                $types .= 's';
                $params[] = $formData['phone'];
            }

            if ($hasAddressColumn) {
                $setParts[] = 'address = ?';
                $types .= 's';
                $params[] = $formData['address'];
            }

            if ($hasUpdatedAtColumn) {
                $setParts[] = 'updated_at = CURRENT_TIMESTAMP';
            }

            $types .= 'i';
            $params[] = $userId;
            $updateSql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?';
            $updateStmt = $conn->prepare($updateSql);

            if ($updateStmt) {
                bindProfileParams($updateStmt, $types, $params);

                if ($updateStmt->execute()) {
                    $_SESSION['user']['full_name'] = $formData['full_name'];
                    $_SESSION['user']['email'] = $formData['email'];
                    $profileSuccess = 'Cập nhật thông tin tài khoản thành công.';
                    $profile = loadProfile($conn, $userId, $hasPhoneColumn, $hasAddressColumn) ?: $profile;
                } else {
                    $profileErrors[] = 'Không thể cập nhật thông tin lúc này.';
                }

                $updateStmt->close();
            } else {
                $profileErrors[] = 'Không thể chuẩn bị cập nhật thông tin.';
            }
        }
    }

    if ($formType === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '') {
            $passwordErrors[] = 'Vui lòng nhập mật khẩu hiện tại.';
        }

        if ($newPassword === '') {
            $passwordErrors[] = 'Vui lòng nhập mật khẩu mới.';
        } elseif (strlen($newPassword) < 6) {
            $passwordErrors[] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        }

        if ($confirmPassword === '') {
            $passwordErrors[] = 'Vui lòng xác nhận mật khẩu mới.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordErrors[] = 'Mật khẩu xác nhận không khớp.';
        }

        if (empty($passwordErrors) && !password_verify($currentPassword, (string) ($profile['password'] ?? ''))) {
            $passwordErrors[] = 'Mật khẩu hiện tại không đúng.';
        }

        if (empty($passwordErrors)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordSql = $hasUpdatedAtColumn
                ? 'UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
                : 'UPDATE users SET password = ? WHERE id = ?';
            $passwordStmt = $conn->prepare($passwordSql);

            if ($passwordStmt) {
                $passwordStmt->bind_param('si', $hashedPassword, $userId);

                if ($passwordStmt->execute()) {
                    $passwordSuccess = 'Đổi mật khẩu thành công.';
                    $profile = loadProfile($conn, $userId, $hasPhoneColumn, $hasAddressColumn) ?: $profile;
                } else {
                    $passwordErrors[] = 'Không thể đổi mật khẩu lúc này.';
                }

                $passwordStmt->close();
            } else {
                $passwordErrors[] = 'Không thể chuẩn bị đổi mật khẩu.';
            }
        }
    }
}

$roleLabel = profileRoleLabel((string) ($profile['role'] ?? $userRole));
$homeLink = profileHomeLink((string) ($profile['role'] ?? $userRole));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Thông tin tài khoản</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bike-marketplace.css">
</head>
<body class="profile-page">
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container py-2">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <span class="brand-mark"><i class="bi bi-bicycle"></i></span>
                Bike Marketplace
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto mb-3 mb-lg-0 gap-lg-3">
                    <li class="nav-item"><a class="nav-link" href="index.php">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="bikes.php">Xe đạp</a></li>
                    <li class="nav-item"><a class="nav-link" href="bikes.php#categories">Danh mục</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#contact">Liên hệ</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2">
                    <a href="<?= e($homeLink) ?>" class="btn btn-outline-dark"><?= e($roleLabel) ?></a>
                    <a href="logout.php" class="btn btn-success">Đăng xuất</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Tài khoản</div>
                <h1 class="section-title text-white mb-2">Thông tin tài khoản</h1>
                <p class="mb-0 text-white-50">Cập nhật thông tin liên hệ và đổi mật khẩu đăng nhập của bạn.</p>
            </div>
        </section>

        <section class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-4">
                    <aside class="profile-summary h-100">
                        <div class="profile-avatar">
                            <i class="bi bi-person"></i>
                        </div>
                        <h2 class="h4 fw-bold mb-1"><?= e($profile['full_name'] ?? '') ?></h2>
                        <p class="text-muted mb-3"><?= e($profile['email'] ?? '') ?></p>
                        <span class="status-badge status-approved"><?= e($roleLabel) ?></span>
                        <div class="profile-meta">
                            <div><strong>Trạng thái</strong><span><?= e($profile['status'] ?? 'active') ?></span></div>
                            <div><strong>Số điện thoại</strong><span><?= e(($profile['phone'] ?? '') !== '' ? $profile['phone'] : 'Chưa cập nhật') ?></span></div>
                            <div><strong>Địa chỉ</strong><span><?= e(($profile['address'] ?? '') !== '' ? $profile['address'] : 'Chưa cập nhật') ?></span></div>
                        </div>
                    </aside>
                </div>

                <div class="col-lg-8">
                    <div class="profile-card mb-4">
                        <h2 class="section-heading">Cập nhật thông tin</h2>

                        <?php if (!empty($profileErrors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($profileErrors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($profileSuccess !== ''): ?>
                            <div class="alert alert-success" role="alert"><?= e($profileSuccess) ?></div>
                        <?php endif; ?>

                        <form method="post" action="profile.php">
                            <input type="hidden" name="form_type" value="profile">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="full_name">Họ và tên</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($formData['full_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="email">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= e($formData['email']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="phone">Số điện thoại</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($formData['phone']) ?>" <?= $hasPhoneColumn ? '' : 'disabled' ?>>
                                    <?php if (!$hasPhoneColumn): ?>
                                        <div class="form-text">Database hiện chưa có cột phone.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="address">Địa chỉ</label>
                                    <input type="text" class="form-control" id="address" name="address" value="<?= e($formData['address']) ?>" <?= $hasAddressColumn ? '' : 'disabled' ?>>
                                    <?php if (!$hasAddressColumn): ?>
                                        <div class="form-text">Database hiện chưa có cột address.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success px-4">Lưu thông tin</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="profile-card">
                        <h2 class="section-heading">Đổi mật khẩu</h2>

                        <?php if (!empty($passwordErrors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($passwordErrors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($passwordSuccess !== ''): ?>
                            <div class="alert alert-success" role="alert"><?= e($passwordSuccess) ?></div>
                        <?php endif; ?>

                        <form method="post" action="profile.php">
                            <input type="hidden" name="form_type" value="password">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="current_password">Mật khẩu hiện tại</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="new_password">Mật khẩu mới</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="confirm_password">Xác nhận mật khẩu mới</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password">
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success px-4">Đổi mật khẩu</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="fw-bold mb-3">Bike Marketplace</h4>
                    <p>Quản lý tài khoản giúp giao dịch mua bán xe đạp minh bạch và thuận tiện hơn.</p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Thông tin liên hệ</h5>
                    <p class="mb-2"><i class="bi bi-geo-alt me-2"></i> 128 Market Street, Ho Chi Minh City</p>
                    <p class="mb-2"><i class="bi bi-telephone me-2"></i> +84 901 234 567</p>
                    <p class="mb-0"><i class="bi bi-envelope me-2"></i> hello@bikemarketplace.com</p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Theo dõi chúng tôi</h5>
                    <div class="d-flex gap-2 mb-3">
                        <a class="social-link" href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a class="social-link" href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a class="social-link" href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
                        <a class="social-link" href="#" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                    </div>
                    <p class="mb-0">Hỗ trợ mỗi ngày từ 8:00 AM đến 8:00 PM.</p>
                </div>
            </div>
            <div class="border-top border-secondary-subtle mt-4 pt-4 text-center text-white-50">
                <small>&copy; 2026 Bike Marketplace. Trang tài khoản được xây dựng bằng PHP, CSS và Bootstrap.</small>
            </div>
        </div>
    </footer>

    <?php require __DIR__ . '/includes/chat-widget.php'; ?>
    <script src="<?= e(baseUrl('js/chat-widget.js')) ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
