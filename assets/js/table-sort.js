(function () {
    const collator = new Intl.Collator(undefined, {
        numeric: true,
        sensitivity: 'base'
    });

    function animateRows(rows, firstPositions) {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        rows.forEach((row) => {
            const first = firstPositions.get(row);
            const last = row.getBoundingClientRect();
            const deltaY = first.top - last.top;

            if (deltaY !== 0) {
                row.animate(
                    [{ transform: `translateY(${deltaY}px)` }, { transform: 'translateY(0)' }],
                    { duration: 280, easing: 'cubic-bezier(0.2, 0, 0, 1)' }
                );
            }
        });
    }

    function sortableRows(tbody) {
        return Array.from(tbody.rows).filter((row) => !row.querySelector('[colspan]'));
    }

    function moveRows(tbody, rows) {
        const firstPositions = new Map(rows.map((row) => [row, row.getBoundingClientRect()]));
        rows.forEach((row) => tbody.appendChild(row));
        animateRows(rows, firstPositions);
    }

    function isNumericColumn(label) {
        const normalizedLabel = label.trim().toLowerCase().replace(/\s+/g, ' ');
        return /^id$/.test(normalizedLabel)
            || /\b(quantity|qty|stock|price|total|amount|refund)\b/.test(normalizedLabel);
    }

    function normalizeLabel(label) {
        return label.trim().toLowerCase().replace(/\s+/g, ' ');
    }

    function isExcludedHeader(header, label) {
        const normalizedLabel = normalizeLabel(label);
        const normalizedClassName = typeof header.className === 'string'
            ? header.className.toLowerCase()
            : '';

        return !normalizedLabel
            || /^(select|action|actions|checkbox)$/.test(normalizedLabel)
            || /^(#|sr\s*#?|s\.?\s*no\.?|serial(?:\s*(?:no\.?|number|#))?)$/.test(normalizedLabel)
            || /\bstatus\b/.test(normalizedLabel)
            || /\b(image|images|avatar|avatars|photo|photos|picture|pictures)\b/.test(normalizedLabel)
            || /(?:select|checkbox)/.test(normalizedClassName)
            || header.hasAttribute('data-sort-disabled')
            || Boolean(header.querySelector('input, button, select, img'));
    }

    function numericValue(value) {
        const normalized = value
            .trim()
            .replace(/,/g, '')
            .replace(/^[^\d+-]*/, '')
            .replace(/[^\d.]+$/, '');

        if (!/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(normalized)) {
            return null;
        }

        const number = Number(normalized);
        return Number.isFinite(number) ? number : null;
    }

    function compareCells(leftCell, rightCell, label) {
        const left = leftCell ? leftCell.textContent.trim().replace(/\s+/g, ' ') : '';
        const right = rightCell ? rightCell.textContent.trim().replace(/\s+/g, ' ') : '';
        const normalizedLabel = label.toLowerCase();

        if (/\b(date|joined|created|added)\b/.test(normalizedLabel)) {
            const leftDate = Date.parse(left);
            const rightDate = Date.parse(right);
            const leftIsDate = Number.isFinite(leftDate);
            const rightIsDate = Number.isFinite(rightDate);

            if (leftIsDate !== rightIsDate) {
                return leftIsDate ? -1 : 1;
            }
            if (leftIsDate && leftDate !== rightDate) {
                return leftDate - rightDate;
            }
        }

        if (isNumericColumn(label)) {
            const leftNumber = numericValue(left);
            const rightNumber = numericValue(right);

            if (leftNumber !== null || rightNumber !== null) {
                if (leftNumber === null) return 1;
                if (rightNumber === null) return -1;
                if (leftNumber !== rightNumber) return leftNumber - rightNumber;
            }
        }

        return collator.compare(left, right);
    }

    function sortByColumn(table, header, columnIndex, direction) {
        const tbody = table.tBodies[0];
        if (!tbody) {
            return;
        }

        const rows = sortableRows(tbody);
        if (rows.length < 2) {
            return;
        }

        const multiplier = direction === 'asc' ? 1 : -1;
        const orderedRows = rows
            .map((row, index) => ({ row, index }))
            .sort((left, right) => {
                const comparison = compareCells(
                    left.row.cells[columnIndex],
                    right.row.cells[columnIndex],
                    header.dataset.sortLabel || header.textContent.trim()
                );
                return comparison === 0
                    ? left.index - right.index
                    : comparison * multiplier;
            })
            .map((item) => item.row);

        moveRows(tbody, orderedRows);
    }

    function initializeColumnSorting() {
        document.querySelectorAll('table[data-sortable-table]').forEach((table) => {
            const headerRow = table.tHead && table.tHead.rows[0];
            if (!headerRow) {
                return;
            }

            const allowedColumns = table.dataset.sortColumns
                ? new Set(table.dataset.sortColumns.split(',').map(normalizeLabel))
                : null;

            Array.from(headerRow.cells).forEach((header, columnIndex) => {
                const label = header.textContent.trim().replace(/\s+/g, ' ');
                if (
                    isExcludedHeader(header, label)
                    || (allowedColumns && !allowedColumns.has(normalizeLabel(label)))
                    || header.querySelector('.table-column-sort-trigger')
                ) {
                    return;
                }

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'table-column-sort-trigger';
                button.dataset.sortDirection = 'none';
                button.setAttribute('aria-label', `Sort by ${label} ascending`);
                button.title = `Sort by ${label} ascending`;

                const icon = document.createElement('i');
                icon.className = 'bi bi-arrow-down-up';
                icon.setAttribute('aria-hidden', 'true');
                button.appendChild(icon);
                header.appendChild(button);

                const toggleSort = () => {
                    const direction = button.dataset.sortDirection === 'asc' ? 'desc' : 'asc';
                    button.dataset.sortDirection = direction;
                    header.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');

                    Array.from(headerRow.cells).forEach((otherHeader) => {
                        if (otherHeader !== header) {
                            otherHeader.setAttribute('aria-sort', 'none');
                        }
                    });

                    icon.className = direction === 'asc' ? 'bi bi-sort-up-alt' : 'bi bi-sort-down-alt';
                    const nextDirection = direction === 'asc' ? 'descending' : 'ascending';
                    button.setAttribute('aria-label', `Sorted ${direction} by ${label}. Sort ${nextDirection}.`);
                    button.title = `Sorted ${direction} by ${label}. Click to sort ${nextDirection}.`;
                    sortByColumn(table, header, columnIndex, direction);
                };

                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    toggleSort();
                });
                header.classList.add('table-column-sortable');
                header.addEventListener('click', (event) => {
                    if (event.target instanceof Element && event.target.closest('a, button, input, select')) {
                        return;
                    }
                    toggleSort();
                });
            });
        });
    }

    document.querySelectorAll('[data-sort-toggle]').forEach((button) => {
        const table = document.getElementById(button.dataset.sortTarget);
        const tbody = table && table.tBodies[0];

        if (!tbody) {
            return;
        }

        let reversed = false;

        button.addEventListener('click', () => {
            const rows = sortableRows(tbody);
            if (rows.length < 2) {
                return;
            }

            const orderedRows = rows.slice().reverse();
            moveRows(tbody, orderedRows);

            orderedRows.forEach((row, index) => {
                const serialCell = Array.from(row.cells).find(
                    (cell) => !cell.querySelector('input, button, a')
                );
                if (serialCell) {
                    serialCell.textContent = String(index + 1);
                }
            });

            reversed = !reversed;
            const nextAction = reversed ? 'Sort Descending' : 'Sort Ascending';
            const icon = button.querySelector('i');
            button.title = nextAction;
            button.setAttribute('aria-label', nextAction);
            button.setAttribute('aria-pressed', String(reversed));
            if (icon) {
                icon.className = reversed ? 'bi bi-sort-down-alt' : 'bi bi-sort-up-alt';
            }
        });
    });

    initializeColumnSorting();
})();
