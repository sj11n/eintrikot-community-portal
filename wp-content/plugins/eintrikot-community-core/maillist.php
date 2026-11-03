<?php
/**
 * Verwaltung → E-Mail-Verteiler: all addresses of the current members as one list for the BCC field,
 * for club announcements such as the invitation to the general meeting. Only for the board and
 * administrators. Every call is written to the change log.
 */

namespace Eintrikot\Community;

if (!defined('ABSPATH')) {
    exit();
}

/** Addresses of all portal accounts, sorted and without duplicates. Sample addresses of the test phase are left out. */
function mail_list_addresses() {
    $mails = [];
    foreach (
        get_users(['capability' => 'eintrikot_portal', 'number' => -1, 'fields' => ['user_email']])
        as $user
    ) {
        $mail = strtolower(trim($user->user_email));
        if (is_email($mail) && !preg_match('/@example\.(test|com|org)$/', $mail)) {
            $mails[$mail] = true;
        }
    }
    $mails = array_keys($mails);
    sort($mails);
    return $mails;
}

function render_mail_list() {
    if (!view_manager()) {
        echo '<div class="portal-empty"><h1>Kein Zugriff</h1></div>';
        return;
    }
    $mails = mail_list_addresses();
    log_change(0, 'mail_list', null, count($mails), 'E-Mail-Verteiler abgerufen');
    echo '<a class="text-link service-back" href="' .
        esc_url(portal_url('admin')) .
        '">← Verwaltung</a>' .
        page_head(
            'E-Mail-Verteiler',
            'Alle Mitglieder in einer Liste für das BCC-Feld, zum Beispiel für die Einladung zur Mitgliederversammlung.',
            '',
            'admin'
        );
    echo '<section class="portal-section"><h2>' .
        esc_html((string) count($mails)) .
        ' Adressen</h2><p>Immer ins Feld <strong>BCC</strong> einfügen, nie in „An“ oder „CC“. Sonst sehen alle Mitglieder alle Adressen. Nur für Vereinsmitteilungen verwenden. Newsletter und Werbung gehen nur an Mitglieder, die ihn im Profil eingeschaltet haben.</p>' .
        '<p><label class="screen-reader-text" for="mail-list">Adressen</label><textarea id="mail-list" class="large-text" rows="8" readonly>' .
        esc_textarea(implode(', ', $mails)) .
        '</textarea></p><p><button type="button" class="button solid" data-copy-target="mail-list">Alle Adressen kopieren</button> <span data-copy-state role="status"></span></p></section>';
}
