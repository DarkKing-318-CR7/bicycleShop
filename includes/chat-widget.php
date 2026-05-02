<?php
$chatUser = function_exists('currentUser') ? currentUser() : null;
$chatUserName = $chatUser['full_name'] ?? 'Khách';
$chatUserRole = $chatUser['role'] ?? 'guest';
$chatPageName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$chatBike = in_array($chatPageName, ['bike-detail.php', 'checkout.php'], true) && isset($bike) && is_array($bike)
    ? $bike
    : [];
$chatContext = [
    'userName' => $chatUserName,
    'role' => $chatUserRole,
    'bikeTitle' => (string) ($chatBike['title'] ?? ''),
    'sellerName' => (string) ($chatBike['seller_name'] ?? ''),
];
?>
<div
    class="chat-widget"
    data-chat-widget
    data-chat-context='<?= e(json_encode($chatContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
>
    <button class="chat-launcher" type="button" data-chat-toggle aria-label="Mở hộp chat" aria-expanded="false">
        <i class="bi bi-chat-dots-fill"></i>
        <span class="chat-launcher-badge" data-chat-badge>1</span>
    </button>

    <section class="chat-panel" data-chat-panel aria-label="Hộp chat hỗ trợ">
        <header class="chat-header">
            <div>
                <span class="chat-kicker">Bike Marketplace</span>
                <h2>Hỗ trợ nhanh</h2>
            </div>
            <button class="chat-icon-button" type="button" data-chat-close aria-label="Đóng hộp chat">
                <i class="bi bi-x-lg"></i>
            </button>
        </header>

        <div class="chat-status">
            <span></span>
            Trực tuyến 8:00 - 20:00
        </div>

        <div class="chat-messages" data-chat-messages></div>

        <div class="chat-quick-actions" aria-label="Câu hỏi nhanh">
            <button type="button" data-chat-prompt="Tôi muốn tư vấn chọn xe phù hợp">Tư vấn chọn xe</button>
            <button type="button" data-chat-prompt="Tôi muốn liên hệ người bán">Liên hệ người bán</button>
            <button type="button" data-chat-prompt="Tôi muốn đăng bán xe">Đăng bán xe</button>
        </div>

        <form class="chat-form" data-chat-form>
            <input
                type="text"
                data-chat-input
                autocomplete="off"
                maxlength="180"
                placeholder="Nhập tin nhắn..."
                aria-label="Nhập tin nhắn"
            >
            <button type="submit" aria-label="Gửi tin nhắn">
                <i class="bi bi-send-fill"></i>
            </button>
        </form>
    </section>
</div>
