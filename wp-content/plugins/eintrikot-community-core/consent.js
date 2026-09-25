/* Eltern-Adresse ohne Tippfehler: zweimal eingeben, bekannte Anbieter-Vertipper vorschlagen. */
(() => {
    const form = document.querySelector('[data-consent-email]');
    if (!form) return;
    const main = form.querySelector('[data-email-main]');
    const repeat = form.querySelector('[data-email-repeat]');
    const suggest = form.querySelector('[data-email-suggest]');
    const mismatch = form.querySelector('[data-email-mismatch]');
    const domains = [
        'gmail.com',
        'googlemail.com',
        'gmx.de',
        'gmx.net',
        'web.de',
        't-online.de',
        'yahoo.de',
        'yahoo.com',
        'outlook.de',
        'outlook.com',
        'hotmail.de',
        'hotmail.com',
        'icloud.com',
        'me.com',
        'freenet.de',
        'posteo.de',
        'mailbox.org',
        'aol.com',
        'live.de',
        'arcor.de',
    ];
    // Edit distance between two short strings.
    const distance = (a, b) => {
        const d = Array.from({ length: a.length + 1 }, (_, i) => [i]);
        for (let j = 1; j <= b.length; j++) d[0][j] = j;
        for (let i = 1; i <= a.length; i++)
            for (let j = 1; j <= b.length; j++)
                d[i][j] = Math.min(
                    d[i - 1][j] + 1,
                    d[i][j - 1] + 1,
                    d[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1),
                );
        return d[a.length][b.length];
    };
    const check = () => {
        const value = main.value.trim().toLowerCase();
        const [local, domain] = value.split('@');
        suggest.hidden = true;
        if (!local || !domain || domains.includes(domain)) return;
        const best = domains.map((d) => [d, distance(domain, d)]).sort((x, y) => x[1] - y[1])[0];
        if (best && best[1] > 0 && best[1] <= 2) {
            const fixed = local + '@' + best[0];
            suggest.innerHTML = '';
            suggest.append('Meintest du ');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'text-reset';
            button.textContent = fixed;
            button.addEventListener('click', () => {
                main.value = fixed;
                suggest.hidden = true;
                repeat.focus();
            });
            suggest.append(button, '?');
            suggest.hidden = false;
        }
    };
    main.addEventListener('blur', check);
    main.addEventListener('input', () => (suggest.hidden = true));
    // No pasting into the repeat field: typing it again is the point.
    repeat.addEventListener('paste', (e) => e.preventDefault());
    form.addEventListener('submit', (e) => {
        const a = main.value.trim().toLowerCase();
        const b = repeat.value.trim().toLowerCase();
        mismatch.hidden = a === b;
        if (!main.checkValidity() || a !== b) {
            e.preventDefault();
            (a !== b ? repeat : main).focus();
        }
    });
})();
