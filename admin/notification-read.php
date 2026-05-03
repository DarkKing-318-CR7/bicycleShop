<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

markAdminNotificationsRead();

echo json_encode(['success' => true]);
