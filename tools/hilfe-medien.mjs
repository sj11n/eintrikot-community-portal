// Bilder, Videos und Markierungs-Maße für die Mitglieder-Hilfe (help.php).
// Wird von tools/hilfe-medien.sh gegen eine Wegwerf-Kopie des lokalen Testportals aufgerufen:
//   node tools/hilfe-medien.mjs <adresse> <ausgabeordner> [nur-bilder|nur-videos]
// Schreibt <name>.png, boxes.json (Lage der Elemente in Prozent) und <name>.webm.
// Nur erfundene Personen (Demo-Mitglieder aus testphase.php, Testkonto „Mia Test“).
import { chromium } from 'playwright';
import { writeFileSync, mkdirSync, readdirSync, existsSync, rmSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { homedir } from 'node:os';
import { join } from 'node:path';

const [base, OUT, only = ''] = process.argv.slice(2);
if (!base || !OUT) {
    console.error('Aufruf: node tools/hilfe-medien.mjs <adresse> <ausgabeordner> [nur-bilder|nur-videos]');
    process.exit(2);
}
const P = base + '/community-portal/?';
const LOGIN = { user: 'mia.test', pass: 'TestPw-Member-9x!' }; // Testkonto aus tools/lokal-test.sh
mkdirSync(OUT, { recursive: true });

async function login(page) {
    await page.goto(base + '/wp-login.php');
    await page.fill('#user_login', LOGIN.user);
    await page.fill('#user_pass', LOGIN.pass);
    await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
}
async function scrollTo(page, loc, offset = 120) {
    await loc.first().evaluate((e, o) => window.scrollTo(0, e.getBoundingClientRect().top + scrollY - o), offset);
    await page.waitForTimeout(300);
}

// ---------- Bilder ----------
const boxes = {};
const missing = [];
/** Screenshot plus the position of each named element (one locator or a list, then their union). */
async function shot(page, name, targets = {}) {
    const vp = page.viewportSize();
    const out = {};
    for (const [key, spec] of Object.entries(targets)) {
        const rects = [];
        for (const loc of Array.isArray(spec) ? spec : [spec]) {
            const b = await loc
                .first()
                .boundingBox({ timeout: 3000 })
                .catch(() => null);
            if (b) rects.push(b);
        }
        if (!rects.length) {
            missing.push(name + '.' + key);
            continue;
        }
        // Union, clipped to the visible picture.
        const x1 = Math.max(0, Math.min(...rects.map((r) => r.x)));
        const y1 = Math.max(0, Math.min(...rects.map((r) => r.y)));
        const x2 = Math.min(vp.width, Math.max(...rects.map((r) => r.x + r.width)));
        const y2 = Math.min(vp.height, Math.max(...rects.map((r) => r.y + r.height)));
        if (x2 <= x1 || y2 <= y1) {
            missing.push(name + '.' + key + ' (außerhalb des Bildes)');
            continue;
        }
        const pc = (v, of) => +((v / of) * 100).toFixed(2);
        out[key] = { x: pc(x1, vp.width), y: pc(y1, vp.height), w: pc(x2 - x1, vp.width), h: pc(y2 - y1, vp.height) };
    }
    boxes[name] = { w: vp.width, h: vp.height, t: out };
    await page.mouse.move(vp.width - 2, vp.height - 2);
    await page.screenshot({ path: join(OUT, name + '.png') });
    console.log('  Bild', name);
}

async function pictures(browser) {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2, locale: 'de-DE' });
    ctx.setDefaultTimeout(8000);
    const p = await ctx.newPage();

    // Vor der Anmeldung
    await p.goto(P + 'view=portal');
    await shot(p, 'anmelden', {
        email: p.getByLabel('E-Mail-Adresse'),
        passwort: p.getByLabel('Passwort', { exact: true }),
        bleiben: p.getByText('Angemeldet bleiben', { exact: true }),
        vergessen: p.getByRole('link', { name: 'Passwort vergessen?' })
    });
    await p.goto(base + '/wp-login.php?action=lostpassword');
    await shot(p, 'passwort-vergessen', { feld: p.locator('#user_login'), knopf: p.locator('#wp-submit') });

    await login(p);
    const section = (title) => p.locator('section.portal-section').filter({ has: p.getByRole('heading', { name: title, exact: true }) });

    await p.goto(P + 'view=portal');
    await shot(p, 'start-erste-schritte', {
        vorschau: p.locator('.showme-preview'),
        freigeben: p.getByRole('button', { name: 'Freigeben' }),
        news: [p.getByRole('button', { name: 'Ja, gern' }), p.getByRole('button', { name: 'Nein, danke' })],
        spaeter: p.getByRole('button', { name: 'Später erinnern' })
    });
    await scrollTo(p, p.getByText('Hallo, Mia.'), 16);
    await shot(p, 'start-ueberblick', {
        nav: p.locator('.side-nav'),
        suche: p.locator('form.page-search'),
        fortschritt: p.locator('a.home-progress'),
        info: p.locator('.home-card.tone-rose'),
        termin: p.locator('.home-card.tone-green'),
        anfragen: p.locator('.home-card.tone-gold')
    });

    await p.goto(P + 'view=portal');
    await p.locator('.account-menu summary').click();
    await p.waitForTimeout(400);
    const menu = p.locator('.account-menu nav');
    await shot(p, 'kontomenue', {
        hilfe: p.locator('.help-button'),
        trigger: p.locator('.account-menu summary'),
        ansehen: menu.getByRole('link', { name: 'Mein Profil ansehen' }),
        bearbeiten: menu.getByRole('link', { name: 'Profil bearbeiten' }),
        ab: menu.getByRole('link', { name: 'Abmelden' })
    });

    await p.goto(P + 'view=members');
    await shot(p, 'mitglieder-liste', {
        suche: p.locator('form.page-search'),
        filter: p.locator('main').getByText('Filter', { exact: true }),
        sort: p.locator('.sort-bar'),
        az: p.locator('.letter-bar'),
        karte: p.locator('main a[href*="view=member&"]')
    });
    await p.goto(P + 'view=members&q=K%C3%B6ln');
    await p.locator('main a[href*="view=member&"]').first().click();
    await p.waitForLoadState();
    await shot(p, 'mitglied-profil', {
        zurueck: p.getByRole('link', { name: /Zurück zur Suche/ }),
        kopf: p.locator('main h1').first()
    });

    await p.goto(P + 'view=aktuelles&tab=termine');
    await shot(p, 'termine-liste', {
        reiter: p.getByRole('link', { name: 'Termine', exact: true }).last(),
        mehr: p.getByText('Mehr zum Termin').first(),
        anmelden: p.getByRole('button', { name: 'Für Veranstaltung anmelden' }).first()
    });
    await p.getByRole('button', { name: 'Für Veranstaltung anmelden' }).first().click();
    await p.waitForLoadState();
    await p.waitForTimeout(400);
    const signed = p.getByRole('button', { name: 'Abmelden' }).first();
    await scrollTo(p, signed, 320);
    await shot(p, 'termine-angemeldet', { wer: p.getByText(/Wer ist dabei/).first(), ab: signed });
    await signed.click(); // wieder abmelden, damit das Video sauber beginnt
    await p.waitForLoadState();

    await p.goto(P + 'view=aktuelles&tab=mitteilungen');
    await shot(p, 'vereinsinfos', {
        reiter: p.getByRole('link', { name: 'Vereinsinfos', exact: true }).last(),
        eintrag: p.locator('article.info-entry').first()
    });

    await p.goto(P + 'view=service');
    await shot(p, 'service', { anfragen: section('Deine Anfragen') });
    await scrollTo(p, section('Mitmachen'), 60);
    await shot(p, 'service-angebote', {
        mitmachen: section('Mitmachen'),
        kontakt: section('Meine Daten und Kontakt')
    });
    await p.goto(P + 'view=service&service=contact');
    await shot(p, 'anfrage-formular', {
        feld: p.locator('form.et-form textarea'),
        absenden: p.getByRole('button', { name: 'Anfrage absenden' })
    });
    await p.goto(P + 'view=service');
    const href = await p.locator('main a[href*="view=request&"]').first().getAttribute('href');
    await p.goto(href + '&saved=1');
    await shot(p, 'anfrage-eingegangen', {
        bestaetigung: p.locator('.request-confirm'),
        stand: p.locator('ol.request-timeline')
    });
    await p.goto(href);
    await scrollTo(p, p.locator('.request-overview'), 150);
    await shot(p, 'anfrage-verlauf', {
        stand: p.locator('ol.request-timeline'),
        weiter: p.locator('dl.request-facts'),
        rueck: p.locator('.request-reply')
    });

    await p.goto(P + 'view=profile');
    await shot(p, 'profil-oben', {
        sicht: p.locator('.visibility-intro'),
        sosehen: p.getByRole('link', { name: /So sehen dich andere/ })
    });
    const form = (title) => p.locator('section.form-section').filter({ has: p.getByRole('heading', { name: title, exact: true }) });
    await scrollTo(p, form('Über dich'), 40);
    await shot(p, 'profil-ueberdich', {
        bild: p.locator('label.avatar-file'),
        felder: [p.getByLabel('Wohnort', { exact: true }), p.getByLabel(/Aktueller Verein/)]
    });
    await scrollTo(p, form('Hockey-Lebenslauf'), 160);
    await shot(p, 'profil-schalter', {
        schalter: form('Hockey-Lebenslauf').locator('.visibility-switch'),
        speichern: p.locator('.save-bar button')
    });
    await scrollTo(p, p.locator('fieldset.birthday-choice'), 160);
    await shot(p, 'profil-geburtstag', {
        wahl: p.locator('fieldset.birthday-choice'),
        alter: p.getByText('Mein Alter im Mitgliederprofil anzeigen')
    });
    await ctx.close();

    const mc = await browser.newContext({ viewport: { width: 390, height: 780 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true, locale: 'de-DE' });
    mc.setDefaultTimeout(8000);
    const m = await mc.newPage();
    await login(m);
    await m.goto(P + 'view=portal');
    await scrollTo(m, m.getByText('Hallo, Mia.'), 70);
    await shot(m, 'handy-start');
    await m.goto(P + 'view=aktuelles&tab=termine');
    await shot(m, 'handy-termine');
    await mc.close();

    writeFileSync(join(OUT, 'boxes.json'), JSON.stringify(boxes, null, 1) + '\n');
}

