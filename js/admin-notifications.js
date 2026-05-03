(function () {
    var roots = document.querySelectorAll('[data-admin-notification]');

    if (!roots.length) {
        return;
    }

    function closeAll(exceptRoot) {
        roots.forEach(function (root) {
            if (root === exceptRoot) {
                return;
            }

            root.classList.remove('is-open');
            var button = root.querySelector('.admin-notification-toggle');

            if (button) {
                button.setAttribute('aria-expanded', 'false');
            }
        });
    }

    roots.forEach(function (root) {
        var button = root.querySelector('.admin-notification-toggle');
        var dot = root.querySelector('[data-notification-dot]');

        if (!button) {
            return;
        }

        button.addEventListener('click', function (event) {
            event.stopPropagation();
            var isOpen = root.classList.toggle('is-open');
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            closeAll(root);

            var readUrl = button.dataset.notificationReadUrl;

            if (isOpen && dot && readUrl) {
                fetch(readUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    if (response.ok) {
                        dot.remove();
                        dot = null;
                    }
                }).catch(function () {});
            }
        });

        root.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });

    document.addEventListener('click', function () {
        closeAll(null);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAll(null);
        }
    });
})();
