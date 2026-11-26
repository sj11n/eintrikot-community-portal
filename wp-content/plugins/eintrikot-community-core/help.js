/* Mitglieder-Hilfe: Legende hebt die Markierung im Bild hervor, Videos mit Pause-Knopf.
   Ohne JavaScript bleibt alles lesbar: Nummern und Pfeile sind fest im Bild (help.php). */
(function () {
    document.querySelectorAll('.et-help .hw-legende').forEach(function (list) {
        var fig = document.getElementById(list.getAttribute('data-for'));
        var svg = fig && fig.querySelector('.hw-anno');
        if (!svg) {
            return;
        }
        var marks = svg.querySelectorAll('g.m');
        function show(n) {
            svg.classList.toggle('fokus', n !== null);
            marks.forEach(function (g) {
                g.classList.toggle('an', g.getAttribute('data-n') === n);
            });
        }
        list.querySelectorAll('li').forEach(function (li) {
            var n = li.getAttribute('data-n');
            li.addEventListener('mouseenter', function () {
                show(n);
            });
            li.addEventListener('focus', function () {
                show(n);
            });
            li.addEventListener('mouseleave', function () {
                show(null);
            });
            li.addEventListener('blur', function () {
                show(null);
            });
        });
    });

    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('.et-help .hw-video').forEach(function (box) {
        var video = box.querySelector('video');
        var button = box.querySelector('.hw-vtoggle');
        if (!video || !button) {
            return;
        }
        function label() {
            button.textContent = video.paused ? 'Abspielen' : 'Pause';
        }
        // „Bewegung reduzieren“: nichts startet von selbst.
        if (reduce) {
            video.removeAttribute('autoplay');
            video.pause();
        }
        button.hidden = false;
        label();
        video.addEventListener('play', label);
        video.addEventListener('pause', label);
        button.addEventListener('click', function () {
            if (video.paused) {
                video.play();
            } else {
                video.pause();
            }
        });
    });
})();
