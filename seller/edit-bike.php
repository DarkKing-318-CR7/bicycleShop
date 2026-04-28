<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

if (!hasRole('seller')) {
    redirect('../index.php');
}

requireRole('seller');

$currentUser = currentUser();
$sellerId = (int) ($currentUser['id'] ?? 0);
$sellerName = $currentUser['full_name'] ?? 'Người bán';
$userRole = $currentUser['role'] ?? '';
$bikeId = (int) ($_GET['id'] ?? 0);

$categories = [];
$brands = [];
$bikeImages = [];
$errors = [];
$success = '';
$fallbackImage = 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=800&q=80';

$formData = [
    'title' => '',
    'brand_id' => '',
    'category_id' => '',
    'price' => '',
    'condition_status' => 'used',
    'location' => '',
    'frame_size' => '',
    'wheel_size' => '',
    'brake_type' => '',
    'drivetrain' => '',
    'color' => '',
    'short_description' => '',
    'description' => '',
    'status' => 'pending',
];

function getUserHomeLink(string $role): string
{
    if ($role === 'admin') {
        return '../admin/index.php';
    }

    if ($role === 'seller') {
        return 'my-bikes.php';
    }

    return '../index.php';
}

function formatPriceVnd($price): string
{
    return number_format((float) $price, 0, ',', '.') . 'đ';
}

function getConditionLabel(string $condition): string
{
    switch ($condition) {
        case 'new':
            return 'Mới';
        case 'like_new':
            return 'Đã qua sử dụng - Rất tốt';
        case 'used':
        default:
            return 'Đã qua sử dụng - Tốt';
    }
}

function getStatusLabel(string $status): string
{
    switch ($status) {
        case 'approved':
            return 'Đã duyệt';
        case 'sold':
            return 'Đã bán';
        case 'rejected':
            return 'Từ chối';
        case 'pending':
        default:
            return 'Đang chờ duyệt';
    }
}

function getStatusClass(string $status): string
{
    switch ($status) {
        case 'approved':
            return 'status-approved';
        case 'sold':
            return 'status-sold';
        case 'rejected':
            return 'status-rejected';
        case 'pending':
        default:
            return 'status-pending';
    }
}

