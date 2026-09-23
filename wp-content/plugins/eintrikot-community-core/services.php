<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
function service_types() {
    return [
        'event' => 'Für ein Event anmelden',
        'idea' => 'Förderidee einreichen',
        'help' => 'Ich möchte mich einbringen',
        'contact' => 'Vorstand kontaktieren',
        'address' => 'Stammdaten ändern',
        'bank' => 'Bankverbindung ändern',
        'funding' => 'EINTRIKOT zusätzlich fördern'
    ];
}
/** Service types grouped for the overview. Every key must exist in service_types(). */
function service_groups() {
    return [
        'Mitmachen' => ['event', 'idea', 'help', 'funding'],
        'Meine Daten und Kontakt' => ['address', 'bank', 'contact']
    ];
}
function render_service() {
    if (!member_access()) {
        return;
    }
    $types = service_types();
    $type = isset($_GET['service']) && is_string($_GET['service']) ? sanitize_key($_GET['service']) : '';
    if (!isset($types[$type])) {
        echo '<a class="text-link service-back" href="' .
            esc_url(portal_url('more')) .
            '">← Mehr</a><div class="profile-title"><h1>Service.</h1><p>Was können wir für dich tun?</p></div>';
        foreach (service_groups() as $group => $keys) {
            echo '<section class="portal-section"><h2>' . esc_html($group) . '</h2><div class="et-services">';
            foreach ($keys as $key) {
                echo '<a class="et-member" href="' .
                    esc_url(portal_url('service', ['service' => $key])) .
                    '"><strong>' .
                    esc_html($types[$key]) .
                    '</strong><span aria-hidden="true">→</span></a>';
            }
            echo '</div></section>';
        }
        echo '<section class="portal-section"><h2>Deine Anfragen</h2>';
        render_requests(false);
        echo '</section>';
        return;
    }
    $draft = get_transient('et_service_draft_' . get_current_user_id() . '_' . $type);
    if ($draft) {
        delete_transient('et_service_draft_' . get_current_user_id() . '_' . $type);
    }
    if ($draft) {
        echo '<div class="form-error" role="alert">' . esc_html($draft['message']) . '</div>';
    }
    ob_start();
    echo '<nav aria-label="Breadcrumb"><a class="text-link service-back" href="' .
        esc_url(portal_url('service')) .
        '">← Service</a></nav><h1>' .
        esc_html($types[$type]) .
        '</h1><form class="et-form" method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '">';
    wp_nonce_field('et_service');
    echo '<input type="hidden" name="request_token" value="' .
        esc_attr($draft['values']['request_token'] ?? wp_generate_uuid4()) .
        '">';
    echo '<input type="hidden" name="action" value="et_service"><input type="hidden" name="kind" value="' .
        esc_attr($type) .
        '">';
    if ($type === 'funding') {
        echo '<p>Du kannst einen jährlich wiederkehrenden freiwilligen Förderbeitrag zusätzlich zum Mitgliedsbeitrag anfragen. Der Vorstand prüft die Anfrage und übernimmt sie anschließend in MeinVerein.</p><label>Jährlicher Betrag<select name="amount"><option value="50">50 €</option><option value="100">100 €</option><option value="150">150 €</option><option value="custom">Individueller Betrag</option><option value="0">Freiwilligen Beitrag beenden</option></select></label><label>Individueller Betrag in Euro<input name="custom_amount" type="number" min="1" max="100000" step="0.01"></label><label>Gewünschter Beginn<input type="date" name="effective_date" required min="' .
            esc_attr(wp_date('Y-m-d')) .
            '"></label><label><input name="confirmed" type="checkbox" value="1" required> Ich beantrage diesen jährlich wiederkehrenden Förderbeitrag. Die Änderung gilt erst nach Prüfung und Übernahme in MeinVerein.</label>';
    } elseif ($type === 'address') {
        echo '<p>Teile uns deine neuen Kontaktdaten mit. Die Mitgliederverwaltung prüft und übernimmt sie in MeinVerein. Deinen sichtbaren Wohnort kannst du zusätzlich in deinem Profil pflegen.</p>';
        foreach (
            [
                'street' => 'Straße und Hausnummer',
                'postcode' => 'Postleitzahl',
                'city' => 'Ort',
                'country' => 'Land',
                'email' => 'Neue E-Mail-Adresse',
                'phone' => 'Neue Telefonnummer'
            ]
            as $key => $label
        ) {
            echo '<label>' .
                esc_html($label) .
                '<input name="' .
                esc_attr($key) .
                '" type="' .
                ($key === 'email' ? 'email' : 'text') .
                '" maxlength="200"></label>';
        }
    } elseif ($type === 'bank') {
        echo '<p>Fordere hier den sicheren Weg zur Änderung deiner Bankverbindung an. Die Mitgliederverwaltung kümmert sich um die Änderung in MeinVerein.</p><p>Bitte hier keine IBAN oder Bankunterlagen eingeben.</p>';
    }
    if ($type !== 'bank') {
        echo '<label>' .
            (in_array($type, ['bank', 'funding', 'address'], true)
                ? 'Ergänzung (optional)'
                : 'Deine Nachricht') .
            '<textarea name="message" maxlength="4000" ' .
            (!in_array($type, ['bank', 'funding', 'address'], true) ? 'required minlength="10"' : '') .
            '></textarea></label>';
    }
    echo '<button class="button solid">Anfrage speichern</button></form>';
    $html = ob_get_clean();
    if ($draft) {
        $html = restore_service_form($html, $draft['values']);
    }
    echo $html;
}
function restore_service_form($html, $values) {
    $doc = new \DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="UTF-8"><div id="et-form-replay">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    foreach (['input', 'select', 'textarea'] as $tag) {
        foreach ($doc->getElementsByTagName($tag) as $node) {
            $name = $node->getAttribute('name');
            if (!array_key_exists($name, $values) || in_array($name, ['_wpnonce', 'action', 'kind'], true)) {
                continue;
            }
            $value = $values[$name];
            if ($tag === 'textarea') {
                $node->nodeValue = '';
                $node->appendChild($doc->createTextNode($value));
            } elseif ($tag === 'select') {
                foreach ($node->getElementsByTagName('option') as $option) {
                    $option->removeAttribute('selected');
                    if ($option->getAttribute('value') === $value) {
                        $option->setAttribute('selected', 'selected');
                    }
                }
            } elseif ($node->getAttribute('type') === 'checkbox') {
                if ($value === '1') {
                    $node->setAttribute('checked', 'checked');
                }
            } else {
                $node->setAttribute('value', $value);
            }
        }
    }
    $out = '';
    $root = $doc->getElementById('et-form-replay');
    if ($root) {
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
    }
    return $out ?: $html;
}
function service_error($type, $message) {
    $values = [];
    foreach ($_POST as $key => $value) {
        if (is_scalar($value) && !in_array($key, ['_wpnonce', '_wp_http_referer'], true)) {
            $values[sanitize_key($key)] = mb_substr(
                sanitize_textarea_field(wp_unslash((string) $value)),
                0,
                4000
            );
        }
    }
    set_transient(
        'et_service_draft_' . get_current_user_id() . '_' . $type,
        ['values' => $values, 'message' => $message],
        300
    );
    wp_safe_redirect(portal_url('service', ['service' => $type]));
    exit();
}
function create_service_request($type, $message, $details, $token) {
    global $wpdb;
    $owner = get_current_user_id();
    $key = 'et_request_token_' . $owner . '_' . hash('sha256', $token);
    $existing = get_option($key);
    if (is_numeric($existing) && request_can_read(get_post((int) $existing))) {
        return (int) $existing;
    }
    if ($wpdb->query('START TRANSACTION') === false) {
        return new \WP_Error('storage', 'Speichern derzeit nicht möglich.');
    }
    if (!add_option($key, 'pending', '', 'no')) {
        $wpdb->query('ROLLBACK');
        wp_cache_delete($key, 'options');
        $existing = get_option($key);
        return is_numeric($existing) && request_can_read(get_post((int) $existing))
            ? (int) $existing
            : new \WP_Error(
                'pending',
                'Deine Anfrage wird gerade gespeichert. Bitte prüfe gleich deine Anfragen.'
            );
    }
    $id = wp_insert_post(
        wp_slash([
            'post_type' => 'et_request',
            'post_status' => 'private',
            'post_author' => $owner,
            'post_title' => service_types()[$type],
            'post_content' => $message
        ]),
        true
    );
    $ok = !is_wp_error($id) && $id > 0;
    if ($ok) {
        foreach (
            ['et_kind' => $type, 'et_details' => $details, 'et_status' => 'received']
            as $meta => $value
        ) {
            if (update_post_meta($id, $meta, wp_slash($value)) === false) {
                $ok = false;
                break;
            }
        }
    }
    if ($ok) {
        $ok = log_change($owner, 'service_request', null, $id, 'Anfrage erstellt') !== false;
    }
    if ($ok) {
        $ok = update_option($key, $id, false) !== false;
    }
    if (!$ok || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        wp_cache_delete($key, 'options');
        wp_cache_delete('notoptions', 'options');
        if (is_numeric($id)) {
            clean_post_cache($id);
        }
        return new \WP_Error(
            'storage',
            'Die Anfrage konnte nicht vollständig gespeichert werden. Bitte erneut versuchen.'
        );
    }
    return $id;
}
add_action('admin_post_et_service', function () {
    if (!member_access()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_service');
    $type = post_text('kind', '', 30);
    $types = service_types();
    if (!isset($types[$type])) {
        wp_die('Ungültiges Anliegen.', '', ['response' => 400]);
    }
    foreach ($_POST as $value) {
        if (!is_scalar($value) || mb_strlen((string) $value) > 4000) {
            service_error($type, 'Bitte die Eingaben prüfen.');
        }
    }
    $token = post_text('request_token', '', 64);
    if (!preg_match('/^[a-f0-9-]{36}$/D', $token)) {
        service_error($type, 'Bitte das Formular neu laden.');
    }
    $message = post_text('message', '', 4000);
    $details = [];
    if ($type === 'funding') {
        $choice = post_text('amount', '', 10);
        if (!in_array($choice, ['0', '50', '100', '150', 'custom'], true)) {
            service_error($type, 'Bitte einen Betrag auswählen.');
        }
        $amount = $choice === 'custom' ? str_replace(',', '.', post_text('custom_amount', '', 12)) : $choice;
        if (
            !preg_match('/^\d{1,6}(?:\.\d{1,2})?$/D', $amount) ||
            (float) $amount > 100000 ||
            ($choice === 'custom' && (float) $amount <= 0)
        ) {
            service_error($type, 'Bitte einen gültigen Betrag angeben.');
        }
        $date = post_text('effective_date', '', 10);
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        if (
            !$d ||
            $d->format('Y-m-d') !== $date ||
            $date < wp_date('Y-m-d') ||
            post_text('confirmed', '', 1) !== '1'
        ) {
            service_error($type, 'Bitte Beginn und Bestätigung prüfen.');
        }
        $details = [
            'annual_amount_cents' => (int) round((float) $amount * 100),
            'effective_date' => $date,
            'confirmed_at' => current_time('mysql', true)
        ];
        $message =
            'Jährlicher freiwilliger Förderbeitrag: ' .
            number_format((float) $amount, 2, ',', '.') .
            ' EUR. Gewünschter Beginn: ' .
            $date .
            "\n" .
            $message;
    } elseif ($type === 'address') {
        $labels = [
            'street' => 'Straße',
            'postcode' => 'Postleitzahl',
            'city' => 'Ort',
            'country' => 'Land',
            'email' => 'E-Mail',
            'phone' => 'Telefon'
        ];
        foreach ($labels as $key => $label) {
            $value = post_text($key, '', 200);
            if ($key === 'email' && $value !== '' && !is_email($value)) {
                service_error($type, 'Bitte eine gültige E-Mail-Adresse angeben.');
            }
            if ($value !== '') {
                $details[$key] = $value;
            }
        }
        if (!$details) {
            service_error($type, 'Bitte mindestens eine Änderung angeben.');
        }
        foreach ($details as $key => $value) {
            $message .= "\n" . $labels[$key] . ': ' . $value;
        }
    } elseif ($type === 'bank') {
        $message = 'Sicheren Änderungsweg für Bankverbindung angefragt.';
    } elseif (mb_strlen($message) < 10) {
        service_error($type, 'Bitte beschreibe dein Anliegen mit mindestens zehn Zeichen.');
    }
    $id = create_service_request($type, $message, $details, $token);
    if (is_wp_error($id)) {
        service_error($type, $id->get_error_message());
    }
    wp_safe_redirect(portal_url('request', ['request' => $id, 'saved' => 1]));
    exit();
});
