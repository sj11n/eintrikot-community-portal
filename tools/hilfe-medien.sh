#!/usr/bin/env bash
# Erneuert Bilder, Videos und Markierungs-Maße der Mitglieder-Hilfe (wp-content/plugins/eintrikot-community-core/help/).
#
# Arbeitet auf einer Wegwerf-Kopie des lokalen Testportals (.lokal/hilfe-medien/), damit Testdaten und
# gemeinsame Testläufe sich nicht stören. Alle gezeigten Personen sind erfunden.
#
#   tools/hilfe-medien.sh               Bilder und Videos
#   tools/hilfe-medien.sh nur-bilder    nur Bilder (schnell, z. B. nach einer Layout-Änderung)
#   tools/hilfe-medien.sh nur-videos    nur Videos
#
# Einmalig nötig: tools/lokal-test.sh setup · npm install · npx playwright install ffmpeg · brew install webp
# Danach Bilder ansehen (Markierungen an der richtigen Stelle?) und mit tools/lokal-test.sh test prüfen.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/.lokal/wordpress"
WORK="$ROOT/.lokal/hilfe-medien"
COPY="$WORK/wordpress"
RAW="$WORK/roh"
DEST="$ROOT/wp-content/plugins/eintrikot-community-core/help"
PORT="${PORT:-8906}"

[[ -f "$SRC/wp-config.php" ]] || { echo "Kein lokales Testportal. Zuerst: tools/lokal-test.sh setup"; exit 1; }
command -v cwebp >/dev/null || { echo "cwebp fehlt (brew install webp)."; exit 1; }
[[ -d "$ROOT/node_modules/playwright" ]] || { echo "Playwright fehlt. Zuerst: npm install"; exit 1; }

echo "== Kopie des Testportals anlegen"
rm -rf "$WORK"
mkdir -p "$WORK" "$RAW"
cp -R "$SRC" "$COPY" # Plugin und Theme bleiben Verweise auf dieses Repository
# Die Kopie läuft unter eigenem Port.
sed -i.orig "s#127.0.0.1:8899#127.0.0.1:$PORT#g; s#getenv('PORT') ?: '8899'#getenv('PORT') ?: '$PORT'#" "$COPY/wp-config.php"

echo "== Testdaten"
PORT="$PORT" php "$ROOT/tools/hilfe-medien-seed.php" "$COPY"

cat >"$WORK/router.php" <<PHP
<?php
\$path = parse_url(\$_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (\$path !== '/' && is_file('$COPY' . \$path)) { return false; }
\$_SERVER['SCRIPT_NAME'] = '/index.php';
chdir('$COPY');
require '$COPY/index.php';
PHP
(cd "$COPY" && PORT="$PORT" exec php -S "127.0.0.1:$PORT" -t . "$WORK/router.php" >"$WORK/server.log" 2>&1) &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
for _ in $(seq 1 50); do
    curl -fs -o /dev/null "http://127.0.0.1:$PORT/wp-login.php" && break
    sleep 0.2
done

echo "== Aufnahmen"
node "$ROOT/tools/hilfe-medien.mjs" "http://127.0.0.1:$PORT" "$RAW" "${1:-}"

echo "== In das Plugin übernehmen"
mkdir -p "$DEST"
for png in "$RAW"/*.png; do
    [[ -e "$png" ]] || continue
    cwebp -quiet -q 84 -m 6 -sharp_yuv "$png" -o "$DEST/$(basename "${png%.png}").webp"
done
[[ -f "$RAW/boxes.json" ]] && cp "$RAW/boxes.json" "$DEST/boxes.json"
for v in "$RAW"/*.webm; do
    [[ -e "$v" ]] && cp "$v" "$DEST/"
done
du -sh "$DEST"
echo "Fertig. Bilder in $DEST ansehen und tools/lokal-test.sh test laufen lassen."
