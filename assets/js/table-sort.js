document.querySelectorAll('[data-sort-toggle]').forEach((button) => {
    const table = document.getElementById(button.dataset.sortTarget);
    const tbody = table?.tBodies[0];

    if (!tbody) {
        return;
    }

    let reversed = false;

    button.addEventListener('click', () => {
        const rows = Array.from(tbody.rows).filter((row) => !row.querySelector('[colspan]'));
        if (rows.length < 2) {
            return;
        }

        const firstPositions = new Map(
            rows.map((row) => [row, row.getBoundingClientRect()])
        );
        const orderedRows = rows.slice().reverse();

        orderedRows.forEach((row, index) => {
            const serialCell = Array.from(row.cells).find(
                (cell) => !cell.querySelector('input, button, a')
            );
            if (serialCell) {
                serialCell.textContent = String(index + 1);
            }
            tbody.appendChild(row);
        });

        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            orderedRows.forEach((row) => {
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
