<?php

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function hasRole(string $role): bool
{
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === $role;
}

function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

function baseUrl(string $path = ''): string
{
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    $path = ltrim($path, '/');

    return $base . '/' . $path;
}

function tableColumnExists(mysqli $conn, string $table, string $column): bool
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['total'] ?? 0) > 0;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect(baseUrl('login.php'));
    }
}

function requireRole(string $role): void
{
    if (!isLoggedIn()) {
        redirect(baseUrl('login.php'));
    }

    if (!hasRole($role)) {
        redirect(baseUrl('index.php'));
    }
}
