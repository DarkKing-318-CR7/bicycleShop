<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$success = '';

$formData = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'role' => 'buyer',
];

if (isLoggedIn()) {
    $role = $_SESSION['user']['role'] ?? '';

    if ($role === 'admin') {
        redirect('admin/index.php');
    }

    if ($role === 'seller') {
        redirect('seller/my-bikes.php');
    }

    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['full_name'] = trim($_POST['full_name'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['phone'] = trim($_POST['phone'] ?? '');
    $formData['address'] = trim($_POST['address'] ?? '');
    $formData['role'] = trim($_POST['role'] ?? 'buyer');

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($formData['full_name'] === '') {
        $errors[] = 'Vui lòng nhập họ và tên.';
    }

    if ($formData['email'] === '') {
        $errors[] = 'Vui lòng nhập email.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không đúng định dạng.';
    }

    if ($password === '') {
        $errors[] = 'Vui lòng nhập mật khẩu.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }

    if ($confirmPassword === '') {
        $errors[] = 'Vui lòng xác nhận mật khẩu.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Mật khẩu xác nhận không khớp.';
    }

    if (!in_array($formData['role'], ['buyer', 'seller'], true)) {
        $errors[] = 'Vai trò tài khoản không hợp lệ.';
    }

    if (empty($errors)) {
        $checkSql = "SELECT id FROM users WHERE email = ? LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);

        if ($checkStmt) {
            $checkStmt->bind_param('s', $formData['email']);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult && $checkResult->fetch_assoc()) {
                $errors[] = 'Email này đã được sử dụng. Vui lòng chọn email khác.';
            }

            $checkStmt->close();
        } else {
            $errors[] = 'Không thể kiểm tra email lúc này. Vui lòng thử lại sau.';
        }
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $insertSql = "INSERT INTO users (full_name, email, password, phone, address, role, status) VALUES (?, ?, ?, ?, ?, ?, 'active')";
        $insertStmt = $conn->prepare($insertSql);

        if ($insertStmt) {
            $insertStmt->bind_param(
                'ssssss',
                $formData['full_name'],
                $formData['email'],
                $hashedPassword,
                $formData['phone'],
                $formData['address'],
                $formData['role']
            );

            if ($insertStmt->execute()) {
                $success = 'Đăng ký tài khoản thành công. Bạn có thể đăng nhập ngay bây giờ.';
                $formData = [
                    'full_name' => '',
                    'email' => '',
                    'phone' => '',
                    'address' => '',
                    'role' => 'buyer',
                ];
            } else {
                $errors[] = 'Không thể tạo tài khoản lúc này. Vui lòng thử lại sau.';
            }

            $insertStmt->close();
        } else {
            $errors[] = 'Không thể xử lý đăng ký lúc này. Vui lòng thử lại sau.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Đăng ký tài khoản</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bike-marketplace.css">
</head>
<body class="register-page">
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
                    <li class="nav-item"><a class="nav-link" href="categories.php">Danh mục</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Liên hệ</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2">
                    <a href="login.php" class="btn btn-outline-dark">Đăng nhập</a>
                    <a href="register.php" class="btn btn-success">Đăng ký</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Đăng ký</div>
                <h1 class="section-title text-white mb-2">Đăng ký tài khoản</h1>
                <p class="mb-0 text-white-50">Tạo tài khoản để mua bán xe đạp thể thao cũ một cách nhanh chóng và thuận tiện.</p>
            </div>
        </section>

        <section class="container">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-6">
                    <div class="auth-brand h-100">
                        <span class="auth-eyebrow"><i class="bi bi-stars"></i> Không gian mua bán dành cho người yêu xe đạp</span>
                        <h2>Tham gia cộng đồng Bike Marketplace</h2>
                        <p>Tạo tài khoản để khám phá hàng trăm mẫu xe, kết nối trực tiếp với người mua và người bán, đồng thời quản lý mọi hoạt động giao dịch trong một giao diện hiện đại và dễ sử dụng.</p>
                        <div class="benefit-list">
                            <div class="benefit-item">
                                <span class="benefit-icon"><i class="bi bi-megaphone"></i></span>
                                <div>
                                    <strong>Đăng tin bán xe dễ dàng</strong>
                                    <p class="mb-0">Tạo tin chỉ trong vài bước với nội dung rõ ràng và hình ảnh nổi bật.</p>
                                </div>
                            </div>
                            <div class="benefit-item">
                                <span class="benefit-icon"><i class="bi bi-heart"></i></span>
                                <div>
                                    <strong>Lưu xe yêu thích nhanh chóng</strong>
                                    <p class="mb-0">Theo dõi các mẫu xe tiềm năng để quay lại xem khi cần.</p>
                                </div>
                            </div>
                            <div class="benefit-item">
                                <span class="benefit-icon"><i class="bi bi-people"></i></span>
                                <div>
                                    <strong>Kết nối trực tiếp với người mua và người bán</strong>
                                    <p class="mb-0">Trao đổi nhanh để đặt lịch xem xe, hỏi chi tiết và thương lượng.</p>
                                </div>
                            </div>
                            <div class="benefit-item">
                                <span class="benefit-icon"><i class="bi bi-person-gear"></i></span>
                                <div>
                                    <strong>Quản lý tài khoản thuận tiện</strong>
                                    <p class="mb-0">Dễ dàng cập nhật hồ sơ, trạng thái tin đăng và hoạt động gần đây.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="auth-card">
                        <h2 class="auth-title">Tạo tài khoản mới</h2>
                        <p class="auth-subtitle">Điền thông tin bên dưới để bắt đầu mua bán xe đạp trên Bike Marketplace.</p>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($success !== ''): ?>
                            <div class="alert alert-success" role="alert">
                                <?= e($success) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="register.php">
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="full_name">Họ và tên</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="full_name"
                                        name="full_name"
                                        placeholder="Nhập họ và tên của bạn"
                                        value="<?= e($formData['full_name']) ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="email">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="email"
                                        name="email"
                                        placeholder="emailcuaban@example.com"
                                        value="<?= e($formData['email']) ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="phone">Số điện thoại</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input
                                        type="tel"
                                        class="form-control"
                                        id="phone"
                                        name="phone"
                                        placeholder="Nhập số điện thoại"
                                        value="<?= e($formData['phone']) ?>"
                                    >
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="address">Địa chỉ</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="address"
                                        name="address"
                                        placeholder="Nhập địa chỉ của bạn"
                                        value="<?= e($formData['address']) ?>"
                                    >
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="password">Mật khẩu</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input
                                            type="password"
                                            class="form-control"
                                            id="password"
                                            name="password"
                                            placeholder="Tạo mật khẩu"
                                            required
                                        >
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="confirm_password">Xác nhận mật khẩu</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                        <input
                                            type="password"
                                            class="form-control"
                                            id="confirm_password"
                                            name="confirm_password"
                                            placeholder="Nhập lại mật khẩu"
                                            required
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 mb-3">
                                <label class="form-label fw-semibold" for="role">Vai trò tài khoản</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                    <select class="form-select" id="role" name="role">
                                        <option value="buyer" <?= $formData['role'] === 'buyer' ? 'selected' : '' ?>>Người mua</option>
                                        <option value="seller" <?= $formData['role'] === 'seller' ? 'selected' : '' ?>>Người bán</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                                <label class="form-check-label" for="agreeTerms">
                                    Tôi đồng ý với điều khoản sử dụng và chính sách của nền tảng
                                </label>
                            </div>

                            <button type="submit" class="btn btn-success w-100">Đăng ký</button>

                            <div class="divider">hoặc</div>

                            <div class="d-grid gap-3">
                                <button type="button" class="btn btn-outline-dark social-btn">
                                    <i class="bi bi-google"></i>
                                    Đăng ký với Google
                                </button>
                                <button type="button" class="btn btn-outline-dark social-btn">
                                    <i class="bi bi-facebook"></i>
                                    Đăng ký với Facebook
                                </button>
                            </div>

                            <div class="login-prompt text-center">
                                <p class="mb-2 muted">Đã có tài khoản?</p>
                                <a href="login.php" class="btn btn-outline-success">Đăng nhập ngay</a>
                            </div>
                        </form>
                    </div>

                    <div class="helper-card">
                        <h3 class="h5 fw-bold mb-2">Lưu ý khi tạo tài khoản</h3>
                        <p class="mb-2">Bạn có thể chọn vai trò người mua hoặc người bán khi tạo tài khoản để phù hợp với nhu cầu sử dụng.</p>
                        <p class="mb-0">Sau này bạn vẫn có thể cập nhật thêm thông tin hồ sơ, hình đại diện và nội dung tin đăng trong phần quản lý tài khoản.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="container">
            <div class="cta-band">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <h2 class="fw-bold mb-2">Bắt đầu hành trình mua bán xe đạp của bạn ngay hôm nay</h2>
                        <p class="mb-0">Tạo tài khoản để khám phá hàng trăm mẫu xe và đăng tin bán chỉ trong vài bước.</p>
                    </div>
                    <div class="col-lg-4 d-flex flex-column flex-sm-row gap-3 justify-content-lg-end">
                        <a href="bikes.php" class="btn btn-warning text-dark">Khám phá xe đạp</a>
                        <a href="contact.php" class="btn btn-outline-light">Liên hệ hỗ trợ</a>
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
                    <p>Nền tảng mua bán xe đạp hiện đại dành cho sinh viên và người yêu xe, nơi kết nối nhu cầu mua bán một cách trực quan và thuận tiện.</p>
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
                    <p class="mb-0">Hỗ trợ mỗi ngày từ 8:00 AM đến 8:00 PM dành cho người mua và người bán.</p>
                </div>
            </div>
            <div class="border-top border-secondary-subtle mt-4 pt-4 text-center text-white-50">
                <small>&copy; 2026 Bike Marketplace. Trang đăng ký được xây dựng với HTML, CSS, Bootstrap 5 và Bootstrap Icons.</small>
            </div>
        </div>
    </footer>

    <?php require __DIR__ . '/includes/chat-widget.php'; ?>
    <script src="<?= e(baseUrl('js/chat-widget.js')) ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
