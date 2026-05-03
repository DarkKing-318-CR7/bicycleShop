<?php

function adminNotificationEscape($value): string
{
    if (function_exists('e')) {
        return e($value);
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fetchAdminNotifications(mysqli $conn, int $limit = 10): array
{
    $limit = max(1, min($limit, 10));
    $bikeStatus = 'pending';
    $orderStatus = 'pending';
    $sql = "
        SELECT type, item_id, title, message, link, icon, created_at
        FROM (
            SELECT
                'bike' AS type,
                id AS item_id,
                'Tin mới cần duyệt' AS title,
                'Có tin đăng mới cần kiểm duyệt' AS message,
                'moderation.php' AS link,
                'bi-card-list' AS icon,
                created_at
            FROM bikes
            WHERE status = ?

            UNION ALL

            SELECT
                'order' AS type,
                id AS item_id,
                'Đơn hàng mới' AS title,
                'Có đơn hàng mới' AS message,
                'orders.php' AS link,
                'bi-receipt' AS icon,
                created_at
            FROM orders
            WHERE status = ?
        ) AS notifications
        ORDER BY created_at DESC
        LIMIT ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ssi', $bikeStatus, $orderStatus, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }

    $stmt->close();

    return $notifications;
}

function hasUnreadAdminNotifications(mysqli $conn): bool
{
    $lastCheckTime = $_SESSION['admin_notifications_last_check_time'] ?? '1970-01-01 00:00:00';
    $bikeStatus = 'pending';
    $orderStatus = 'pending';
    $sql = "
        SELECT COUNT(*) AS total
        FROM (
            SELECT id
            FROM bikes
            WHERE status = ? AND created_at > ?

            UNION ALL

            SELECT id
            FROM orders
            WHERE status = ? AND created_at > ?
        ) AS unread_notifications
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssss', $bikeStatus, $lastCheckTime, $orderStatus, $lastCheckTime);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['total'] ?? 0) > 0;
}

function markAdminNotificationsRead(): void
{
    $_SESSION['admin_notifications_last_check_time'] = date('Y-m-d H:i:s');
}

function formatAdminNotificationTime(?string $createdAt): string
{
    if (empty($createdAt)) {
        return '';
    }

    $timestamp = strtotime($createdAt);

    if (!$timestamp) {
        return $createdAt;
    }

    return date('d/m/Y H:i', $timestamp);
}

function renderAdminNotificationDropdown(mysqli $conn): void
{
    $notifications = fetchAdminNotifications($conn, 10);
    $hasUnread = hasUnreadAdminNotifications($conn);
    ?>
    <div class="admin-notification" data-admin-notification>
        <button
            class="admin-icon-btn admin-notification-toggle"
            type="button"
            aria-label="Thông báo"
            aria-expanded="false"
            data-notification-read-url="notification-read.php"
        >
            <i class="bi bi-bell"></i>
            <?php if ($hasUnread): ?>
                <span class="admin-notification-dot" data-notification-dot></span>
            <?php endif; ?>
        </button>
        <div class="admin-notification-dropdown" data-notification-dropdown>
            <div class="admin-notification-head">Thông báo</div>
            <?php if (empty($notifications)): ?>
                <div class="admin-notification-empty">Không có thông báo mới</div>
            <?php else: ?>
                <div class="admin-notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <a class="admin-notification-item" href="<?= adminNotificationEscape($notification['link'] ?? '#') ?>">
                            <span class="admin-notification-icon">
                                <i class="bi <?= adminNotificationEscape($notification['icon'] ?? 'bi-bell') ?>"></i>
                            </span>
                            <span class="admin-notification-content">
                                <strong><?= adminNotificationEscape($notification['title'] ?? '') ?></strong>
                                <span><?= adminNotificationEscape($notification['message'] ?? '') ?></span>
                                <time datetime="<?= adminNotificationEscape($notification['created_at'] ?? '') ?>">
                                    <?= adminNotificationEscape(formatAdminNotificationTime($notification['created_at'] ?? null)) ?>
                                </time>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
