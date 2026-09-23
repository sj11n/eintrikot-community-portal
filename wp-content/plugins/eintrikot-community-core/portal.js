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
            list.dispatchEvent(new Event('input', { bubbles: true }));
        });
        list.addEventListener('click', (e) => {
            if (e.target.closest('.remove-station')) {
                e.target.closest('.station').remove();
                list.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }

    // Unsaved changes: visible hint in the save bar and a warning before leaving the page.
    const form = document.querySelector('form[data-dirty-check]');
    if (form) {
        let dirty = false;
        const state = form.querySelector('[data-save-state]');
        const mark = () => {
            dirty = true;
            if (state) state.textContent = 'Nicht gespeicherte Änderungen';
            form.classList.add('is-dirty');
        };
        form.addEventListener('input', mark);
        form.addEventListener('change', mark);
        form.addEventListener('submit', () => {
            dirty = false;
            if (state) state.textContent = 'Wird gespeichert …';
        });
        window.addEventListener('beforeunload', (e) => {
            if (dirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    // A partly shared section keeps its field settings until its switch is flipped.
    document.querySelectorAll('.visibility-switch input[role="switch"]').forEach((input) => {
        input.addEventListener('change', () => {
            const row = input.closest('.visibility-switch-row');
            row?.querySelector('.visibility-keep')?.remove();
            row?.querySelector('.visibility-note')?.remove();
        });
    });

    // Directory filters apply as soon as a value is chosen.
    document.querySelectorAll('select[data-autosubmit]').forEach((select) => {
        select.form?.classList.add('js-autosubmit');
        select.addEventListener('change', () => select.form.requestSubmit());
    });

    // Messages after saving or failing: move focus there so screen readers announce them.
    document.querySelector('.form-error[tabindex], .request-confirm[tabindex]')?.focus();

    // Mobile: while typing, hide the bottom navigation so the save bar and the keyboard fit.
    const app = document.querySelector('.et-app.shell');
    if (app) {
        app.addEventListener('focusin', (e) => {
            if (e.target.matches('input:not([type=checkbox]):not([type=radio]), textarea, select'))
                app.classList.add('is-typing');
        });
        app.addEventListener('focusout', () => app.classList.remove('is-typing'));
    }

    // Profile picture: click the picture, choose a file, pick the square section.
    const input = document.querySelector('[data-avatar-input]'),
        pick = document.querySelector('[data-avatar-pick]'),
        dialog = document.querySelector('[data-avatar-cropper]');
    if (!input || !pick || !dialog || typeof dialog.showModal !== 'function' || !window.DataTransfer) {
        return;
    }
    document.documentElement.classList.add('js-cropper');
    const stage = dialog.querySelector('[data-crop-stage]'),
        img = dialog.querySelector('[data-crop-image]'),
        zoom = dialog.querySelector('[data-crop-zoom]');
    let view = null,
        url = null,
        applied = false;

    const clamp = () => {
        const size = stage.clientWidth,
            scale = view.base * view.zoom,
            w = view.w * scale,
            h = view.h * scale;
        view.x = Math.min(0, Math.max(size - w, view.x));
        view.y = Math.min(0, Math.max(size - h, view.y));
        img.style.width = w + 'px';
        img.style.height = h + 'px';
        img.style.transform = `translate(${view.x}px, ${view.y}px)`;
    };
    const setZoom = (value) => {
        const size = stage.clientWidth,
            before = view.base * view.zoom;
        view.zoom = Math.min(4, Math.max(1, value));
        const after = view.base * view.zoom;
        // Zoom around the centre of the visible section.
        view.x = size / 2 - ((size / 2 - view.x) * after) / before;
        view.y = size / 2 - ((size / 2 - view.y) * after) / before;
        zoom.value = String(view.zoom);
        clamp();
    };

    pick.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (applied) {
            applied = false;
            return;
        }
        if (!file) return;
        if (url) URL.revokeObjectURL(url);
        url = URL.createObjectURL(file);
        img.onload = () => {
            dialog.showModal();
            const size = stage.clientWidth;
            view = { w: img.naturalWidth, h: img.naturalHeight, zoom: 1, x: 0, y: 0 };
            view.base = size / Math.min(view.w, view.h);
            view.x = (size - view.w * view.base) / 2;
            view.y = (size - view.h * view.base) / 2;
            zoom.value = '1';
            clamp();
            stage.focus();
        };
        img.onerror = () => {
            input.value = '';
            window.alert(
                'Dieses Bild kann der Browser nicht öffnen. Bitte ein JPEG-, PNG- oder WebP-Bild wählen.',
            );
        };
        img.src = url;
    });

    let drag = null;
    stage.addEventListener('pointerdown', (e) => {
        drag = { x: e.clientX, y: e.clientY, vx: view.x, vy: view.y };
        stage.setPointerCapture(e.pointerId);
    });
    stage.addEventListener('pointermove', (e) => {
        if (!drag) return;
        view.x = drag.vx + e.clientX - drag.x;
        view.y = drag.vy + e.clientY - drag.y;
        clamp();
    });
    stage.addEventListener('pointerup', () => (drag = null));
    stage.addEventListener('pointercancel', () => (drag = null));
    stage.addEventListener(
        'wheel',
        (e) => {
            e.preventDefault();
            setZoom(view.zoom - e.deltaY / 500);
        },
        { passive: false },
    );
    stage.addEventListener('keydown', (e) => {
        const step = e.shiftKey ? 40 : 10;
        const moves = {
            ArrowLeft: [step, 0],
            ArrowRight: [-step, 0],
            ArrowUp: [0, step],
            ArrowDown: [0, -step],
        };
        if (moves[e.key]) {
            view.x += moves[e.key][0];
            view.y += moves[e.key][1];
            clamp();
        } else if (e.key === '+' || e.key === '=') {
            setZoom(view.zoom + 0.1);
        } else if (e.key === '-') {
            setZoom(view.zoom - 0.1);
        } else {
            return;
        }
        e.preventDefault();
    });
    zoom.addEventListener('input', () => setZoom(parseFloat(zoom.value)));

    const close = () => {
        dialog.close();
        pick.focus();
    };
    dialog.querySelector('[data-crop-cancel]').addEventListener('click', () => {
        input.value = '';
        close();
    });
    dialog.addEventListener('cancel', () => {
        input.value = '';
    });
    dialog.querySelector('[data-crop-apply]').addEventListener('click', () => {
        const size = stage.clientWidth,
            scale = view.base * view.zoom,
            canvas = document.createElement('canvas');
        canvas.width = canvas.height = 512;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, 512, 512);
        ctx.drawImage(img, -view.x / scale, -view.y / scale, size / scale, size / scale, 0, 0, 512, 512);
        canvas.toBlob(
            (blob) => {
                if (!blob) return;
                const transfer = new DataTransfer();
                transfer.items.add(new File([blob], 'profilbild.jpg', { type: 'image/jpeg' }));
                applied = true;
                input.files = transfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                const preview = document.createElement('img');
                preview.className = 'et-avatar';
                preview.alt = '';
                preview.src = canvas.toDataURL('image/jpeg', 0.85);
                pick.querySelector('.et-avatar')?.replaceWith(preview);
                const remove = document.querySelector('[data-avatar-remove]');
                if (remove) remove.checked = false;
                close();
            },
            'image/jpeg',
            0.9,
        );
    });
})();
