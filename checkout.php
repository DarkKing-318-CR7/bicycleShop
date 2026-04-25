<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('buyer');

$currentUser = currentUser();
$buyerId = (int)($currentUser['id'] ?? 0);
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80';

$bikeId = (int)($_GET['bike_id'] ?? $_POST['bike_id'] ?? 0);

if ($bikeId <= 0) {
    redirect('bikes.php');
}

$error = '';
$success = '';

$formData = [
    'contact_method' => 'phone',
    'meeting_location' => '',
    'payment_method' => 'cash',
    'buyer_note' => '',
];

$bike = null;

$bikeSql = "
    SELECT
        b.id,
        b.title,
        b.price,
        b.description,
        b.location,
        b.status,
        b.created_at,
        b.seller_id,
        COALESCE(c.name, 'Danh mục khác') AS category_name,
        COALESCE(br.name, 'Không rõ thương hiệu') AS brand_name,
        COALESCE(u.full_name, 'Người bán') AS seller_name,
        COALESCE(u.phone, '') AS seller_phone,
        COALESCE(u.email, '') AS seller_email,
        COALESCE(img.image_url, ?) AS image_url
    FROM bikes b
    LEFT JOIN categories c ON c.id = b.category_id
    LEFT JOIN brands br ON br.id = b.brand_id
    LEFT JOIN users u ON u.id = b.seller_id
    LEFT JOIN bike_images img ON img.id = (
        SELECT bi.id
        FROM bike_images bi
        WHERE bi.bike_id = b.id
        ORDER BY bi.id ASC
        LIMIT 1
    )
    WHERE b.id = ?
    LIMIT 1
";

$bikeStmt = $conn->prepare($bikeSql);

if ($bikeStmt) {
    $bikeStmt->bind_param('si', $fallbackImage, $bikeId);
    $bikeStmt->execute();
    $bikeResult = $bikeStmt->get_result();
    $bike = $bikeResult ? $bikeResult->fetch_assoc() : null;
    $bikeStmt->close();
}

if (!$bike) {
    redirect('bikes.php');
}

$status = strtolower((string)($bike['status'] ?? 'pending'));
if (in_array($status, ['sold', 'completed'], true)) {
    $error = 'Xe này đã được bán hoặc không còn khả dụng để đặt mua.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $formData['contact_method'] = trim($_POST['contact_method'] ?? 'phone');
    $formData['meeting_location'] = trim($_POST['meeting_location'] ?? '');
    $formData['payment_method'] = trim($_POST['payment_method'] ?? 'cash');
    $formData['buyer_note'] = trim($_POST['buyer_note'] ?? '');

    if (!in_array($formData['contact_method'], ['phone', 'email', 'chat'], true)) {
        $error = 'Phương thức liên hệ không hợp lệ.';
    } elseif ($formData['meeting_location'] === '') {
        $error = 'Vui lòng nhập địa điểm gặp giao dịch.';
    } elseif (!in_array($formData['payment_method'], ['cash', 'transfer'], true)) {
        $error = 'Phương thức thanh toán không hợp lệ.';
    } else {
        $offeredPrice = (float)($bike['price'] ?? 0);
        $sellerId = (int)($bike['seller_id'] ?? 0);
        $orderCode = 'ORD-' . date('YmdHis') . '-' . $buyerId;

        $insertSql = "
            INSERT INTO orders (
                order_code,
                bike_id,
                buyer_id,
                seller_id,
                offered_price,
                contact_method,
                meeting_location,
                buyer_note,
                status,
                payment_method,
                payment_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'unpaid')
        ";

        $insertStmt = $conn->prepare($insertSql);

        if ($insertStmt) {
            $insertStmt->bind_param(
                'siiidssss',
                $orderCode,
                $bikeId,
                $buyerId,
                $sellerId,
                $offeredPrice,
                $formData['contact_method'],
                $formData['meeting_location'],
                $formData['buyer_note'],
                $formData['payment_method']
            );

            if ($insertStmt->execute()) {
                redirect('buyer/my-orders.php');
            } else {
                $error = 'Không thể tạo đơn hàng lúc này. Vui lòng thử lại sau.';
            }

            $insertStmt->close();
        } else {
            $error = 'Không thể xử lý đơn hàng lúc này.';
        }
    }
}

function formatPriceVnd($price): string
{
    return number_format((float)$price, 0, ',', '.') . 'đ';
}

