(function () {
    var roots = document.querySelectorAll('[data-global-search-root]');

    if (!roots.length) {
        return;
    }

    function closeAll(exceptRoot) {
        roots.forEach(function (root) {
            if (root === exceptRoot) {
                return;
            }

            root.classList.remove('is-open');
        });
    }

    function createItem(item) {
        var link = document.createElement('a');
        link.className = 'admin-global-search-item';
        link.href = item.url || '#';

        var title = document.createElement('strong');
        title.textContent = item.title || '';

        var meta = document.createElement('span');
        meta.textContent = item.meta || '';

        link.appendChild(title);
        link.appendChild(meta);

        return link;
    }

    function renderResults(root, groups) {
        var dropdown = root.querySelector('[data-global-search-results]');
        dropdown.innerHTML = '';

        var hasItems = false;

        groups.forEach(function (group) {
            if (!group.items || !group.items.length) {
                return;
            }

            hasItems = true;

            var section = document.createElement('section');
            section.className = 'admin-global-search-group';

            var heading = document.createElement('div');
            heading.className = 'admin-global-search-heading';
            heading.textContent = group.label || '';
            section.appendChild(heading);

            group.items.forEach(function (item) {
                section.appendChild(createItem(item));
            });

            dropdown.appendChild(section);
        });

        if (!hasItems) {
            var empty = document.createElement('div');
            empty.className = 'admin-global-search-empty';
            empty.textContent = 'Không tìm thấy dữ liệu';
            dropdown.appendChild(empty);
        }

        root.classList.add('is-open');
    }

    roots.forEach(function (root) {
        var input = root.querySelector('[data-global-search-input]');
        var debounceTimer = null;
        var controller = null;

        if (!input) {
            return;
        }

        input.addEventListener('keyup', function () {
            var keyword = input.value.trim();
            clearTimeout(debounceTimer);

            if (keyword === '') {
                root.classList.remove('is-open');
                return;
            }

            debounceTimer = setTimeout(function () {
                if (controller) {
                    controller.abort();
                }

                controller = new AbortController();

                fetch(input.dataset.globalSearchUrl + '?q=' + encodeURIComponent(keyword), {
                    signal: controller.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Search failed');
                        }

                        return response.json();
                    })
                    .then(function (data) {
                        renderResults(root, data.groups || []);
                    })
                    .catch(function (error) {
                        if (error.name !== 'AbortError') {
                            renderResults(root, []);
                        }
                    });
            }, 300);
        });

        input.addEventListener('focus', function () {
            if (input.value.trim() !== '') {
                root.classList.add('is-open');
            }
        });

        root.addEventListener('click', function (event) {
            event.stopPropagation();
            closeAll(root);
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
