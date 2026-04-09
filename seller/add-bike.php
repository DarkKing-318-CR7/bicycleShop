
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
$sellerEmail = $currentUser['email'] ?? '';
$sellerPhone = '';
$userRole = $currentUser['role'] ?? '';

$categories = [];
$brands = [];
$errors = [];
$success = '';

$formData = [
    'title' => '',
    'brand_id' => '',
    'category_id' => '',
    'price' => '',
    'condition_status' => 'new',
    'location' => '',
    'year' => '',
    'frame_size' => '',
    'wheel_size' => '',
    'frame_material' => '',
    'drivetrain' => '',
    'brake_type' => '',
    'color' => '',
    'short_description' => '',
    'description' => '',
    'seller_name' => $sellerName,
    'seller_phone' => $sellerPhone,
    'seller_email' => $sellerEmail,
    'contact_method' => 'phone',
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

function slugify(string $text): string
{
    $text = trim($text);

    if ($text === '') {
        return 'xe-dap';
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');

    return $text !== '' ? $text : 'xe-dap';
}

function getUniqueBikeSlug(mysqli $conn, string $title): string
{
    $baseSlug = slugify($title);
    $slug = $baseSlug;
    $counter = 1;

    while (true) {
        $sql = "SELECT id FROM bikes WHERE slug = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return $slug . '-' . time();
        }

        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->fetch_assoc();
        $stmt->close();

        if (!$exists) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
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
    $originalName = $file['name'] ?? 'image';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

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

$userSql = "SELECT phone FROM users WHERE id = ? LIMIT 1";
$userStmt = $conn->prepare($userSql);
if ($userStmt) {
    $userStmt->bind_param('i', $sellerId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userRow = $userResult ? $userResult->fetch_assoc() : null;
    $sellerPhone = $userRow['phone'] ?? '';
    $userStmt->close();
    $formData['seller_phone'] = $sellerPhone;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['title'] = trim($_POST['title'] ?? '');
    $formData['brand_id'] = trim($_POST['brand_id'] ?? '');
    $formData['category_id'] = trim($_POST['category_id'] ?? '');
    $formData['price'] = trim($_POST['price'] ?? '');
    $formData['condition_status'] = trim($_POST['condition_status'] ?? 'new');
    $formData['location'] = trim($_POST['location'] ?? '');
    $formData['year'] = trim($_POST['year'] ?? '');
    $formData['frame_size'] = trim($_POST['frame_size'] ?? '');
    $formData['wheel_size'] = trim($_POST['wheel_size'] ?? '');
    $formData['frame_material'] = trim($_POST['frame_material'] ?? '');
    $formData['drivetrain'] = trim($_POST['drivetrain'] ?? '');
    $formData['brake_type'] = trim($_POST['brake_type'] ?? '');
    $formData['color'] = trim($_POST['color'] ?? '');
    $formData['short_description'] = trim($_POST['short_description'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['seller_name'] = trim($_POST['seller_name'] ?? $sellerName);
    $formData['seller_phone'] = trim($_POST['seller_phone'] ?? $sellerPhone);
    $formData['seller_email'] = trim($_POST['seller_email'] ?? $sellerEmail);
    $formData['contact_method'] = trim($_POST['contact_method'] ?? 'phone');

    if ($formData['title'] === '') {
        $errors[] = 'Vui lòng nhập tên xe.';
    }

    if ($formData['category_id'] === '' || (int) $formData['category_id'] <= 0) {
        $errors[] = 'Vui lòng chọn danh mục xe.';
    }

    if ($formData['brand_id'] === '' || (int) $formData['brand_id'] <= 0) {
        $errors[] = 'Vui lòng chọn thương hiệu.';
    }

    if ($formData['price'] === '') {
        $errors[] = 'Vui lòng nhập giá bán.';
    } elseif (!is_numeric(str_replace([',', '.'], '', $formData['price'])) && !is_numeric($formData['price'])) {
        $errors[] = 'Giá bán phải là số hợp lệ.';
    }

    if (!in_array($formData['condition_status'], ['new', 'like_new', 'used'], true)) {
        $formData['condition_status'] = 'used';
    }

    $normalizedPrice = (float) preg_replace('/[^\d.]/', '', str_replace(',', '', $formData['price']));
    if ($normalizedPrice <= 0) {
        $errors[] = 'Giá bán phải lớn hơn 0.';
    }

    if ($formData['description'] === '' && $formData['short_description'] === '') {
        $errors[] = 'Vui lòng nhập mô tả cho tin đăng.';
    }

    if (empty($errors)) {
        $slug = getUniqueBikeSlug($conn, $formData['title']);
        $description = $formData['description'] !== '' ? $formData['description'] : $formData['short_description'];

        $insertSql = "
            INSERT INTO bikes (
                seller_id,
                category_id,
                brand_id,
                title,
                slug,
                description,
                price,
                condition_status,
                frame_size,
                wheel_size,
                color,
                location,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ";
        $insertStmt = $conn->prepare($insertSql);

        if ($insertStmt) {
            $categoryId = (int) $formData['category_id'];
            $brandId = (int) $formData['brand_id'];
            $insertStmt->bind_param(
                'iiisssdsssss',
                $sellerId,
                $categoryId,
                $brandId,
                $formData['title'],
                $slug,
                $description,
                $normalizedPrice,
                $formData['condition_status'],
                $formData['frame_size'],
                $formData['wheel_size'],
                $formData['color'],
                $formData['location']
            );

            if ($insertStmt->execute()) {
                $bikeId = (int) $insertStmt->insert_id;
                $uploadDir = __DIR__ . '/../uploads/bikes';
                $webPrefix = 'uploads/bikes';
                $uploadedImages = [];

                if (isset($_FILES['main_image'])) {
                    $mainImagePath = uploadBikeImage($_FILES['main_image'], $uploadDir, $webPrefix);
                    if ($mainImagePath) {
                        $uploadedImages[] = [
                            'path' => $mainImagePath,
                            'is_primary' => 1,
                        ];
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
                            $uploadedImages[] = [
                                'path' => $imagePath,
                                'is_primary' => 0,
                            ];
                        }
                    }
                }

                if (!empty($uploadedImages)) {
                    $imageSql = "INSERT INTO bike_images (bike_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)";
                    $imageStmt = $conn->prepare($imageSql);

                    if ($imageStmt) {
                        $sortOrder = 1;
                        foreach ($uploadedImages as $image) {
                            $isPrimary = (int) $image['is_primary'];
                            $imagePath = $image['path'];
                            $imageStmt->bind_param('isii', $bikeId, $imagePath, $isPrimary, $sortOrder);
                            $imageStmt->execute();
                            $sortOrder++;
                        }
                        $imageStmt->close();
                    }
                }

                $success = 'Đăng tin thành công. Tin đăng của bạn đang chờ kiểm duyệt.';
                $formData = [
                    'title' => '',
                    'brand_id' => '',
                    'category_id' => '',
                    'price' => '',
                    'condition_status' => 'new',
                    'location' => '',
                    'year' => '',
                    'frame_size' => '',
                    'wheel_size' => '',
                    'frame_material' => '',
                    'drivetrain' => '',
                    'brake_type' => '',
                    'color' => '',
                    'short_description' => '',
                    'description' => '',
                    'seller_name' => $sellerName,
                    'seller_phone' => $sellerPhone,
                    'seller_email' => $sellerEmail,
                    'contact_method' => 'phone',
                ];
            } else {
                $errors[] = 'Không thể tạo tin đăng lúc này. Vui lòng thử lại sau.';
            }

            $insertStmt->close();
        } else {
            $errors[] = 'Không thể chuẩn bị câu lệnh lưu tin đăng.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bike Marketplace | Đăng tin bán xe</title>
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
                    <a href="<?= e(getUserHomeLink($userRole)) ?>" class="btn btn-outline-dark"><?= e($sellerName) ?></a>
                    <a href="../logout.php" class="btn btn-success">Đăng xuất</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <section class="container">
            <div class="page-hero-box">
                <div class="breadcrumb-note"><i class="bi bi-house-door"></i> Trang chủ <span>/</span> Người bán <span>/</span> Đăng tin xe</div>
                <h1 class="section-title text-white mb-2">Đăng tin bán xe</h1>
                <p class="mb-0 text-white-50">Tạo bài đăng chuyên nghiệp để tiếp cận người mua nhanh hơn.</p>
            </div>
        </section>

        <section class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <div class="form-card">
                        <h2 class="section-title fs-2 mb-2">Thông tin xe đạp</h2>
                        <p class="section-subtitle mb-0">Điền đầy đủ thông tin để tin đăng của bạn rõ ràng, đáng tin cậy và dễ tiếp cận hơn với người mua.</p>

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

                        <form class="mt-4" method="post" action="add-bike.php" enctype="multipart/form-data">
                            <div class="section-block mt-0 pt-0 border-0">
                                <h3 class="section-heading">Thông tin cơ bản</h3>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Tên xe</label>
                                        <input type="text" name="title" class="form-control" placeholder="Ví dụ: Trek Domane SL 6" value="<?= e($formData['title']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Thương hiệu</label>
                                        <select name="brand_id" class="form-select">
                                            <option value="">Chọn thương hiệu</option>
                                            <?php foreach ($brands as $brand): ?>
                                                <option value="<?= e((int) $brand['id']) ?>" <?= (int) $formData['brand_id'] === (int) $brand['id'] ? 'selected' : '' ?>>
                                                    <?= e($brand['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Danh mục xe</label>
                                        <select name="category_id" class="form-select">
                                            <option value="">Chọn danh mục</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= e((int) $category['id']) ?>" <?= (int) $formData['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                                    <?= e($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Giá bán</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-cash-stack"></i></span>
                                            <input type="text" name="price" class="form-control" placeholder="Nhập giá bán dự kiến" value="<?= e($formData['price']) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Tình trạng</label>
                                        <select name="condition_status" class="form-select">
                                            <option value="new" <?= $formData['condition_status'] === 'new' ? 'selected' : '' ?>>Mới</option>
                                            <option value="like_new" <?= $formData['condition_status'] === 'like_new' ? 'selected' : '' ?>>Đã qua sử dụng - Rất tốt</option>
                                            <option value="used" <?= $formData['condition_status'] === 'used' ? 'selected' : '' ?>>Đã qua sử dụng - Tốt</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Khu vực</label>
                                        <input type="text" name="location" class="form-control" placeholder="Ví dụ: TP. Hồ Chí Minh" value="<?= e($formData['location']) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Năm sản xuất</label>
                                        <input type="number" name="year" class="form-control" placeholder="Ví dụ: 2023" value="<?= e($formData['year']) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="section-block">
                                <h3 class="section-heading">Thông số kỹ thuật</h3>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Kích thước khung</label>
                                        <input type="text" name="frame_size" class="form-control" placeholder="Ví dụ: 54 cm" value="<?= e($formData['frame_size']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Kích thước bánh xe</label>
                                        <input type="text" name="wheel_size" class="form-control" placeholder="Ví dụ: 700C" value="<?= e($formData['wheel_size']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Chất liệu khung</label>
                                        <input type="text" name="frame_material" class="form-control" placeholder="Ví dụ: Carbon, Nhôm" value="<?= e($formData['frame_material']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Hệ thống truyền động</label>
                                        <input type="text" name="drivetrain" class="form-control" placeholder="Ví dụ: Shimano 105" value="<?= e($formData['drivetrain']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Loại phanh</label>
                                        <input type="text" name="brake_type" class="form-control" placeholder="Ví dụ: Phanh đĩa thủy lực" value="<?= e($formData['brake_type']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Màu sắc</label>
                                        <input type="text" name="color" class="form-control" placeholder="Ví dụ: Đen nhám" value="<?= e($formData['color']) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="section-block">
                                <h3 class="section-heading">Mô tả</h3>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Mô tả ngắn</label>
                                        <input type="text" name="short_description" class="form-control" placeholder="Một dòng mô tả nổi bật về chiếc xe của bạn" value="<?= e($formData['short_description']) ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Mô tả chi tiết</label>
                                        <textarea name="description" class="form-control" placeholder="Mô tả tình trạng xe, lịch sử sử dụng, điểm nổi bật, phụ kiện đi kèm và lý do bán..."><?= e($formData['description']) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="section-block">
                                <h3 class="section-heading">Hình ảnh</h3>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="upload-box">
                                            <i class="bi bi-cloud-arrow-up fs-1 text-success mb-3"></i>
                                            <strong>Tải ảnh chính của xe</strong>
                                            <p class="mb-0 mt-2">Kéo thả ảnh vào đây hoặc chọn ảnh từ thiết bị của bạn.</p>
                                            <input type="file" name="main_image" class="form-control mt-3" accept="image/*">
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="upload-thumb">
                                            <i class="bi bi-image fs-3 mb-2 text-success"></i>
                                            <span>Ảnh phụ 1</span>
                                            <input type="file" name="additional_images[]" class="form-control mt-2" accept="image/*">
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="upload-thumb">
                                            <i class="bi bi-image fs-3 mb-2 text-success"></i>
                                            <span>Ảnh phụ 2</span>
                                            <input type="file" name="additional_images[]" class="form-control mt-2" accept="image/*">
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="upload-thumb">
                                            <i class="bi bi-image fs-3 mb-2 text-success"></i>
                                            <span>Ảnh phụ 3</span>
                                            <input type="file" name="additional_images[]" class="form-control mt-2" accept="image/*">
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="upload-thumb">
                                            <i class="bi bi-image fs-3 mb-2 text-success"></i>
                                            <span>Ảnh phụ 4</span>
                                            <input type="file" name="additional_images[]" class="form-control mt-2" accept="image/*">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <p class="text-muted mb-0">Khuyến nghị sử dụng ảnh rõ nét, đủ sáng và chụp nhiều góc để tăng độ tin cậy cho tin đăng.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="section-block">
                                <h3 class="section-heading">Thông tin liên hệ</h3>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Tên người bán</label>
                                        <input type="text" name="seller_name" class="form-control" placeholder="Nhập tên hiển thị" value="<?= e($formData['seller_name']) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Số điện thoại</label>
                                        <input type="tel" name="seller_phone" class="form-control" placeholder="Nhập số điện thoại" value="<?= e($formData['seller_phone']) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Email</label>
                                        <input type="email" name="seller_email" class="form-control" placeholder="Nhập email liên hệ" value="<?= e($formData['seller_email']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Phương thức liên hệ ưu tiên</label>
                                        <select name="contact_method" class="form-select">
                                            <option value="phone" <?= $formData['contact_method'] === 'phone' ? 'selected' : '' ?>>Điện thoại</option>
                                            <option value="email" <?= $formData['contact_method'] === 'email' ? 'selected' : '' ?>>Email</option>
                                            <option value="both" <?= $formData['contact_method'] === 'both' ? 'selected' : '' ?>>Cả hai</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="action-row">
                                <a href="my-bikes.php" class="btn btn-outline-dark">Quay lại</a>
                                <button type="submit" class="btn btn-success">Đăng tin ngay</button>
                                <a href="../bikes.php" class="btn btn-outline-success">Xem trước</a>
                            </div>
                        </form>
                    </div>

                    <div class="policy-card mt-4">
                        <h3 class="section-heading">Chính sách đăng tin</h3>
                        <p class="mb-0">Tin đăng cần phản ánh đúng tình trạng thực tế của xe, sử dụng hình ảnh rõ ràng và cung cấp thông tin trung thực. Nền tảng có thể kiểm duyệt nội dung để đảm bảo chất lượng và trải nghiệm chung cho người mua.</p>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="sidebar-card">
                        <h3 class="section-heading">Mẹo đăng tin hiệu quả</h3>
                        <ul class="tip-list">
                            <li><i class="bi bi-check-circle-fill"></i><span>Chọn ảnh rõ nét, đủ sáng và chụp nhiều góc khác nhau.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Ghi đúng tình trạng xe để tăng độ tin cậy với người mua.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Mô tả trung thực lịch sử sử dụng và phụ kiện đi kèm.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Đặt giá hợp lý theo tình trạng và thương hiệu của xe.</span></li>
                            <li><i class="bi bi-check-circle-fill"></i><span>Bổ sung thông số kỹ thuật để người xem dễ so sánh.</span></li>
                        </ul>
                    </div>

                    <div class="sidebar-card mt-4">
                        <h3 class="section-heading">Xem nhanh tin đăng</h3>
                        <div class="preview-box">
                            <div class="preview-image">
                                <div class="text-center">
                                    <i class="bi bi-image fs-1 d-block mb-2"></i>
                                    <span>Ảnh xem trước</span>
                                </div>
                            </div>
                            <div class="preview-content">
                                <div class="preview-title"><?= e($formData['title'] !== '' ? $formData['title'] : 'Tên xe sẽ hiển thị tại đây') ?></div>
                                <div class="price mb-2"><?= e($formData['price'] !== '' ? $formData['price'] : 'Giá bán') ?></div>
                                <div class="preview-meta mb-1">Tình trạng: <?= e($formData['condition_status'] !== '' ? getConditionLabel($formData['condition_status']) : 'Chưa cập nhật') ?></div>
                                <div class="preview-meta mb-2">Khu vực: <?= e($formData['location'] !== '' ? $formData['location'] : 'Chưa cập nhật') ?></div>
                                <p class="preview-meta mb-0">Bản xem trước sẽ phản ánh thông tin bạn đang nhập để dễ kiểm tra trước khi đăng.</p>
                            </div>
                        </div>
                    </div>
                    <div class="sidebar-card support-card mt-4">
                        <h3 class="section-heading">Cần hỗ trợ?</h3>
                        <p class="mb-2">Liên hệ đội ngũ hỗ trợ nếu bạn cần trợ giúp khi tạo tin đăng hoặc chuẩn bị hình ảnh sản phẩm.</p>
                        <p class="mb-2"><i class="bi bi-envelope me-2"></i> seller-support@bikemarketplace.com</p>
                        <p class="mb-3"><i class="bi bi-telephone me-2"></i> 1900 5678</p>
                        <a href="#contact" class="btn btn-outline-success">Liên hệ hỗ trợ</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="container">
            <div class="cta-band">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <h2 class="fw-bold mb-2">Tăng cơ hội bán xe với tin đăng chất lượng</h2>
                        <p class="mb-0">Hoàn thiện thông tin chi tiết để người mua dễ dàng tin tưởng và liên hệ.</p>
                    </div>
                    <div class="col-lg-4 d-flex flex-column flex-sm-row gap-3 justify-content-lg-end">
                        <a href="../bikes.php" class="btn btn-warning text-dark">Xem xe đang bán</a>
                        <a href="#contact" class="btn btn-outline-light">Liên hệ đội ngũ hỗ trợ</a>
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
                    <p>Nền tảng mua bán xe đạp hiện đại giúp người bán đăng tin chuyên nghiệp và tiếp cận người mua nhanh chóng hơn.</p>
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
                <small>&copy; 2026 Bike Marketplace. Trang đăng tin bán xe được xây dựng với PHP, CSS, Bootstrap 5 và Bootstrap Icons.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
