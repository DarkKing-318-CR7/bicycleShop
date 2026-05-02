<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$email = '';

if (isLoggedIn()) {
    $role = $_SESSION['user']['role'] ?? '';

    if ($role === 'admin') {
        redirect('admin/index.php');
    }

    if ($role === 'seller') {
        redirect('seller/my-bikes.php');
    }

    if ($role === 'inspector') {
        redirect('inspector/index.php');
    }

    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        $sql = "SELECT id, full_name, email, password, role, status FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                ];

                if ($user['role'] === 'admin') {
                    redirect('admin/index.php');
                }

                if ($user['role'] === 'seller') {
                    redirect('seller/my-bikes.php');
                }

                if ($user['role'] === 'inspector') {
                    redirect('inspector/index.php');
                }

                redirect('index.php');
            } else {
                $error = 'Email hoặc mật khẩu không đúng, hoặc tài khoản chưa hoạt động.';
            }
        } else {
            $error = 'Không thể xử lý đăng nhập lúc này. Vui lòng thử lại sau.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Đăng nhập</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bike-marketplace.css">
</head>
<body class="login-page">
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
                    <a href="login.php" class="btn btn-success">Đăng nhập</a>
                    <a href="register.php" class="btn btn-outline-dark">Đăng ký</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Đăng nhập</div>
                <h1 class="section-title text-white mb-2">Đăng nhập</h1>
                <p class="mb-0 text-white-50">Đăng nhập để quản lý tin đăng, lưu xe yêu thích và kết nối với người bán.</p>
            </div>
        </section>

        <section class="container">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-6">
                    <div class="auth-brand h-100">
                        <span class="auth-eyebrow"><i class="bi bi-shield-check"></i> Nền tảng mua bán xe đạp đáng tin cậy</span>
                        <h2>Chào mừng trở lại với Bike Marketplace</h2>
                        <p>Truy cập tài khoản để theo dõi những mẫu xe phù hợp, trao đổi nhanh với người bán và quản lý tin đăng của bạn trong cùng một không gian hiện đại.</p>
                        <div class="benefit-list">
                            <div class="benefit-item">
                                <span class="benefit-icon"><i class="bi bi-heart"></i></span>
                                <div>
                                    <strong>Theo dõi xe yêu thích</strong>
                                    <p class="mb-0">Lưu các mẫu xe phù hợp để xem lại nhanh khi cần so sánh.</p>
                                </div>
                            </div>
                            <div class="benefit-item">
                                <span class="benefit-icon"><i class="bi bi-chat-dots"></i></span>
                                <div>
                                    <strong>Liên hệ người bán nhanh chóng</strong>
                                    <p class="mb-0">Kết nối trực tiếp để hỏi thông tin, lịch xem xe và thương lượng.</p>
                                </div>
                            </div>
                            <div class="benefit-item">
                                <span class="benefit-icon"><i class="bi bi-grid"></i></span>
                                <div>
                                    <strong>Quản lý tin đăng dễ dàng</strong>
                                    <p class="mb-0">Theo dõi trạng thái tin bán và cập nhật nội dung một cách thuận tiện.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="auth-card">
                        <h2 class="auth-title">Đăng nhập tài khoản</h2>
                        <p class="auth-subtitle">Nhập thông tin của bạn để tiếp tục sử dụng Bike Marketplace.</p>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= e($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="login.php">
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="email">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="email"
                                        name="email"
                                        placeholder="nhapemail@example.com"
                                        value="<?= e($email) ?>"
                                        required
                                    >
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="password">Mật khẩu</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password"
                                        name="password"
                                        placeholder="Nhập mật khẩu của bạn"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
                                <label class="form-check-label d-flex align-items-center gap-2">
                                    <input class="form-check-input mt-0" type="checkbox">
                                    Ghi nhớ đăng nhập
                                </label>
                                <a href="#" class="text-success fw-semibold">Quên mật khẩu?</a>
                            </div>

                            <button type="submit" class="btn btn-success w-100">Đăng nhập</button>

                            <div class="divider">hoặc</div>

                            <div class="d-grid gap-3">
                                <button type="button" class="btn btn-outline-dark social-btn">
                                    <i class="bi bi-google"></i>
                                    Đăng nhập với Google
                                </button>
                                <button type="button" class="btn btn-outline-dark social-btn">
                                    <i class="bi bi-facebook"></i>
                                    Đăng nhập với Facebook
                                </button>
                            </div>

                            <div class="register-prompt text-center">
                                <p class="mb-2 muted">Chưa có tài khoản?</p>
                                <a href="register.php" class="btn btn-outline-success">Đăng ký ngay</a>
                            </div>
                        </form>
                    </div>

                    <div class="support-card">
                        <h3 class="h5 fw-bold mb-2">Cần hỗ trợ?</h3>
                        <p class="mb-2">Liên hệ hỗ trợ nếu bạn gặp khó khăn khi đăng nhập hoặc cần xác minh tài khoản.</p>
                        <p class="mb-1"><i class="bi bi-envelope me-2"></i> support@bikemarketplace.com</p>
                        <p class="mb-0"><i class="bi bi-telephone me-2"></i> 1900 1234</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="container">
            <div class="cta-band">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <h2 class="fw-bold mb-2">Bạn muốn bán xe đạp của mình?</h2>
                        <p class="mb-0">Tạo tài khoản để đăng tin và tiếp cận cộng đồng yêu xe đạp.</p>
                    </div>
                    <div class="col-lg-4 d-flex flex-column flex-sm-row gap-3 justify-content-lg-end">
                        <a href="register.php" class="btn btn-warning text-dark">Đăng ký tài khoản</a>
                        <a href="bikes.php" class="btn btn-outline-light">Khám phá xe đạp</a>
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
                    <p>Nền tảng mua bán xe đạp hiện đại dành cho người yêu xe, nơi kết nối người bán uy tín với cộng đồng đam mê đạp xe.</p>
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
                <small>&copy; 2026 Bike Marketplace. Trang đăng nhập được xây dựng với HTML, CSS, Bootstrap 5 và Bootstrap Icons.</small>
            </div>
        </div>
    </footer>

    <?php require __DIR__ . '/includes/chat-widget.php'; ?>
    <script src="<?= e(baseUrl('js/chat-widget.js')) ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
