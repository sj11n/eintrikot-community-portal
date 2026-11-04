(() => {
    // Preview: computer, phone width, dark mode. Without JavaScript the light computer view stays.
    document.querySelectorAll('.nl-preview-section').forEach((section) => {
        const group = section.querySelector('.nl-device');
        const box = section.querySelector('.nl-preview');
        const frame = section.querySelector('iframe.nl-mail');
        const dark = section.querySelector('template.nl-dark');
        if (!group || !box || !frame || !dark) return;
        const light = frame.getAttribute('srcdoc');
        group.hidden = false;
        group.addEventListener('click', (e) => {
            const button = e.target.closest('[data-nl-device]');
            if (!button) return;
            const device = button.dataset.nlDevice;
            group.querySelectorAll('button').forEach((b) => {
                b.classList.toggle('active', b === button);
                b.setAttribute('aria-pressed', String(b === button));
            });
            box.classList.toggle('is-phone', device === 'phone');
            const html = device === 'dark' ? dark.content.textContent || dark.textContent : light;
            if (frame.getAttribute('srcdoc') !== html) frame.setAttribute('srcdoc', html);
        });
    });

    // Mail frames grow with their content, so the page scrolls instead of the frame.
    const fit = (frame) => {
        try {
            const doc = frame.contentDocument;
            if (doc && doc.body) frame.style.height = doc.documentElement.scrollHeight + 'px';
        } catch (e) {
            /* keep the fixed height */
        }
    };
    document.querySelectorAll('iframe.nl-mail').forEach((frame) => {
        frame.addEventListener('load', () => {
            fit(frame);
            frame.contentWindow?.addEventListener('resize', () => fit(frame));
            frame.contentDocument
                ?.querySelectorAll('img')
                .forEach((img) => img.addEventListener('load', () => fit(frame)));
            // Links in the preview open in a new tab, never inside the frame.
            frame.contentDocument?.querySelectorAll('a[href]').forEach((a) => (a.target = '_blank'));
        });
        if (frame.contentDocument?.readyState === 'complete') fit(frame);
    });

    // Approval: the button is only active once the confirmation is ticked.
    document.querySelectorAll('[data-nl-confirm]').forEach((box) => {
        const button = box.form?.querySelector('[data-nl-confirmed]');
        if (!button) return;
        const sync = () => (button.disabled = !box.checked);
        box.addEventListener('change', sync);
        sync();
    });

    // Cancelling asks once more.
    document.querySelectorAll('[data-nl-ask]').forEach((button) => {
        button.addEventListener('click', (e) => {
            if (!window.confirm(button.dataset.nlAsk)) e.preventDefault();
        });
    });

    // Progress while sending: reload now and then, unless someone is typing.
    const run = document.querySelector('[data-nl-refresh]');
    if (run) {
        setTimeout(
            () => {
                if (!document.activeElement || !document.activeElement.matches('input, textarea'))
                    location.reload();
            },
            Number(run.dataset.nlRefresh) * 1000,
        );
    }
})();
