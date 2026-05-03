<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/support-messages.php';

$currentUser = currentUser();
$isLoggedIn = isLoggedIn();
$userRole = $currentUser['role'] ?? '';
$userName = $currentUser['full_name'] ?? '';
$userEmail = $currentUser['email'] ?? '';
$userPhone = $currentUser['phone'] ?? '';
$contactMessage = '';
$contactMessageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_form'])) {
    $contactResult = saveSupportMessageFromContactPost($conn, $_POST, $currentUser);
    $contactMessage = $contactResult['message'];
    $contactMessageType = $contactResult['success'] ? 'success' : 'danger';
}

function getUserHomeLink(string $role): string
{
    if ($role === 'admin') {
        return 'admin/index.php';
    }

    if ($role === 'seller') {
        return 'seller/my-bikes.php';
    }

    return 'index.php';
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Liên hệ hỗ trợ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bike-marketplace.css">
</head>

<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <span class="brand-mark"><i class="bi bi-bicycle"></i></span>
                Bike Marketplace
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Chuyển đổi điều hướng">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto mb-3 mb-lg-0 gap-lg-3">
                    <li class="nav-item"><a class="nav-link" href="index.php">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="bikes.php">Xe đạp</a></li>
                    <li class="nav-item"><a class="nav-link active" href="contact.php">Liên hệ</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= e(getUserHomeLink($userRole)) ?>" class="btn btn-outline-dark"><?= e($userName ?: 'Tài khoản') ?></a>
                        <a href="logout.php" class="btn btn-success">Đăng xuất</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-dark">Đăng nhập</a>
                        <a href="register.php" class="btn btn-success">Đăng ký</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-hero">
        <div class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-life-preserver"></i> Hỗ trợ người dùng</div>
                <h1>Liên hệ / Khiếu nại / Hỗ trợ</h1>
                <p>Gửi yêu cầu cho Bike Marketplace để admin tiếp nhận và xử lý trực tiếp.</p>
            </div>
        </div>
    </main>

    <section class="contact-section section-space pt-4" id="contact">
        <div class="container">
            <div class="contact-panel">
                <div class="row g-4 align-items-stretch">
                    <div class="col-lg-5">
                        <div class="contact-info h-100">
                            <div class="section-label text-warning">Liên hệ</div>
                            <h2 class="section-title text-white mb-3">Bạn cần hỗ trợ điều gì?</h2>
                            <p class="contact-copy">Mọi phản hồi, khiếu nại và yêu cầu hỗ trợ sẽ được lưu vào hộp thư admin.</p>
                            <div class="contact-list">
                                <div class="contact-item">
                                    <span><i class="bi bi-telephone"></i></span>
                                    <div><strong>Hotline</strong><p>+84 901 234 567</p></div>
                                </div>
                                <div class="contact-item">
                                    <span><i class="bi bi-envelope"></i></span>
                                    <div><strong>Email</strong><p>hello@bikemarketplace.com</p></div>
                                </div>
                                <div class="contact-item">
                                    <span><i class="bi bi-clock"></i></span>
                                    <div><strong>Giờ hỗ trợ</strong><p>8:00 AM - 8:00 PM mỗi ngày</p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <form class="contact-form h-100" action="contact.php#contact" method="post">
                            <input type="hidden" name="contact_form" value="1">
                            <?php if ($contactMessage !== ''): ?>
                                <div class="alert alert-<?= e($contactMessageType) ?>" role="alert"><?= e($contactMessage) ?></div>
                            <?php endif; ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_name">Họ và tên</label>
                                    <input type="text" class="form-control" id="contact_name" name="contact_name" value="<?= e($userName) ?>" placeholder="Nhập họ và tên">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_phone">Số điện thoại</label>
                                    <input type="tel" class="form-control" id="contact_phone" name="contact_phone" value="<?= e($userPhone) ?>" placeholder="Nhập số điện thoại">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_email">Email</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= e($userEmail) ?>" placeholder="email@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="contact_topic">Chủ đề</label>
                                    <select class="form-select" id="contact_topic" name="contact_topic">
                                        <option>Khiếu nại giao dịch</option>
                                        <option>Hỗ trợ tài khoản</option>
                                        <option>Hỗ trợ đăng tin</option>
                                        <option>Góp ý hệ thống</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="contact_message">Nội dung</label>
                                    <textarea class="form-control" id="contact_message" name="contact_message" rows="6" placeholder="Mô tả chi tiết khiếu nại hoặc yêu cầu hỗ trợ"></textarea>
                                </div>
                                <div class="col-12 d-grid d-sm-flex justify-content-sm-end">
                                    <button type="submit" class="btn btn-success px-4">
                                        <i class="bi bi-send me-2"></i>Gửi liên hệ
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <h4 class="fw-bold mb-3">Bike Marketplace</h4>
                    <p>Hộp thư hỗ trợ dành cho người mua và người bán.</p>
                </div>
                <div class="col-lg-6 text-lg-end">
                    <p class="mb-0">© 2026 Bike Marketplace</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
