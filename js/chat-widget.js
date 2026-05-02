(function () {
    const widget = document.querySelector("[data-chat-widget]");

    if (!widget) {
        return;
    }

    const toggleButton = widget.querySelector("[data-chat-toggle]");
    const closeButton = widget.querySelector("[data-chat-close]");
    const badge = widget.querySelector("[data-chat-badge]");
    const messagesEl = widget.querySelector("[data-chat-messages]");
    const form = widget.querySelector("[data-chat-form]");
    const input = widget.querySelector("[data-chat-input]");
    const promptButtons = widget.querySelectorAll("[data-chat-prompt]");
    const storageKey = "bike_marketplace_chat";
    let context = {};

    try {
        context = JSON.parse(widget.dataset.chatContext || "{}");
    } catch (error) {
        context = {};
    }

    const defaultMessages = [
        {
            from: "bot",
            text: getGreeting(),
            time: getTimeLabel(),
        },
    ];

    let messages = loadMessages();

    renderMessages();

    toggleButton.addEventListener("click", function () {
        const isOpen = widget.classList.toggle("is-open");
        toggleButton.setAttribute("aria-expanded", String(isOpen));
        badge.hidden = true;

        if (isOpen) {
            window.setTimeout(function () {
                input.focus();
            }, 150);
        }
    });

    closeButton.addEventListener("click", function () {
        widget.classList.remove("is-open");
        toggleButton.setAttribute("aria-expanded", "false");
    });

    promptButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            sendMessage(button.dataset.chatPrompt || button.textContent.trim());
        });
    });

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        sendMessage(input.value);
    });

    function sendMessage(rawText) {
        const text = rawText.trim();

        if (!text) {
            return;
        }

        addMessage("user", text);
        input.value = "";

        window.setTimeout(function () {
            addMessage("bot", buildReply(text));
        }, 450);
    }

    function addMessage(from, text) {
        messages.push({
            from,
            text,
            time: getTimeLabel(),
        });

        messages = messages.slice(-20);
        saveMessages();
        renderMessages();
    }

    function renderMessages() {
        messagesEl.innerHTML = messages
            .map(function (message) {
                return (
                    '<div class="chat-message chat-message-' +
                    escapeHtml(message.from) +
                    '">' +
                    '<p>' +
                    linkify(escapeHtml(message.text)) +
                    "</p>" +
                    '<time datetime="">' +
                    escapeHtml(message.time) +
                    "</time>" +
                    "</div>"
                );
            })
            .join("");

        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function loadMessages() {
        try {
            const stored = JSON.parse(localStorage.getItem(storageKey) || "[]");

            if (Array.isArray(stored) && stored.length > 0) {
                return stored;
            }
        } catch (error) {
            return defaultMessages;
        }

        return defaultMessages;
    }

    function saveMessages() {
        localStorage.setItem(storageKey, JSON.stringify(messages));
    }

    function getGreeting() {
        const name = context.userName && context.userName !== "Khách" ? " " + context.userName : "";

        if (context.bikeTitle) {
            return "Chào" + name + ", bạn đang xem " + context.bikeTitle + ". Mình có thể giúp hỏi người bán, tư vấn đặt mua hoặc so sánh mẫu xe này.";
        }

        if (context.role === "seller") {
            return "Chào" + name + ", mình có thể hỗ trợ quản lý tin đăng, theo dõi đơn hàng hoặc gợi ý cách đăng xe hấp dẫn hơn.";
        }

        return "Chào" + name + ", bạn cần tư vấn chọn xe, liên hệ người bán hay hỗ trợ đặt mua?";
    }

    function buildReply(text) {
        const normalized = removeVietnameseMarks(text).toLowerCase();

        if (normalized.includes("dang ban") || normalized.includes("ban xe") || normalized.includes("seller")) {
            return "Bạn có thể đăng bán xe tại /seller/add-bike.php. Nên chuẩn bị ảnh rõ, mô tả tình trạng thật kỹ, size khung, bánh xe, màu sắc và khu vực giao dịch.";
        }

        if (normalized.includes("lien he") || normalized.includes("nguoi ban") || normalized.includes("seller")) {
            if (context.sellerName) {
                return "Người bán của xe này là " + context.sellerName + ". Bạn có thể dùng nút đặt mua hoặc để lại lời nhắn, hệ thống sẽ lưu thông tin giao dịch cho hai bên.";
            }

            return "Bạn mở trang chi tiết xe rồi chọn đặt mua hoặc liên hệ hỗ trợ. Nếu cần, hãy gửi tên mẫu xe để mình gợi ý bước tiếp theo.";
        }

        if (normalized.includes("gia") || normalized.includes("mac ca") || normalized.includes("thuong luong")) {
            return "Khi thương lượng, bạn nên hỏi lịch sử sử dụng, tình trạng khung, bộ truyền động, phanh, bánh xe và phụ kiện đi kèm trước khi chốt giá.";
        }

        if (normalized.includes("chon xe") || normalized.includes("tu van") || normalized.includes("phu hop")) {
            return "Bạn cho mình biết mục đích chính: đi phố, road, địa hình hay touring. Sau đó so sánh size khung, loại bánh, tình trạng xe và ngân sách.";
        }

        if (normalized.includes("don hang") || normalized.includes("dat mua") || normalized.includes("checkout")) {
            return "Bạn có thể đặt mua từ trang chi tiết xe. Sau khi tạo đơn, vào mục đơn mua để theo dõi trạng thái xác nhận, giao dịch và hoàn tất.";
        }

        return "Mình đã ghi nhận. Bạn mô tả thêm mẫu xe, ngân sách hoặc vấn đề đang gặp nhé, mình sẽ gợi ý bước xử lý phù hợp.";
    }

    function getTimeLabel() {
        return new Intl.DateTimeFormat("vi-VN", {
            hour: "2-digit",
            minute: "2-digit",
        }).format(new Date());
    }

    function linkify(text) {
        return text.replace(/(\/[a-z0-9-_/]+\.php)/gi, function (match) {
            return '<a href="' + match + '">' + match + "</a>";
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function removeVietnameseMarks(value) {
        return value
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/đ/g, "d")
            .replace(/Đ/g, "D");
    }
})();
