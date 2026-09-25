/* Mitglied werden: Hinweisfenster vor dem MeinVerein-Antrag und anonyme Zählung. */
(() => {
    const dialog = document.querySelector('[data-join-dialog]');
    const open = document.querySelector('[data-join-open]');
    if (!dialog || !open || typeof dialog.showModal !== 'function') return;

    const count = (step) => {
        const data = new FormData();
        data.append('action', 'et_join_count');
        data.append('step', step);
        if (navigator.sendBeacon) navigator.sendBeacon(window.eintrikotJoin.ajax, data);
    };
    const show = (step) => {
        dialog.querySelectorAll('[data-join-step]').forEach((el) => {
            el.hidden = el.dataset.joinStep !== step;
        });
    };

    open.addEventListener('click', (e) => {
        e.preventDefault();
        show('info');
        dialog.showModal();
        dialog.scrollTop = 0;
        dialog.querySelector('#et-join-title')?.focus();
        count('open');
    });
    dialog.querySelector('[data-join-go]')?.addEventListener('click', () => {
        count('go');
        show('done');
        dialog.querySelector('[data-join-step="done"] h2')?.focus();
    });
    dialog
        .querySelectorAll('[data-join-close]')
        .forEach((btn) => btn.addEventListener('click', () => dialog.close()));
    // A click on the dimmed background closes the window.
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => open.focus());
})();
