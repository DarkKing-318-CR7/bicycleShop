<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('buyer');

$currentUser = currentUser();
$isLoggedIn = isLoggedIn();
$userRole = $currentUser['role'] ?? '';
$buyerId = (int) ($currentUser['id'] ?? 0);
$userName = $currentUser['full_name'] ?? 'Tài khoản';
$buyerName = $userName;

$errors = [];
$successMessage = '';
$buyer = null;

function profileRoleLabel(string $role): string
{
    return $role === 'buyer' ? 'Người mua' : 'Tài khoản';
}

function profileStatusLabel(string $status): string
{
    switch ($status) {
        case 'active':
            return 'Hoạt động';
        case 'inactive':
            return 'Tạm khóa';
        default:
            return $status !== '' ? $status : 'Đang cập nhật';
    }
}

$selectSql = "
    SELECT id, full_name, email, phone, address, role, status
    FROM users
    WHERE id = ?
    LIMIT 1
";

$selectStmt = $conn->prepare($selectSql);

if ($selectStmt) {
    $selectStmt->bind_param('i', $buyerId);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $buyer = $result ? $result->fetch_assoc() : null;
    $selectStmt->close();
}

if (!$buyer) {
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($fullName === '') {
        $errors[] = 'Vui lòng nhập họ và tên.';
    }

    if (empty($errors)) {
        $updateSql = "
            UPDATE users
            SET full_name = ?, phone = ?, address = ?
            WHERE id = ?
            LIMIT 1
        ";
        $updateStmt = $conn->prepare($updateSql);

        if ($updateStmt) {
            $updateStmt->bind_param('sssi', $fullName, $phone, $address, $buyerId);

            if ($updateStmt->execute()) {
                $_SESSION['user']['full_name'] = $fullName;
                $buyerName = $fullName;
                $successMessage = 'Cập nhật hồ sơ thành công.';
                $buyer['full_name'] = $fullName;
                $buyer['phone'] = $phone;
                $buyer['address'] = $address;
            } else {
                $errors[] = 'Không thể cập nhật hồ sơ lúc này.';
            }

            $updateStmt->close();
        } else {
            $errors[] = 'Không thể chuẩn bị truy vấn cập nhật hồ sơ.';
        }
    }
}

$roleLabel = profileRoleLabel((string) ($buyer['role'] ?? 'buyer'));
$statusLabel = profileStatusLabel((string) ($buyer['status'] ?? ''));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Hồ sơ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/bike-marketplace.css">
</head>
<body class="seller-my-bikes-page">
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container py-2">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <span class="brand-mark"><i class="bi bi-bicycle"></i></span>
                Bike Marketplace
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Chuyển đổi điều hướng">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto mb-3 mb-lg-0 gap-lg-3">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="../bikes.php">Xe đạp</a></li>
                    <li class="nav-item"><a class="nav-link" href="../categories.php">Danh mục</a></li>
                    <li class="nav-item"><a class="nav-link" href="../contact.php">Liên hệ</a></li>
                </ul>
                <div class="dropdown">
                    <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= e($buyerName) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item active" href="profile.php"><i class="bi bi-person me-2"></i>Hồ sơ</a></li>
                        <li><a class="dropdown-item" href="my-orders.php"><i class="bi bi-receipt me-2"></i>Đơn mua của tôi</a></li>
                        <li><a class="dropdown-item" href="favorites.php"><i class="bi bi-heart me-2"></i>Xe yêu thích</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Người mua <span>/</span> Hồ sơ</div>
                <h1 class="section-title text-white mb-2">Hồ sơ tài khoản</h1>
                <p class="mb-0 text-white-50">Cập nhật thông tin liên hệ để giao dịch mua xe thuận tiện và rõ ràng hơn.</p>
            </div>
        </section>

        <section class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-4">
                    <aside class="profile-summary h-100">
                        <div class="profile-avatar">
                            <i class="bi bi-person"></i>
                        </div>
                        <h2 class="h4 fw-bold mb-1"><?= e((string) ($buyer['full_name'] ?? 'Tài khoản')) ?></h2>
                        <p class="text-muted mb-3"><?= e((string) ($buyer['email'] ?? '')) ?></p>
                        <span class="status-badge status-approved"><?= e($roleLabel) ?></span>
                        <div class="profile-meta">
                            <div><strong>Trạng thái</strong><span><?= e($statusLabel) ?></span></div>
                            <div><strong>Số điện thoại</strong><span><?= e(trim((string) ($buyer['phone'] ?? '')) !== '' ? (string) $buyer['phone'] : 'Chưa cập nhật') ?></span></div>
                            <div><strong>Địa chỉ</strong><span><?= e(trim((string) ($buyer['address'] ?? '')) !== '' ? (string) $buyer['address'] : 'Chưa cập nhật') ?></span></div>
                        </div>
                    </aside>
                </div>

                <div class="col-lg-8">
                    <div class="profile-card">
                        <h2 class="section-heading">Cập nhật hồ sơ</h2>
                        <p class="section-subtitle mb-4">Bạn có thể chỉnh sửa họ tên, số điện thoại và địa chỉ. Vai trò, trạng thái và mật khẩu không thay đổi tại trang này.</p>

                        <?php if ($successMessage !== ''): ?>
                            <div class="alert alert-success" role="alert"><?= e($successMessage) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label fw-semibold">Họ và tên</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="full_name"
                                        name="full_name"
                                        value="<?= e((string) ($buyer['full_name'] ?? '')) ?>"
                                        required
                                    >
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label fw-semibold">Email</label>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="email"
                                        value="<?= e((string) ($buyer['email'] ?? '')) ?>"
                                        readonly
                                    >
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label fw-semibold">Số điện thoại</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="phone"
                                        name="phone"
                                        value="<?= e((string) ($buyer['phone'] ?? '')) ?>"
                                    >
                                </div>
                                <div class="col-md-6">
                                    <label for="address" class="form-label fw-semibold">Địa chỉ</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="address"
                                        name="address"
                                        value="<?= e((string) ($buyer['address'] ?? '')) ?>"
                                    >
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Vai trò</label>
                                    <input type="text" class="form-control" value="<?= e($roleLabel) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Trạng thái tài khoản</label>
                                    <input type="text" class="form-control" value="<?= e($statusLabel) ?>" readonly>
                                </div>
                            </div>

                            <div class="action-row mt-4">
                                <button type="submit" class="btn btn-success">Lưu thay đổi</button>
                                <a href="my-orders.php" class="btn btn-outline-dark">Xem đơn mua</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer id="contact">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="fw-bold mb-3">Bike Marketplace</h4>
                    <p>Quản lý thông tin người mua, theo dõi đơn hàng và lưu các mẫu xe phù hợp trong một giao diện thống nhất.</p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Liên kết nhanh</h5>
                    <p class="mb-2"><a href="../bikes.php">Khám phá xe đạp</a></p>
                    <p class="mb-2"><a href="favorites.php">Xe yêu thích</a></p>
                    <p class="mb-0"><a href="../contact.php">Liên hệ hỗ trợ</a></p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Tài khoản của bạn</h5>
                    <p class="mb-2"><a href="profile.php">Hồ sơ</a></p>
                    <p class="mb-2"><a href="my-orders.php">Đơn mua của tôi</a></p>
                    <p class="mb-0"><a href="../logout.php">Đăng xuất</a></p>
                </div>
            </div>
        </div>
    </footer>

    <?php require __DIR__ . '/../includes/chat-widget.php'; ?>
    <script src="<?= e(baseUrl('js/chat-widget.js')) ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
