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
$selectedBikeId = (int) ($_GET['bike_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderate_bike'])) {
    $bikeId = (int) ($_POST['bike_id'] ?? 0);
    $action = $_POST['action_type'] ?? '';

    if ($bikeId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        $stmt = $conn->prepare("UPDATE bikes SET status = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'");

        if ($stmt) {
            $stmt->bind_param("si", $newStatus, $bikeId);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                header('Location: moderation.php?msg=' . ($action === 'approve' ? 'approved' : 'rejected'));
                exit;
            }

            $stmt->close();
            header('Location: moderation.php?msg=not_found');
            exit;
        }

        $message = 'Không thể chuẩn bị truy vấn cập nhật trạng thái tin đăng.';
        $messageType = 'danger';
    } else {
        $message = 'Dữ liệu kiểm duyệt không hợp lệ.';
        $messageType = 'danger';
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'approved') {
        $message = 'Đã duyệt tin đăng thành công.';
    } elseif ($_GET['msg'] === 'rejected') {
        $message = 'Đã từ chối tin đăng thành công.';
    } elseif ($_GET['msg'] === 'not_found') {
        $message = 'Tin đăng không tồn tại hoặc đã được xử lý trước đó.';
        $messageType = 'warning';
    }
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

        $initials .= function_exists('mb_substr')
            ? mb_substr($part, 0, 1, 'UTF-8')
            : substr($part, 0, 1);

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return strtoupper($initials ?: 'AD');
}

function bikeImageSrc(?string $imageUrl): string
{
    $imageUrl = trim((string) $imageUrl);

    if ($imageUrl === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#', $imageUrl) || str_starts_with($imageUrl, '/')) {
        return $imageUrl;
    }

    return '../' . ltrim($imageUrl, '/');
}

$adminInitials = getInitials($adminName);

