<?php

function supportMessageEscape($value): string
{
    if (function_exists('e')) {
        return e($value);
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ensureSupportMessagesTable(mysqli $conn): bool
{
    static $checked = false;
    static $available = false;

    if ($checked) {
        return $available;
    }

    $checked = true;

    try {
        $sql = "
            CREATE TABLE IF NOT EXISTS support_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                name VARCHAR(150) NULL,
                email VARCHAR(150) NULL,
                phone VARCHAR(30) NULL,
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                status ENUM('new','read','resolved') DEFAULT 'new',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_support_messages_status (status),
                KEY idx_support_messages_created_at (created_at),
                KEY idx_support_messages_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $available = (bool) $conn->query($sql);
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function saveSupportMessageFromContactPost(mysqli $conn, array $post, ?array $user = null): array
{
    if (!ensureSupportMessagesTable($conn)) {
        return [
            'success' => false,
            'message' => 'Chưa thể lưu liên hệ. Vui lòng thử lại sau.',
        ];
    }

    $userId = isset($user['id']) ? (int) $user['id'] : null;
    $name = trim((string) ($post['contact_name'] ?? ''));
    $email = trim((string) ($post['contact_email'] ?? ''));
    $phone = trim((string) ($post['contact_phone'] ?? ''));
    $subject = trim((string) ($post['contact_topic'] ?? $post['contact_subject'] ?? ''));
    $message = trim((string) ($post['contact_message'] ?? ''));
    $status = 'new';

    if ($name === '' && !empty($user['full_name'])) {
        $name = (string) $user['full_name'];
    }

    if ($email === '' && !empty($user['email'])) {
        $email = (string) $user['email'];
    }

    if ($phone === '' && !empty($user['phone'])) {
        $phone = (string) $user['phone'];
    }

    if ($subject === '') {
        $subject = 'Yêu cầu hỗ trợ';
    }

    if ($name === '' || $email === '' || $message === '') {
        return [
            'success' => false,
            'message' => 'Vui lòng nhập họ tên, email và nội dung cần hỗ trợ.',
        ];
    }

    $sql = "
        INSERT INTO support_messages (user_id, name, email, phone, subject, message, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [
            'success' => false,
            'message' => 'Không thể chuẩn bị lưu liên hệ.',
        ];
    }

    $stmt->bind_param('issssss', $userId, $name, $email, $phone, $subject, $message, $status);
    $success = $stmt->execute();
    $stmt->close();

    return [
        'success' => $success,
        'message' => $success
            ? 'Cảm ơn bạn đã liên hệ. Bike Marketplace sẽ phản hồi trong thời gian sớm nhất.'
            : 'Không thể lưu liên hệ. Vui lòng thử lại sau.',
    ];
}

function fetchRecentSupportMessages(mysqli $conn, int $limit = 5): array
{
    if (!ensureSupportMessagesTable($conn)) {
        return [];
    }

    $limit = max(1, min($limit, 5));
    $status = 'new';
    $sql = "
        SELECT sm.id, sm.name, sm.email, sm.phone, sm.subject, sm.message, sm.created_at,
               u.full_name AS user_name, u.email AS user_email
        FROM support_messages sm
        LEFT JOIN users u ON u.id = sm.user_id
        WHERE sm.status = ?
        ORDER BY sm.created_at DESC
        LIMIT ?
    ";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('si', $status, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
    }

    $stmt->close();

    return $messages;
}

function hasNewSupportMessages(mysqli $conn): bool
{
    if (!ensureSupportMessagesTable($conn)) {
        return false;
    }

    $status = 'new';
    $sql = "SELECT COUNT(*) AS total FROM support_messages WHERE status = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['total'] ?? 0) > 0;
}

function formatSupportMessageTime(?string $createdAt): string
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

function supportMessageSenderName(array $message): string
{
    $name = trim((string) ($message['name'] ?? ''));

    if ($name !== '') {
        return $name;
    }

    $userName = trim((string) ($message['user_name'] ?? ''));

    if ($userName !== '') {
        return $userName;
    }

    $email = trim((string) ($message['email'] ?? ''));

    if ($email !== '') {
        return $email;
    }

    $userEmail = trim((string) ($message['user_email'] ?? ''));

    if ($userEmail !== '') {
        return $userEmail;
    }

    return 'Người dùng';
}

function shortSupportMessageText(string $message, int $length = 90): string
{
    $message = trim(preg_replace('/\s+/', ' ', $message));

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($message, 'UTF-8') > $length
            ? mb_substr($message, 0, $length, 'UTF-8') . '...'
            : $message;
    }

    return strlen($message) > $length ? substr($message, 0, $length) . '...' : $message;
}

function renderAdminSupportDropdown(mysqli $conn): void
{
    $messages = fetchRecentSupportMessages($conn, 5);
    $hasNew = hasNewSupportMessages($conn);
    ?>
    <div class="admin-notification admin-support-dropdown" data-admin-notification>
        <button
            class="admin-icon-btn admin-notification-toggle"
            type="button"
            aria-label="Tin nhắn hỗ trợ"
            aria-expanded="false"
        >
            <i class="bi bi-chat-dots"></i>
            <?php if ($hasNew): ?>
                <span class="admin-notification-dot"></span>
            <?php endif; ?>
        </button>
        <div class="admin-notification-dropdown" data-notification-dropdown>
            <div class="admin-notification-head">Tin nhắn hỗ trợ</div>
            <?php if (empty($messages)): ?>
                <div class="admin-notification-empty">Không có tin nhắn mới</div>
            <?php else: ?>
                <div class="admin-notification-list">
                    <?php foreach ($messages as $message): ?>
                        <a class="admin-notification-item" href="support.php?id=<?= supportMessageEscape($message['id'] ?? '') ?>">
                            <span class="admin-notification-icon">
                                <i class="bi bi-chat-left-text"></i>
                            </span>
                            <span class="admin-notification-content">
                                <strong><?= supportMessageEscape(supportMessageSenderName($message)) ?></strong>
                                <span><?= supportMessageEscape($message['subject'] ?? '') ?></span>
                                <span><?= supportMessageEscape(shortSupportMessageText($message['message'] ?? '', 58)) ?></span>
                                <time datetime="<?= supportMessageEscape($message['created_at'] ?? '') ?>">
                                    <?= supportMessageEscape(formatSupportMessageTime($message['created_at'] ?? null)) ?>
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
