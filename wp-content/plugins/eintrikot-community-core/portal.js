(() => {
    let next = Date.now();
    const list = document.getElementById('stations'),
        add = document.getElementById('add-station'),
        template = document.getElementById('station-template');
    if (add && list && template) {
        add.addEventListener('click', () => {
            if (list.children.length >= 30) return;
            list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(next++)));
            list.lastElementChild.querySelector('input')?.focus();
        });
        list.addEventListener('click', (e) => {
            if (e.target.closest('.remove-station')) e.target.closest('.station').remove();
        });
    }
    const form = document.querySelector('.profile-form');
    if (form) {
        let dirty = false;
        form.addEventListener('input', () => (dirty = true));
        form.addEventListener('submit', () => (dirty = false));
        window.addEventListener('beforeunload', (e) => {
            if (dirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }
})();
