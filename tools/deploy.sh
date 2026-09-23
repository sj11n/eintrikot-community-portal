#!/usr/bin/env bash
# Spielt Theme und Plugin per SFTP (oder FTPS) auf die WordPress-Installation ein.
#
# Ablauf je Paket: Sicherung laden -> neue Fassung in einen versteckten
# Nachbarordner hochladen -> Ordner tauschen -> Seite prüfen -> bei Fehler
# alte Fassung zurücktauschen. WordPress ignoriert Ordner, die mit "." beginnen.
#
# Umgebung:
#   SFTP_HOST, SFTP_USER, LFTP_PASSWORD   Zugang (aus GitHub-Secrets)
#   WP_CONTENT_PATH   Pfad zu wp-content auf dem Server, z. B. /eintrikot/wp-content
#   SITE_URL          öffentliche Adresse für die Prüfung, z. B. http://eintrikot.myemmel.com
#   DEPLOY_PROTOCOL   sftp (Standard) oder ftps
#   SFTP_KNOWN_HOSTS  optional: known_hosts-Zeile des Servers
#   DRY_RUN           true = nur anzeigen, was sich ändern würde
set -euo pipefail

: "${SFTP_HOST:?SFTP_HOST fehlt}" "${SFTP_USER:?SFTP_USER fehlt}" "${LFTP_PASSWORD:?Passwort fehlt}"
: "${WP_CONTENT_PATH:?WP_CONTENT_PATH fehlt}" "${SITE_URL:?SITE_URL fehlt}"
PROTO="${DEPLOY_PROTOCOL:-sftp}"
REMOTE="${WP_CONTENT_PATH%/}"
DRY="${DRY_RUN:-false}"
STAMP="$(date -u +%Y%m%d-%H%M%S)"
BACKUP_DIR="${BACKUP_DIR:-backup}"

case "$PROTO" in
    sftp)
        KH="$(mktemp)"
        if [[ -n "${SFTP_KNOWN_HOSTS:-}" ]]; then
            printf '%s\n' "$SFTP_KNOWN_HOSTS" >"$KH"
            HOSTCHECK="StrictHostKeyChecking=yes"
        else
            echo "::warning::SFTP_KNOWN_HOSTS nicht gesetzt – Serverschlüssel wird beim ersten Kontakt ungeprüft akzeptiert."
            HOSTCHECK="StrictHostKeyChecking=accept-new"
        fi
        SETTINGS="set sftp:connect-program 'ssh -a -x -o $HOSTCHECK -o UserKnownHostsFile=$KH';"
        ;;
    ftps)
        SETTINGS="set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate yes;"
        ;;
    *)
        echo "::error::DEPLOY_PROTOCOL muss sftp oder ftps sein."
        exit 1
        ;;
esac
SETTINGS="$SETTINGS set net:timeout 30; set net:max-retries 3; set net:reconnect-interval-base 5; set mirror:parallel-transfer-count 4;"

remote() { # $1: lftp-Befehle; bricht beim ersten Fehler ab
    lftp --env-password -u "$SFTP_USER" -e "$SETTINGS set cmd:fail-exit yes; $1; bye" "$PROTO://$SFTP_HOST"
}
remote_soft() { # wie remote, Fehler sind erlaubt (z. B. Ordner existiert noch nicht)
    lftp --env-password -u "$SFTP_USER" -e "$SETTINGS set cmd:fail-exit no; $1; bye" "$PROTO://$SFTP_HOST" || true
}
exists() { # $1: entfernter Ordner
    remote_soft "cls -d '$1'" | grep -q .
}

PACKAGES=(
    "wp-content/plugins/eintrikot-community-core:plugins/eintrikot-community-core"
    "wp-content/themes/eintrikot-community:themes/eintrikot-community"
)

echo "== Verbindung prüfen: $PROTO://$SFTP_HOST"
if ! remote "cls -d ." >/dev/null 2>&1; then
    echo "::error::Anmeldung bei $SFTP_HOST fehlgeschlagen. Zugangsdaten bzw. Protokoll prüfen – es wurde nichts verändert."
    exit 1
fi

echo "== Ziel prüfen: $REMOTE"
if ! exists "$REMOTE/plugins" || ! exists "$REMOTE/themes"; then
    echo "::error::Unter $REMOTE fehlen plugins/ oder themes/. WP_CONTENT_PATH prüfen – es wurde nichts verändert."
    # Hilfe zur Pfadsuche als Hinweis im Lauf: nur Ordnernamen, eine Ebene, keine Dateiinhalte.
    for dir in "/" "." "$(dirname "$REMOTE")" "$REMOTE"; do
        found="$(remote_soft "cls -1 -F '${dir%/}/'" | grep '/$' | head -n 30 | tr '\n' ' ' || true)"
        echo "::notice::Ordner in '$dir': ${found:-(leer oder nicht vorhanden)}"
    done
    exit 1
fi