// ---------- Videos ----------
// Sichtbarer Mauszeiger, Klick-Kreis und Untertitel-Leiste, nur in der Aufnahme.
const overlay = () => {
    const css = `
  #hc-cursor{position:fixed;z-index:2147483647;left:0;top:0;width:26px;height:26px;pointer-events:none;transform:translate(-4px,-2px)}
  #hc-ring{position:fixed;z-index:2147483646;width:44px;height:44px;margin:-22px 0 0 -22px;border-radius:50%;border:3px solid #DA525D;pointer-events:none;opacity:0;transform:scale(.4)}
  #hc-ring.go{animation:hcring .6s ease-out}
  @keyframes hcring{0%{opacity:1;transform:scale(.4)}100%{opacity:0;transform:scale(1.4)}}
  #hc-cap{position:fixed;z-index:2147483645;left:50%;bottom:28px;transform:translateX(-50%);background:#111;color:#fff;font:600 19px/1.35 Montserrat,system-ui,sans-serif;padding:12px 22px;border-radius:999px;box-shadow:0 8px 30px rgba(0,0,0,.25);opacity:0;transition:opacity .25s;white-space:nowrap}
  #hc-cap b{display:inline-grid;place-items:center;width:28px;height:28px;margin-right:10px;border-radius:50%;background:#C8E2EE;color:#111;font-size:15px}`;
    const add = () => {
        if (document.getElementById('hc-cursor')) return;
        const s = document.createElement('style');
        s.textContent = css;
        document.head.appendChild(s);
        const c = document.createElement('div');
        c.id = 'hc-cursor';
        c.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26"><path d="M3 2l7.5 19 2.6-7.9L21 10.5z" fill="#111" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>';
        const r = document.createElement('div');
        r.id = 'hc-ring';
        const cap = document.createElement('div');
        cap.id = 'hc-cap';
        document.body.append(c, r, cap);
        const pos = sessionStorage.getItem('hcpos');
        if (pos) {
            const [x, y] = pos.split(',');
            c.style.left = x + 'px';
            c.style.top = y + 'px';
        }
        const cp = sessionStorage.getItem('hccap');
        if (cp) {
            cap.innerHTML = cp;
            cap.style.opacity = 1;
        }
    };
    document.addEventListener('DOMContentLoaded', add);
    addEventListener('mousemove', (e) => {
        const c = document.getElementById('hc-cursor');
        if (c) {
            c.style.left = e.clientX + 'px';
            c.style.top = e.clientY + 'px';
        }
        sessionStorage.setItem('hcpos', e.clientX + ',' + e.clientY);
    }, true);
    addEventListener('mousedown', (e) => {
        const r = document.getElementById('hc-ring');
        if (!r) return;
        r.style.left = e.clientX + 'px';
        r.style.top = e.clientY + 'px';
        r.classList.remove('go');
        void r.offsetWidth;
        r.classList.add('go');
    }, true);
    window.hcCaption = (html) => {
        sessionStorage.setItem('hccap', html || '');
        const cap = document.getElementById('hc-cap');
        if (!cap) return;
        cap.innerHTML = html || '';
        cap.style.opacity = html ? 1 : 0;
    };
};

