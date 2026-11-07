// Tables marked data-sortable sort on a click in their header. The order is kept per table and put
// back after Livewire redraws it (results poll), since the server always sends its own order.
const sorted = new WeakMap();
const collator = new Intl.Collator(document.documentElement.lang || 'sv', { numeric: true, sensitivity: 'base' });

// A cell's data-sort (a name as last name first) or its text: 39:42 and 2:37:42 as seconds, a dash as empty.
const value = (cell) => {
    const text = (cell?.dataset.sort ?? cell?.textContent ?? '').trim().replace(/^[–-]$/, '');
    return /^\d+(:\d\d)+$/.test(text) ? text.split(':').reduce((sum, part) => sum * 60 + Number(part), 0) : text;
};

const compare = (a, b) => typeof a === 'number' && typeof b === 'number' ? a - b : collator.compare(String(a), String(b));

function apply(table) {
    const { column, desc } = sorted.get(table);
    table.querySelectorAll('thead th').forEach((th) => th.cellIndex === column ? th.setAttribute('aria-sort', desc ? 'descending' : 'ascending') : th.removeAttribute('aria-sort'));
    for (const body of table.tBodies) {
        const rows = [...body.rows].map((row) => [row, value(row.cells[column])]);
        // Empty cells last either way.
        rows.sort(([, a], [, b]) => (a === '') - (b === '') || (desc ? -1 : 1) * compare(a, b));
        body.append(...rows.map(([row]) => row));
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('table[data-sortable] thead button[data-sort]');
    if (!button) return;
    const table = button.closest('table');
    const column = button.closest('th').cellIndex;
    const before = sorted.get(table);
    sorted.set(table, { column, desc: before?.column === column && !before.desc });
    apply(table);
});

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morphed', ({ el }) => el.querySelectorAll('table[data-sortable]').forEach((table) => sorted.has(table) && apply(table)));
});