if [[ "$DRY" == "true" ]]; then
    for pkg in "${PACKAGES[@]}"; do
        src="${pkg%%:*}"
        dst="$REMOTE/${pkg##*:}"
        echo "== Probelauf: $src -> $dst"
        remote_soft "mirror -R --dry-run --delete --no-perms --verbose=1 --exclude-glob .DS_Store '$src' '$dst'"
    done
    echo "Probelauf beendet, nichts verändert."
    exit 0
fi

check_site() {
    local url code body
    for path in "/" "/mitglied-werden/" "/community-portal/"; do
        url="${SITE_URL%/}$path"
        body="$(mktemp)"
        code="$(curl -sS -L -o "$body" -w '%{http_code}' --max-time 30 "$url" || true)"
        code="${code:-000}"
        if [[ "$code" != "200" ]] || grep -qiE 'kritischer Fehler|critical error|Fatal error|Parse error' "$body"; then
            echo "::error::Prüfung fehlgeschlagen: $url (HTTP $code)"
            return 1
        fi
        echo "OK $url"
    done
}

mkdir -p "$BACKUP_DIR"
# Jeder Eintrag: "<Elternordner>|<Name>|<had_previous: 1/0>". Wird gefüllt, sobald ein
# Paket auf dem Server angefasst wurde, damit ein Abbruch es sicher zurücktauschen kann.
swapped=()
rolled_back=false

rollback() {
    [[ "$rolled_back" == true ]] && return 0
    rolled_back=true
    ((${#swapped[@]})) || return 0
    echo "== Zurücktauschen auf die vorherige Fassung"
    local i entry parent name had_prev ok=true
    for ((i = ${#swapped[@]} - 1; i >= 0; i--)); do
        entry="${swapped[$i]}"
        IFS='|' read -r parent name had_prev <<<"$entry"
        remote_soft "rm -r -f '$parent/.failed-$name'"
        if exists "$parent/$name"; then
            remote_soft "mv '$parent/$name' '$parent/.failed-$name'"
        fi
        if [[ "$had_prev" == 1 ]]; then
            remote_soft "mv '$parent/.previous-$name' '$parent/$name'"
            if exists "$parent/$name"; then
                echo "$name: vorherige Fassung wiederhergestellt."
            else
                ok=false
                echo "::error::$name: vorherige Fassung konnte nicht zurückgetauscht werden. Sie liegt unter $parent/.previous-$name bzw. in der Sicherung dieses Laufs."
            fi
        else
            echo "$name: war vorher nicht vorhanden, neue Fassung wieder entfernt."
        fi
    done
    [[ "$ok" == true ]]
}

# Bricht der Lauf an irgendeiner Stelle nach dem ersten Eingriff ab, wird zurückgetauscht.
finished=false
on_exit() {
    local rc=$?
    if [[ $rc -ne 0 && "$finished" != true ]]; then
        rollback || true
    fi
    return "$rc"
}
trap on_exit EXIT

for pkg in "${PACKAGES[@]}"; do
    src="${pkg%%:*}"
    rel="${pkg##*:}"
    parent="$REMOTE/$(dirname "$rel")"
    name="$(basename "$rel")"
    live="$parent/$name"
    next="$parent/.deploy-$name"
    prev="$parent/.previous-$name"

    echo "== $name: Sicherung laden"
    if exists "$live"; then
        remote "mirror --parallel=4 '$live' '$BACKUP_DIR/$name'"
    fi

    echo "== $name: neue Fassung hochladen"
    remote_soft "rm -r -f '$next'"
    remote "mirror -R --no-perms --parallel=4 --exclude-glob .DS_Store '$src' '$next'"
    # Nur für Tests: Einsetzen gezielt scheitern lassen (DEPLOY_FAIL_INJECT=swap:<Name>).
    [[ "${DEPLOY_FAIL_INJECT:-}" == "swap:$name" ]] && next="$parent/.fehlt-$name"

    echo "== $name: Ordner tauschen"
    remote_soft "rm -r -f '$prev'"
    if exists "$live"; then
        if ! remote "mv '$live' '$prev'"; then
            echo "::error::$name: bisherige Fassung ließ sich nicht beiseitelegen."
            exit 1
        fi
        swapped+=("$parent|$name|1")
    else
        swapped+=("$parent|$name|0")
    fi
    if ! remote "mv '$next' '$live'"; then
        echo "::error::$name: neue Fassung ließ sich nicht einsetzen."
        exit 1
    fi
done

echo "== Seite prüfen"
sleep 3
if ! check_site; then
    rollback || true
    check_site || echo "::error::Auch nach dem Zurücktauschen antwortet die Seite nicht korrekt – bitte sofort prüfen."
    exit 1
fi

echo "== Aufräumen"
finished=true
for entry in "${swapped[@]}"; do
    IFS='|' read -r parent name _ <<<"$entry"
    remote_soft "rm -r -f '$parent/.previous-$name' '$parent/.failed-$name'"
done
echo "Eingespielt ($STAMP)."