let cur = { x: 640, y: 400 };
async function moveTo(page, loc, ms = 700) {
    await loc.scrollIntoViewIfNeeded();
    let b = await loc.boundingBox();
    // Never click behind the caption bar at the bottom: scroll the target up first.
    if (b.y + b.height > page.viewportSize().height - 130) {
        await page.evaluate((dy) => window.scrollBy({ top: dy, behavior: 'smooth' }), b.y - 360);
        await page.waitForTimeout(700);
        b = await loc.boundingBox();
    }
    const tx = b.x + Math.min(b.width / 2, 60);
    const ty = b.y + b.height / 2;
    const steps = Math.max(12, Math.round(ms / 16));
    for (let i = 1; i <= steps; i++) {
        const t = i / steps;
        const e = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
        await page.mouse.move(cur.x + (tx - cur.x) * e, cur.y + (ty - cur.y) * e);
        await page.waitForTimeout(16);
    }
    cur = { x: tx, y: ty };
}
async function click(page, loc, nav = false) {
    await moveTo(page, loc);
    await page.waitForTimeout(250);
    if (nav) {
        await Promise.all([page.waitForLoadState('load'), page.mouse.down(), page.waitForTimeout(80).then(() => page.mouse.up())]);
    } else {
        await page.mouse.down();
        await page.waitForTimeout(80);
        await page.mouse.up();
    }
    await page.waitForTimeout(nav ? 900 : 500);
}
const cap = (page, n, t) => page.evaluate((h) => window.hcCaption(h), n ? `<b>${n}</b>${t}` : '');