$bikeList = [];
$sql = "
    SELECT
        b.id,
        b.title,
        b.price,
        b.location,
        b.status,
        b.created_at,
        b.description,
        b.frame_size,
        b.wheel_size,
        b.color,
        b.condition_status,
        c.name AS category_name,
        br.name AS brand_name,
        u.full_name AS seller_name,
        u.email AS seller_email,
        u.phone AS seller_phone,
        (
            SELECT bi.image_url
            FROM bike_images bi
            WHERE bi.bike_id = b.id
            ORDER BY bi.is_primary DESC, bi.sort_order ASC, bi.id ASC
            LIMIT 1
        ) AS image_url
    FROM bikes b
    LEFT JOIN users u ON b.seller_id = u.id
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN brands br ON b.brand_id = br.id
    WHERE b.status = 'pending'
    ORDER BY b.created_at DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bikeList[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace Admin | Kiểm duyệt tin đăng</title>
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
                    <input type="text" class="form-control admin-search" style="max-width: 320px;" placeholder="Tìm kiếm tin đăng, người đăng, trạng thái">
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
                            <li><a class="menu-link" href="brands.php"><i class="bi bi-award"></i> Thương hiệu</a></li>
                            <li><a class="menu-link active" href="moderation.php"><i class="bi bi-shield-check"></i> Kiểm duyệt</a></li>
                            <li><a class="menu-link" href="statistics.php"><i class="bi bi-bar-chart"></i> Thống kê</a></li>
                            <li><a class="menu-link" href="settings.php"><i class="bi bi-gear"></i> Cài đặt</a></li>
                            <li><a class="menu-link" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a></li>
                        </ul>
                    </div>
                </aside>

                <div class="col-xl-10 col-lg-9">
                    <div class="page-breadcrumb">Admin / Kiểm duyệt</div>
                    <div class="page-kicker">Kiểm duyệt tin đăng</div>
                    <h1 class="section-title mb-2">Kiểm duyệt tin đăng xe đạp</h1>
                    <p class="section-subtitle mb-4">Xem xét các tin đăng đang chờ duyệt trước khi hiển thị công khai trên marketplace.</p>

                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div>
                    <?php endif; ?>

                    <div class="content-card">
                        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                            <div>
                                <h2 class="section-heading mb-1">Danh sách tin chờ duyệt</h2>
                                <p class="text-muted mb-0">Chỉ hiển thị các tin đăng có trạng thái chờ duyệt.</p>
                            </div>
                            <div class="text-muted small">
                                <?= e(number_format(count($bikeList), 0, ',', '.')) ?> tin đang chờ duyệt
                            </div>
                        </div>

                        <?php if (!empty($bikeList)): ?>
                            <div class="row g-4">
                                <?php foreach ($bikeList as $bike): ?>
                                    <div class="col-sm-6 col-xl-4 col-xxl-3">
                                        <div class="bike-card">
                                            <div class="ratio ratio-1x1 bg-light">
                                                <?php if (!empty($bike['image_url'])): ?>
                                                    <img src="<?= e(bikeImageSrc($bike['image_url'])) ?>" alt="<?= e($bike['title']) ?>" class="object-fit-cover">
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center text-muted small">Chưa có ảnh</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="content">
                                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                    <h5 class="mb-0"><?= e($bike['title']) ?></h5>
                                                    <span class="status-badge status-pending">Chờ duyệt</span>
                                                </div>
                                                <div class="text-muted small mb-2"><?= e($bike['seller_name'] ?? 'Không rõ') ?></div>
                                                <div class="fw-bold mb-2"><?= e(number_format((float) $bike['price'], 0, ',', '.')) ?>đ</div>
                                                <div class="text-muted small mb-3"><?= e(date('d/m/Y', strtotime($bike['created_at']))) ?></div>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#bikeModal<?= (int) $bike['id'] ?>">Xem</button>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="bike_id" value="<?= (int) $bike['id'] ?>">
                                                        <input type="hidden" name="action_type" value="approve">
                                                        <button type="submit" name="moderate_bike" class="btn btn-sm btn-success">Duyệt</button>
                                                    </form>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn từ chối tin đăng này?');">
                                                        <input type="hidden" name="bike_id" value="<?= (int) $bike['id'] ?>">
                                                        <input type="hidden" name="action_type" value="reject">
                                                        <button type="submit" name="moderate_bike" class="btn btn-sm btn-outline-danger">Từ chối</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">Không có tin đăng nào đang chờ duyệt.</div>
                        <?php endif; ?>
                    </div>

                    <div class="bottom-note">© 2026 Bike Marketplace Admin Panel</div>
                </div>
            </div>
        </div>
    </main>

    <?php foreach ($bikeList as $bike): ?>
        <div class="modal fade" id="bikeModal<?= (int) $bike['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Chi tiết tin đăng</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <?php if (!empty($bike['image_url'])): ?>
                                <div class="col-12">
                                    <img src="<?= e(bikeImageSrc($bike['image_url'])) ?>" alt="<?= e($bike['title']) ?>" class="img-fluid rounded-3">
                                </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <h6 class="fw-bold mb-0">Thông tin xe</h6>
                            </div>
                            <div class="col-md-6">
                                <strong>Tên xe:</strong>
                                <div><?= e($bike['title']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <strong>Giá:</strong>
                                <div><?= e(number_format((float) $bike['price'], 0, ',', '.')) ?>đ</div>
                            </div>
                            <div class="col-md-6">
                                <strong>Danh mục:</strong>
                                <div><?= e($bike['category_name'] ?? 'Chưa phân loại') ?></div>
                            </div>
                            <div class="col-md-6">
                                <strong>Thương hiệu:</strong>
                                <div><?= e($bike['brand_name'] ?? 'Chưa có') ?></div>
                            </div>
                            <div class="col-12">
                                <h6 class="fw-bold mb-0 mt-2">Thông tin người đăng</h6>
                            </div>
                            <div class="col-md-6">
                                <strong>Người đăng:</strong>
                                <div><?= e($bike['seller_name'] ?? 'Không rõ') ?></div>
                            </div>
                            <div class="col-md-6">
                                <strong>Email:</strong>
                                <div><?= e($bike['seller_email'] ?? '') ?></div>
                            </div>
                            <div class="col-md-6">
                                <strong>Số điện thoại:</strong>
                                <div><?= e($bike['seller_phone'] ?? '') ?></div>
                            </div>
                            <div class="col-md-6">
                                <strong>Địa điểm:</strong>
                                <div><?= e($bike['location'] ?? '') ?></div>
                            </div>
                            <div class="col-12">
                                <h6 class="fw-bold mb-0 mt-2">Thông số xe</h6>
                            </div>
                            <div class="col-md-6">
                                <strong>Frame size:</strong>
                                <div><?= e($bike['frame_size'] ?? '') ?></div>
                            </div>
                            <div class="col-md-6">
                                <strong>Wheel size:</strong>
                                <div><?= e($bike['wheel_size'] ?? '') ?></div>
                            </div>
                            <div class="col-md-6">
                                <strong>Màu sắc:</strong>
                                <div><?= e($bike['color'] ?? '') ?></div>
                            </div>
                            <div class="col-md-6">
                                <strong>Tình trạng:</strong>
                                <div><?= e($bike['condition_status'] ?? '') ?></div>
                            </div>
                            <div class="col-12">
                                <strong>Mô tả:</strong>
                                <div class="mt-2"><?= nl2br(e($bike['description'] ?? '')) ?></div>
                            </div>
                            <div class="col-12">
                                <strong>Ngày đăng:</strong>
                                <div><?= e(date('d/m/Y H:i', strtotime($bike['created_at']))) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="bike_id" value="<?= (int) $bike['id'] ?>">
                            <input type="hidden" name="action_type" value="approve">
                            <button type="submit" name="moderate_bike" class="btn btn-success">Duyệt</button>
                        </form>

                        <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn từ chối tin đăng này?');">
                            <input type="hidden" name="bike_id" value="<?= (int) $bike['id'] ?>">
                            <input type="hidden" name="action_type" value="reject">
                            <button type="submit" name="moderate_bike" class="btn btn-outline-danger">Từ chối</button>
                        </form>

                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php if ($selectedBikeId > 0): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modal = document.getElementById('bikeModal<?= (int) $selectedBikeId ?>');
                if (modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>