function normalizeImagePath(?string $path, string $fallback): string
{
    $path = trim((string) $path);

    if ($path === '') {
        return $fallback;
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    return '../' . ltrim($path, '/');
}

function uploadBikeImage(array $file, string $uploadDir, string $webPrefix): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmpName = $file['tmp_name'] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return null;
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $extension = strtolower(pathinfo((string) ($file['name'] ?? 'image'), PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        return null;
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = uniqid('bike_', true) . '.' . $extension;
    $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        return null;
    }

    return rtrim($webPrefix, '/') . '/' . $fileName;
}

$userHomeLink = getUserHomeLink($userRole);

$categoryResult = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

$brandResult = $conn->query("SELECT id, name FROM brands ORDER BY name ASC");
if ($brandResult) {
    while ($row = $brandResult->fetch_assoc()) {
        $brands[] = $row;
    }
}

if ($bikeId <= 0) {
    redirect('my-bikes.php');
}

$bikeSql = "
    SELECT
        b.id,
        b.title,
        b.category_id,
        b.brand_id,
        b.price,
        b.location,
        b.description,
        b.status,
        b.condition_status,
        b.frame_size,
        b.wheel_size,
        b.color
    FROM bikes b
    WHERE b.id = ? AND b.seller_id = ?
    LIMIT 1
";
$bikeStmt = $conn->prepare($bikeSql);
$bike = null;

if ($bikeStmt) {
    $bikeStmt->bind_param('ii', $bikeId, $sellerId);
    $bikeStmt->execute();
    $bikeResult = $bikeStmt->get_result();
    $bike = $bikeResult ? $bikeResult->fetch_assoc() : null;
    $bikeStmt->close();
}

if (!$bike) {
    redirect('my-bikes.php');
}

$formData = [
    'title' => $bike['title'] ?? '',
    'brand_id' => (string) ($bike['brand_id'] ?? ''),
    'category_id' => (string) ($bike['category_id'] ?? ''),
    'price' => isset($bike['price']) ? number_format((float) $bike['price'], 0, ',', '.') : '',
    'condition_status' => $bike['condition_status'] ?? 'used',
    'location' => $bike['location'] ?? '',
    'frame_size' => $bike['frame_size'] ?? '',
    'wheel_size' => $bike['wheel_size'] ?? '',
    'brake_type' => '',
    'drivetrain' => '',
    'color' => $bike['color'] ?? '',
    'short_description' => $bike['description'] ?? '',
    'description' => $bike['description'] ?? '',
    'status' => $bike['status'] ?? 'pending',
];

$imageSql = "SELECT id, image_url FROM bike_images WHERE bike_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC";
$imageStmt = $conn->prepare($imageSql);
if ($imageStmt) {
    $imageStmt->bind_param('i', $bikeId);
    $imageStmt->execute();
    $imageResult = $imageStmt->get_result();
    if ($imageResult) {
        while ($row = $imageResult->fetch_assoc()) {
            $bikeImages[] = $row;
        }
    }
    $imageStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['title'] = trim($_POST['title'] ?? '');
    $formData['brand_id'] = trim($_POST['brand_id'] ?? '');
    $formData['category_id'] = trim($_POST['category_id'] ?? '');
    $formData['price'] = trim($_POST['price'] ?? '');
    $formData['condition_status'] = trim($_POST['condition_status'] ?? 'used');
    $formData['location'] = trim($_POST['location'] ?? '');
    $formData['frame_size'] = trim($_POST['frame_size'] ?? '');
    $formData['wheel_size'] = trim($_POST['wheel_size'] ?? '');
    $formData['brake_type'] = trim($_POST['brake_type'] ?? '');
    $formData['drivetrain'] = trim($_POST['drivetrain'] ?? '');
    $formData['color'] = trim($_POST['color'] ?? '');
    $formData['short_description'] = trim($_POST['short_description'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['status'] = $bike['status'] ?? 'pending';

    if ($formData['title'] === '') {
        $errors[] = 'Vui lòng nhập tên xe.';
    }

    if ($formData['category_id'] === '' || (int) $formData['category_id'] <= 0) {
        $errors[] = 'Vui lòng chọn danh mục.';
    }

    if ($formData['price'] === '') {
        $errors[] = 'Vui lòng nhập giá.';
    }

    $normalizedPrice = (float) preg_replace('/[^\d.]/', '', str_replace(',', '', $formData['price']));
    if ($normalizedPrice <= 0) {
        $errors[] = 'Giá phải là số hợp lệ và lớn hơn 0.';
    }

    if (!in_array($formData['condition_status'], ['new', 'like_new', 'used'], true)) {
        $formData['condition_status'] = 'used';
    }

    if (empty($errors)) {
        $description = $formData['description'] !== '' ? $formData['description'] : $formData['short_description'];
        $brandId = (int) ($formData['brand_id'] !== '' ? $formData['brand_id'] : $bike['brand_id']);
        $categoryId = (int) $formData['category_id'];

        $updateSql = "
            UPDATE bikes
            SET title = ?, category_id = ?, brand_id = ?, price = ?, location = ?, description = ?, condition_status = ?, frame_size = ?, wheel_size = ?, color = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND seller_id = ?
        ";
        $updateStmt = $conn->prepare($updateSql);

        if ($updateStmt) {
            $updateStmt->bind_param(
                'siidssssssii',
                $formData['title'],
                $categoryId,
                $brandId,
                $normalizedPrice,
                $formData['location'],
                $description,
                $formData['condition_status'],
                $formData['frame_size'],
                $formData['wheel_size'],
                $formData['color'],
                $bikeId,
                $sellerId
            );

            if ($updateStmt->execute()) {
                $uploadDir = __DIR__ . '/../uploads/bikes';
                $webPrefix = 'uploads/bikes';
                $uploadedImages = [];

                if (isset($_FILES['main_image'])) {
                    $mainImagePath = uploadBikeImage($_FILES['main_image'], $uploadDir, $webPrefix);
                    if ($mainImagePath) {
                        $uploadedImages[] = ['path' => $mainImagePath, 'is_primary' => 1];
                    }
                }

                if (isset($_FILES['additional_images']['name']) && is_array($_FILES['additional_images']['name'])) {
                    foreach ($_FILES['additional_images']['name'] as $index => $name) {
                        $additionalFile = [
                            'name' => $name,
                            'type' => $_FILES['additional_images']['type'][$index] ?? '',
                            'tmp_name' => $_FILES['additional_images']['tmp_name'][$index] ?? '',
                            'error' => $_FILES['additional_images']['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                            'size' => $_FILES['additional_images']['size'][$index] ?? 0,
                        ];

                        $imagePath = uploadBikeImage($additionalFile, $uploadDir, $webPrefix);
                        if ($imagePath) {
                            $uploadedImages[] = ['path' => $imagePath, 'is_primary' => 0];
                        }
                    }
                }

                if (!empty($uploadedImages)) {
                    $imageInsertSql = "INSERT INTO bike_images (bike_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)";
                    $imageInsertStmt = $conn->prepare($imageInsertSql);
                    if ($imageInsertStmt) {
                        $sortOrder = count($bikeImages) + 1;
                        foreach ($uploadedImages as $image) {
                            $isPrimary = (int) $image['is_primary'];
                            $imagePath = $image['path'];
                            $imageInsertStmt->bind_param('isii', $bikeId, $imagePath, $isPrimary, $sortOrder);
                            $imageInsertStmt->execute();
                            $sortOrder++;
                        }
                        $imageInsertStmt->close();
                    }
                }

                $success = 'Cập nhật tin đăng thành công.';

                $refreshStmt = $conn->prepare($bikeSql);
                if ($refreshStmt) {
                    $refreshStmt->bind_param('ii', $bikeId, $sellerId);
                    $refreshStmt->execute();
                    $refreshResult = $refreshStmt->get_result();
                    $bike = $refreshResult ? $refreshResult->fetch_assoc() : $bike;
                    $refreshStmt->close();
                }

                $formData = [
                    'title' => $bike['title'] ?? $formData['title'],
                    'brand_id' => (string) ($bike['brand_id'] ?? $formData['brand_id']),
                    'category_id' => (string) ($bike['category_id'] ?? $formData['category_id']),
                    'price' => isset($bike['price']) ? number_format((float) $bike['price'], 0, ',', '.') : $formData['price'],
                    'condition_status' => $bike['condition_status'] ?? $formData['condition_status'],
                    'location' => $bike['location'] ?? $formData['location'],
                    'frame_size' => $bike['frame_size'] ?? $formData['frame_size'],
                    'wheel_size' => $bike['wheel_size'] ?? $formData['wheel_size'],
                    'brake_type' => $formData['brake_type'],
                    'drivetrain' => $formData['drivetrain'],
                    'color' => $bike['color'] ?? $formData['color'],
                    'short_description' => $bike['description'] ?? $formData['short_description'],
                    'description' => $bike['description'] ?? $formData['description'],
                    'status' => $bike['status'] ?? $formData['status'],
                ];

                $bikeImages = [];
                $reloadImageStmt = $conn->prepare($imageSql);
                if ($reloadImageStmt) {
                    $reloadImageStmt->bind_param('i', $bikeId);
                    $reloadImageStmt->execute();
                    $reloadImageResult = $reloadImageStmt->get_result();
                    if ($reloadImageResult) {
                        while ($row = $reloadImageResult->fetch_assoc()) {
                            $bikeImages[] = $row;
                        }
                    }
                    $reloadImageStmt->close();
                }
            } else {
                $errors[] = 'Không thể cập nhật tin đăng lúc này.';
            }

            $updateStmt->close();
        } else {
            $errors[] = 'Không thể chuẩn bị câu lệnh cập nhật.';
        }
    }
}

$previewImage = !empty($bikeImages) ? normalizeImagePath($bikeImages[0]['image_url'] ?? '', $fallbackImage) : $fallbackImage;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Chỉnh sửa tin đăng</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/bike-marketplace.css">
</head>
<body class="seller-add-page">
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container py-2">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <span class="brand-mark"><i class="bi bi-bicycle"></i></span>
                Bike Marketplace
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto mb-3 mb-lg-0 gap-lg-3">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="../bikes.php">Xe đạp</a></li>
                    <li class="nav-item"><a class="nav-link" href="../bikes.php#categories">Danh mục</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Liên hệ</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2">
                    <a href="my-bikes.php" class="btn btn-outline-dark">Tin đăng</a>
                    <a href="orders.php" class="btn btn-outline-dark">Đơn mua</a>
                    <a href="add-bike.php" class="btn btn-success">Đăng tin mới</a>
                    <a href="../profile.php" class="btn btn-outline-dark"><?= e($sellerName) ?></a>
                    <a href="../logout.php" class="btn btn-success">Đăng xuất</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Người bán <span>/</span> Sửa tin</div>
                <h1 class="section-title text-white mb-2">Chỉnh sửa tin đăng</h1>
                <p class="mb-0 text-white-50">Cập nhật thông tin xe đạp của bạn.</p>
            </div>
        </section>

        <section class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <div class="form-card">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                            <div>
                                <span class="status-badge <?= e(getStatusClass($formData['status'])) ?>"><?= e(getStatusLabel($formData['status'])) ?></span>
                                <h2 class="section-title fs-2 mt-3 mb-2">Cập nhật thông tin xe đạp</h2>
                                <p class="section-subtitle mb-0">Chỉnh sửa nội dung tin đăng để thông tin rõ ràng hơn, hấp dẫn hơn và dễ được duyệt nhanh hơn.</p>
                            </div>
                            <a href="my-bikes.php" class="btn btn-outline-success">Quay lại tin của tôi</a>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger mt-4 mb-0" role="alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($success !== ''): ?>
                            <div class="alert alert-success mt-4 mb-0" role="alert">
                                <?= e($success) ?>
                            </div>
                        <?php endif; ?>

                        <form class="mt-4" method="post" action="edit-bike.php?id=<?= e($bikeId) ?>" enctype="multipart/form-data">
                            <div class="section-block mt-0 pt-0 border-0">
                                <h3 class="section-heading">Thông tin cơ bản</h3>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Tên xe</label>
                                        <input type="text" name="title" class="form-control" value="<?= e($formData['title']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Thương hiệu</label>
                                        <select name="brand_id" class="form-select">
                                            <?php foreach ($brands as $brand): ?>
                                                <option value="<?= e((int) $brand['id']) ?>" <?= (int) $formData['brand_id'] === (int) $brand['id'] ? 'selected' : '' ?>><?= e($brand['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Danh mục</label>
                                        <select name="category_id" class="form-select">
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= e((int) $category['id']) ?>" <?= (int) $formData['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Giá</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-cash-stack"></i></span>
                                            <input type="text" name="price" class="form-control" value="<?= e($formData['price']) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Tình trạng</label>
                                        <select name="condition_status" class="form-select">
                                            <option value="new" <?= $formData['condition_status'] === 'new' ? 'selected' : '' ?>>Mới</option>
                                            <option value="like_new" <?= $formData['condition_status'] === 'like_new' ? 'selected' : '' ?>>Đã qua sử dụng - Rất tốt</option>
                                            <option value="used" <?= $formData['condition_status'] === 'used' ? 'selected' : '' ?>>Đã qua sử dụng - Tốt</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Khu vực</label>
                                        <input type="text" name="location" class="form-control" value="<?= e($formData['location']) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="section-block">
                                <h3 class="section-heading">Thông số kỹ thuật</h3>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Size khung</label>
                                        <input type="text" name="frame_size" class="form-control" value="<?= e($formData['frame_size']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Size bánh</label>
                                        <input type="text" name="wheel_size" class="form-control" value="<?= e($formData['wheel_size']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Phanh</label>
                                        <input type="text" name="brake_type" class="form-control" value="<?= e($formData['brake_type']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Bộ truyền động</label>
                                        <input type="text" name="drivetrain" class="form-control" value="<?= e($formData['drivetrain']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Màu sắc</label>
                                        <input type="text" name="color" class="form-control" value="<?= e($formData['color']) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="section-block">
                                <h3 class="section-heading">Mô tả</h3>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Mô tả ngắn</label>
                                        <input type="text" name="short_description" class="form-control" value="<?= e($formData['short_description']) ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Mô tả chi tiết</label>
                                        <textarea name="description" class="form-control"><?= e($formData['description']) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="section-block">
                                <h3 class="section-heading">Hình ảnh</h3>
                                <div class="row g-3">
                                    <?php if (!empty($bikeImages)): ?>
                                        <?php foreach ($bikeImages as $image): ?>
                                            <div class="col-6 col-md-3">
                                                <img src="<?= e(normalizeImagePath($image['image_url'] ?? '', $fallbackImage)) ?>" alt="Ảnh xe" class="img-fluid rounded-4 shadow-sm">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-6 col-md-3">
                                            <img src="<?= e($fallbackImage) ?>" alt="Ảnh xe" class="img-fluid rounded-4 shadow-sm">
                                        </div>
                                    <?php endif; ?>
                                    <div class="col-12 d-flex flex-column flex-sm-row gap-3">
                                        <input type="file" name="main_image" class="form-control" accept="image/*">
                                        <input type="file" name="additional_images[]" class="form-control" accept="image/*" multiple>
                                    </div>
                                </div>
                            </div>

                            <div class="action-row">
                                <button type="submit" class="btn btn-success">Lưu thay đổi</button>
                                <a href="my-bikes.php" class="btn btn-outline-dark">Hủy</a>
                                <a href="../bike-detail.php?id=<?= e($bikeId) ?>" class="btn btn-outline-success">Xem tin đăng</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="sidebar-card">
                        <h3 class="section-heading">Xem trước</h3>
                        <div class="preview-box">
                            <div class="preview-image">
                                <img src="<?= e($previewImage) ?>" alt="<?= e($formData['title']) ?>" class="w-100 h-100 object-fit-cover rounded-top-4">
                            </div>
                            <div class="preview-content">
                                <div class="preview-title"><?= e($formData['title'] !== '' ? $formData['title'] : 'Tên xe sẽ hiển thị tại đây') ?></div>
                                <div class="price mb-2"><?= e($formData['price'] !== '' ? $formData['price'] . 'đ' : 'Giá bán') ?></div>
                                <div class="preview-meta mb-1">Tình trạng: <?= e(getConditionLabel($formData['condition_status'])) ?></div>
                                <div class="preview-meta mb-0">Khu vực: <?= e($formData['location'] !== '' ? $formData['location'] : 'Chưa cập nhật') ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="sidebar-card mt-4">
                        <h3 class="section-heading">Mẹo chỉnh sửa hiệu quả</h3>
                        <ul class="tip-list">
                            <li><i class="bi bi-check-circle-fill"></i><span>Cập nhật ảnh rõ nét để tăng độ tin cậy cho người mua.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Điều chỉnh giá hợp lý theo tình trạng thực tế của xe.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Mô tả chi tiết giúp người xem hiểu rõ lịch sử và cấu hình.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Cập nhật đúng tình trạng để tin đăng dễ được duyệt hơn.</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="container">
            <div class="cta-band">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <h2 class="fw-bold mb-2">Tăng khả năng bán nhanh</h2>
                        <p class="mb-0">Hoàn thiện thông tin và hình ảnh để tin đăng nổi bật hơn trước người mua quan tâm.</p>
                    </div>
                    <div class="col-lg-4 d-flex flex-column flex-sm-row gap-3 justify-content-lg-end">
                        <a href="../bike-detail.php?id=<?= e($bikeId) ?>" class="btn btn-warning text-dark">Xem tin đăng</a>
                        <a href="add-bike.php" class="btn btn-outline-light">Đăng tin mới</a>
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
                    <p>Nền tảng mua bán xe đạp hiện đại giúp người bán quản lý tin đăng hiệu quả và kết nối nhanh với người mua phù hợp.</p>
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
                    <p class="mb-0">Hỗ trợ mỗi ngày từ 8:00 AM đến 8:00 PM dành cho cộng đồng mua bán xe đạp.</p>
                </div>
            </div>
            <div class="border-top border-secondary-subtle mt-4 pt-4 text-center text-white-50">
                <small>&copy; 2026 Bike Marketplace. Trang chỉnh sửa tin đăng được xây dựng với HTML, CSS, Bootstrap 5 và Bootstrap Icons.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