function ffmpeg() {
    if (process.env.FFMPEG) return process.env.FFMPEG;
    const cache = process.env.PLAYWRIGHT_BROWSERS_PATH || join(homedir(), process.platform === 'darwin' ? 'Library/Caches/ms-playwright' : '.cache/ms-playwright');
    const dirs = existsSync(cache) ? readdirSync(cache).filter((d) => d.startsWith('ffmpeg-')).sort() : [];
    for (const d of dirs.reverse()) {
        for (const f of ['ffmpeg-mac', 'ffmpeg-linux', 'ffmpeg-win64.exe']) {
            if (existsSync(join(cache, d, f))) return join(cache, d, f);
        }
    }
    throw new Error('ffmpeg nicht gefunden. Einmal ausführen: npx playwright install ffmpeg');
}

/** Records one flow. The sign-in before it is cut off afterwards. */
async function record(browser, name, fn) {
    const raw = join(OUT, 'raw');
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 }, locale: 'de-DE', recordVideo: { dir: raw, size: { width: 1280, height: 800 } } });
    ctx.setDefaultTimeout(8000);
    const page = await ctx.newPage();
    const t0 = Date.now();
    await login(page);
    await page.addInitScript(overlay);
    cur = { x: 640, y: 400 };
    await page.goto(P + 'view=portal');
    await page.waitForTimeout(300);
    const offset = (Date.now() - t0) / 1000;
    await page.mouse.move(cur.x, cur.y);
    await page.waitForTimeout(600);
    await fn(page);
    const video = page.video();
    await ctx.close();
    const file = join(raw, name + '.webm');
    await video.saveAs(file);
    execFileSync(ffmpeg(), ['-y', '-loglevel', 'error', '-ss', offset.toFixed(2), '-i', file, '-an', '-c:v', 'libvpx-vp9', '-b:v', '0', '-crf', '38', '-row-mt', '1', '-deadline', 'good', join(OUT, name + '.webm')]);
    console.log('  Video', name, '(Anmeldung abgeschnitten nach', offset.toFixed(2), 's)');
}

