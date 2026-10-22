/* Kopfbild der Story: Mediathek öffnen, Bild merken, entfernen. */
(function () {
    var box = document.querySelector('[data-et-head]');
    if (!box || !window.wp || !window.wp.media) {
        return;
    }
    var input = box.querySelector('input[name="et_head_image"]');
    var preview = box.querySelector('[data-et-head-preview]');
    var pick = box.querySelector('[data-et-head-pick]');
    var remove = box.querySelector('[data-et-head-remove]');
    var frame = null;
    function show(url) {
        preview.textContent = '';
        if (url) {
            var img = document.createElement('img');
            img.src = url;
            img.alt = '';
            img.style.maxWidth = '100%';
            img.style.height = 'auto';
            preview.appendChild(img);
        }
        remove.hidden = !url;
    }
    pick.addEventListener('click', function (event) {
        event.preventDefault();
        if (!frame) {
            frame = window.wp.media({
                title: 'Kopfbild wählen',
                library: { type: 'image' },
                multiple: false,
                button: { text: 'Als Kopfbild verwenden' },
            });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                input.value = attachment.id;
                show(
                    attachment.sizes && attachment.sizes.medium
                        ? attachment.sizes.medium.url
                        : attachment.url,
                );
            });
        }
        frame.open();
    });
    remove.addEventListener('click', function (event) {
        event.preventDefault();
        input.value = '';
        show('');
    });
})();
