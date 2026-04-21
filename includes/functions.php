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