async function videos(browser) {
    await record(browser, 'video-termin', async (p) => {
        await cap(p, 1, 'Links auf „Aktuelles“ klicken');
        await p.waitForTimeout(900);
        await click(p, p.getByRole('link', { name: 'Aktuelles', exact: true }).first(), true);
        await cap(p, 2, 'Reiter „Termine“ öffnen');
        await p.waitForTimeout(700);
        await click(p, p.getByRole('link', { name: 'Termine', exact: true }).last(), true);
        await cap(p, 3, '„Mehr zum Termin“ zeigt alle Details');
        await p.waitForTimeout(600);
        await click(p, p.getByText('Mehr zum Termin').nth(1));
        await p.waitForTimeout(1200);
        await cap(p, 4, '„Für Veranstaltung anmelden“ – fertig');
        await p.waitForTimeout(700);
        await click(p, p.getByRole('button', { name: 'Für Veranstaltung anmelden' }).nth(1), true);
        const ab = p.getByRole('button', { name: 'Abmelden' }).first();
        await ab.evaluate((e) => window.scrollTo({ top: e.getBoundingClientRect().top + scrollY - 360, behavior: 'smooth' }));
        await p.waitForTimeout(900);
        await cap(p, 5, '„Wer ist dabei?“ zeigt alle Angemeldeten');
        await click(p, p.getByText(/Wer ist dabei/).first());
        await p.waitForTimeout(1500);
        await cap(p, 6, 'Doch keine Zeit? Einfach „Abmelden“');
        await p.waitForTimeout(600);
        await click(p, ab, true);
        await p.waitForTimeout(1600);
        await cap(p, 0, '');
    });
    await record(browser, 'video-mitglied', async (p) => {
        await cap(p, 1, 'Links auf „Mitglieder“ klicken');
        await p.waitForTimeout(900);
        await click(p, p.getByRole('link', { name: 'Mitglieder', exact: true }).first(), true);
        await cap(p, 2, 'Name, Team oder Ort eintippen');
        await p.waitForTimeout(600);
        await click(p, p.getByPlaceholder(/Name/));
        await p.keyboard.type('Köln', { delay: 140 });
        await p.waitForTimeout(400);
        await cap(p, 3, '„Suchen“ klicken');
        await click(p, p.getByRole('button', { name: 'Suchen' }), true);
        await p.waitForTimeout(900);
        await cap(p, 4, 'Karte anklicken – das Profil öffnet sich');
        await p.waitForTimeout(500);
        await click(p, p.locator('main a[href*="view=member&"]').first(), true);
        await p.waitForTimeout(1800);
        await cap(p, 5, '„Zurück zur Suche“ – deine Treffer bleiben');
        await moveTo(p, p.getByRole('link', { name: /Zurück zur Suche/ }));
        await p.waitForTimeout(1800);
        await cap(p, 0, '');
    });
    rmSync(join(OUT, 'raw'), { recursive: true, force: true });
}

const browser = await chromium.launch({ channel: process.env.PW_CHANNEL ?? 'chrome' });
try {
    if (only !== 'nur-videos') await pictures(browser);
    if (only !== 'nur-bilder') await videos(browser);
} finally {
    await browser.close();
}
if (missing.length) {
    console.error('Nicht gefunden (Markierung fehlt im Bild): ' + missing.join(', '));
    process.exit(1);
}