function formatDateVi(?string $date): string
{
    if (!$date) {
        return 'Đang cập nhật';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return 'Đang cập nhật';
    }

    return date('d/m/Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Thanh toán</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bike-marketplace.css">
</head>
<body class="checkout-page">
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
                    <a href="buyer/my-orders.php" class="btn btn-outline-dark">Đơn hàng của tôi</a>
                    <a href="logout.php" class="btn btn-success">Đăng xuất</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note">
                    <i class="bi bi-house-door"></i> Trang chủ
                    <span>/</span> Thanh toán
                </div>
                <h1 class="section-title text-white mb-2">Xác nhận đơn mua</h1>
                <p class="mb-0 text-white-50">Kiểm tra thông tin xe và điền thông tin giao dịch để hoàn tất đặt mua.</p>
            </div>
        </section>

        <section class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    <div class="auth-card">
                        <h2 class="auth-title">Thông tin giao dịch</h2>
                        <p class="auth-subtitle">Vui lòng điền thông tin cần thiết để người bán có thể liên hệ và hẹn gặp với bạn nhanh chóng.</p>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= e($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success !== ''): ?>
                            <div class="alert alert-success" role="alert">
                                <?= e($success) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="checkout.php?bike_id=<?= e($bikeId) ?>">
                            <input type="hidden" name="bike_id" value="<?= e($bikeId) ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="contact_method">Phương thức liên hệ</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-chat-dots"></i></span>
                                    <select class="form-select" id="contact_method" name="contact_method" required>
                                        <option value="phone" <?= $formData['contact_method'] === 'phone' ? 'selected' : '' ?>>Điện thoại</option>
                                        <option value="email" <?= $formData['contact_method'] === 'email' ? 'selected' : '' ?>>Email</option>
                                        <option value="chat" <?= $formData['contact_method'] === 'chat' ? 'selected' : '' ?>>Chat</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="meeting_location">Địa điểm gặp giao dịch</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="meeting_location"
                                        name="meeting_location"
                                        placeholder="Nhập địa điểm gặp giao dịch"
                                        value="<?= e($formData['meeting_location']) ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="payment_method">Phương thức thanh toán</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-wallet2"></i></span>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="cash" <?= $formData['payment_method'] === 'cash' ? 'selected' : '' ?>>Tiền mặt</option>
                                        <option value="transfer" <?= $formData['payment_method'] === 'transfer' ? 'selected' : '' ?>>Chuyển khoản</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold" for="buyer_note">Ghi chú</label>
                                <textarea
                                    class="form-control"
                                    id="buyer_note"
                                    name="buyer_note"
                                    rows="4"
                                    placeholder="Ví dụ: Liên hệ sau 18h, muốn xem xe trước khi chốt..."
                                ><?= e($formData['buyer_note']) ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-success w-100">Xác nhận đặt mua</button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="helper-card mb-4">
                        <h3 class="section-heading">Thông tin xe đạp</h3>

                        <div class="d-flex gap-3 align-items-start">
                            <img
                                src="<?= e($bike['image_url'] ?? $fallbackImage) ?>"
                                alt="<?= e($bike['title'] ?? 'Xe đạp thể thao') ?>"
                                style="width: 120px; height: 90px; object-fit: cover; border-radius: 16px;"
                            >
                            <div class="flex-grow-1">
                                <h4 class="h5 fw-bold mb-2"><?= e($bike['title'] ?? 'Xe đạp thể thao') ?></h4>
                                <p class="mb-2 text-muted"><?= e($bike['category_name'] ?? 'Danh mục khác') ?> • <?= e($bike['brand_name'] ?? 'Không rõ thương hiệu') ?></p>
                                <p class="mb-0 fw-bold text-success"><?= e(formatPriceVnd($bike['price'] ?? 0)) ?></p>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex flex-column gap-2">
                            <div><strong>Địa điểm:</strong> <?= e($bike['location'] ?? 'Đang cập nhật') ?></div>
                            <div><strong>Ngày đăng:</strong> <?= e(formatDateVi($bike['created_at'] ?? null)) ?></div>
                            <div><strong>Người bán:</strong> <?= e($bike['seller_name'] ?? 'Người bán') ?></div>
                            <div><strong>Số điện thoại:</strong> <?= e($bike['seller_phone'] ?? 'Đang cập nhật') ?></div>
                            <div><strong>Email:</strong> <?= e($bike['seller_email'] ?? 'Đang cập nhật') ?></div>
                        </div>
                    </div>

                    <div class="tips-card">
                        <h3 class="section-heading">Lưu ý khi đặt mua</h3>
                        <ul class="tip-list">
                            <li><i class="bi bi-check-circle-fill"></i><span>Kiểm tra kỹ tình trạng xe và thông tin người bán trước khi giao dịch.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Nên hẹn gặp trực tiếp để xem xe thực tế trước khi thanh toán hoàn toàn.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Trao đổi rõ ràng về phương thức giao nhận, phụ kiện kèm theo và bảo hành nếu có.</span></li>
                        </ul>
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
                    <p>Nền tảng mua bán xe đạp thể thao hiện đại, giúp kết nối người mua và người bán một cách minh bạch, an toàn và thuận tiện.</p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Thông tin liên hệ</h5>
                    <p class="mb-2"><i class="bi bi-geo-alt me-2"></i> 128 Market Street, Ho Chi Minh City</p>
                    <p class="mb-2"><i class="bi bi-telephone me-2"></i> +84 901 234 567</p>
                    <p class="mb-0"><i class="bi bi-envelope me-2"></i> hello@bikemarketplace.com</p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold mb-3">Hỗ trợ giao dịch</h5>
                    <p class="mb-0">Chúng tôi hỗ trợ mỗi ngày từ 8:00 AM đến 8:00 PM để đảm bảo trải nghiệm mua bán xe đạp của bạn diễn ra thuận lợi.</p>
                </div>
            </div>
            <div class="border-top border-secondary-subtle mt-4 pt-4 text-center text-white-50">
                <small>&copy; 2026 Bike Marketplace. Trang checkout được xây dựng bằng PHP, CSS, Bootstrap và JavaScript tối giản.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
