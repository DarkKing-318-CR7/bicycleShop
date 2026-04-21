<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$bikeId = filter_input(INPUT_GET, 'bike_id', FILTER_VALIDATE_INT);
$currentUser = currentUser();
$userId = (int) ($currentUser['id'] ?? 0);

if (!$bikeId || $bikeId <= 0 || $userId <= 0) {
    redirect('bikes.php');
}

$checkSql = "SELECT id FROM favorites WHERE user_id = ? AND bike_id = ? LIMIT 1";
$checkStmt = $conn->prepare($checkSql);

if ($checkStmt) {
    $checkStmt->bind_param('ii', $userId, $bikeId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $favorite = $result ? $result->fetch_assoc() : null;
    $checkStmt->close();

    if ($favorite) {
        $deleteSql = "DELETE FROM favorites WHERE user_id = ? AND bike_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);

        if ($deleteStmt) {
            $deleteStmt->bind_param('ii', $userId, $bikeId);
            $deleteStmt->execute();
            $deleteStmt->close();
        }
    } else {
        $insertSql = "INSERT INTO favorites (user_id, bike_id) VALUES (?, ?)";
        $insertStmt = $conn->prepare($insertSql);

        if ($insertStmt) {
            $insertStmt->bind_param('ii', $userId, $bikeId);
            $insertStmt->execute();
            $insertStmt->close();
        }
    }
}

$fallbackUrl = 'bike-detail.php?id=' . $bikeId;
$redirectUrl = $_SERVER['HTTP_REFERER'] ?? $fallbackUrl;

if (!is_string($redirectUrl) || trim($redirectUrl) === '') {
    $redirectUrl = $fallbackUrl;
}

$parsedUrl = parse_url($redirectUrl);

if ($parsedUrl === false) {
    $redirectUrl = $fallbackUrl;
} else {
    $host = $parsedUrl['host'] ?? '';

    if ($host !== '' && isset($_SERVER['HTTP_HOST']) && strcasecmp($host, $_SERVER['HTTP_HOST']) !== 0) {
        $redirectUrl = $fallbackUrl;
    }
}

redirect($redirectUrl);
?>
