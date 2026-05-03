<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'groups' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

function globalSearchText($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function globalSearchFetch(mysqli $conn, string $sql, string $types, array $params): array
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'title' => globalSearchText($row['title'] ?? ''),
                'meta' => globalSearchText($row['meta'] ?? ''),
                'url' => globalSearchText($row['url'] ?? '#'),
            ];
        }
    }

    $stmt->close();

    return $items;
}

$query = trim((string) ($_GET['q'] ?? ''));

if ($query === '') {
    echo json_encode(['success' => true, 'groups' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$keyword = '%' . $query . '%';

$groups = [
    [
        'label' => 'Tin đăng',
        'items' => globalSearchFetch(
            $conn,
            "
                SELECT
                    title,
                    CONCAT(FORMAT(price, 0), 'đ · #', id) AS meta,
                    CONCAT('bikes.php?id=', id) AS url
                FROM bikes
                WHERE title LIKE ?
                ORDER BY created_at DESC
                LIMIT 5
            ",
            's',
            [$keyword]
        ),
    ],
    [
        'label' => 'Người dùng',
        'items' => globalSearchFetch(
            $conn,
            "
                SELECT
                    full_name AS title,
                    CONCAT(email, ' · #', id) AS meta,
                    CONCAT('users.php?id=', id) AS url
                FROM users
                WHERE full_name LIKE ? OR email LIKE ?
                ORDER BY created_at DESC
                LIMIT 5
            ",
            'ss',
            [$keyword, $keyword]
        ),
    ],
    [
        'label' => 'Đơn hàng',
        'items' => globalSearchFetch(
            $conn,
            "
                SELECT
                    CONCAT('Đơn hàng #', id) AS title,
                    CONCAT(order_code, ' · ', FORMAT(offered_price, 0), 'đ') AS meta,
                    CONCAT('orders.php?id=', id) AS url
                FROM orders
                WHERE CAST(id AS CHAR) LIKE ? OR order_code LIKE ?
                ORDER BY created_at DESC
                LIMIT 5
            ",
            'ss',
            [$keyword, $keyword]
        ),
    ],
    [
        'label' => 'Thương hiệu',
        'items' => globalSearchFetch(
            $conn,
            "
                SELECT
                    name AS title,
                    CONCAT('Thương hiệu #', id) AS meta,
                    CONCAT('brands.php?id=', id) AS url
                FROM brands
                WHERE name LIKE ?
                ORDER BY created_at DESC
                LIMIT 5
            ",
            's',
            [$keyword]
        ),
    ],
];

echo json_encode(['success' => true, 'groups' => $groups], JSON_UNESCAPED_UNICODE);
