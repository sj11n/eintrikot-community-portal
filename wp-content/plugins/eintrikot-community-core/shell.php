<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
/** Main navigation (desktop sidebar). Role areas follow the member items. */
function portal_nav_items() {
    $items = [
        'portal' => 'Start',
        'members' => 'Mitglieder',
        'infos' => 'Vereinsinfos',
        'events' => 'Termine',
        'service' => 'Service'
    ];
    if (current_user_can('eintrikot_edit_infos')) {
        $items['editorial'] = 'Redaktion';
    }
    if (manager_access()) {
        $items['admin'] = 'Verwaltung';
    }
    return $items;
}

/** Mobile bottom bar. The own profile and the role areas sit in the account menu. */
function portal_mobile_items() {
    return [
        'portal' => 'Start',
        'members' => 'Mitglieder',
        'infos' => 'Infos',
        'events' => 'Termine',
        'service' => 'Service'
    ];
}

/** Which navigation entry a view belongs to. The own profile belongs to the account menu. */
function portal_nav_section($view) {
    $map = [
        'member' => 'members',
        'edit-member' => 'members',
        'more' => 'service',
        'request' => 'service',
        'documents' => 'service',
        'requests' => 'admin',
        'audit' => 'admin',
        'onboarding' => 'admin',
        'profile' => 'account'
    ];
    return $map[$view] ?? $view;
}

/** CI colour of an area: orientation through colour, while forms and lists stay calm and white. */
function portal_tone($section) {
    $tones = [
        'portal' => 'start',
        'members' => 'blue',
        'infos' => 'rose',
        'events' => 'green',
        'service' => 'gold',
        'editorial' => 'rose',
        'admin' => 'blue',
        'account' => 'start'
    ];
    return $tones[$section] ?? 'blue';
}

/** Coloured head of a portal page: short title, one line of context, optional extra markup. */
function page_head($title, $lead = '', $extra = '', $section = '') {
    $section = $section !== '' ? $section : portal_nav_section(current_view());
    return '<header class="page-head tone-' .
        esc_attr(portal_tone($section)) .
        '"><span class="page-band" aria-hidden="true"></span><div class="page-head-inner"><h1>' .
        esc_html($title) .
        '</h1>' .
        ($lead !== '' ? '<p class="page-lead">' . $lead . '</p>' : '') .
        $extra .
        '</div></header>';
}

function current_view() {
    return isset($_GET['view']) && is_string($_GET['view']) ? sanitize_key($_GET['view']) : 'portal';
}

function portal_nav_links($items, $selected) {
    $html = '';
    foreach ($items as $key => $label) {
        $classes = array_filter([
            'tone-' . portal_tone($key),
            in_array($key, ['editorial', 'admin'], true) ? 'role-nav' : '',
            $selected === $key ? 'active' : ''
        ]);
        $html .=
            '<a href="' .
            esc_url(portal_url($key)) .
            '" class="' .
            esc_attr(implode(' ', $classes)) .
            '"' .
            ($selected === $key ? ' aria-current="page"' : '') .
            '>' .
            esc_html($label) .
            '</a>';
    }
    return $html;
}
function portal_brand() {
    return '<a href="' .
        esc_url(home_url('/')) .
        '" aria-label="EINTRIKOT Startseite"><img class="logo" src="' .
        esc_url(get_theme_file_uri('assets/logo.svg')) .
        '" alt="EINTRIKOT"></a>';
}

/** Account menu: own profile, role areas (for mobile) and sign-out. */
function portal_account_menu($user) {
    $links = [
        [portal_url('member', ['member' => $user->ID]), 'Mein Profil ansehen'],
        [portal_url('profile'), 'Profil bearbeiten']
    ];
    if (current_user_can('eintrikot_edit_infos')) {
        $links[] = [portal_url('editorial'), 'Redaktion', 'role'];
    }
    if (manager_access()) {
        $links[] = [portal_url('admin'), 'Verwaltung', 'role'];
    }
    $links[] = [home_url('/'), 'Zur öffentlichen Website'];
    $links[] = [wp_logout_url(home_url('/')), 'Abmelden'];
    $html =
        '<header class="account-header"><details class="account-menu"><summary aria-label="Mein Konto">' .
        member_avatar($user->ID, $user->display_name) .
        '<span class="account-name">' .
        esc_html($user->display_name) .
        '</span><span class="account-caret" aria-hidden="true">⌄</span></summary><nav aria-label="Mein Konto">';
    foreach ($links as $link) {
        $html .=
            '<a href="' .
            esc_url($link[0]) .
            '"' .
            (isset($link[2]) ? ' class="account-role"' : '') .
            '>' .
            esc_html($link[1]) .
            '</a>';
    }
    return $html . '</nav></details></header>';
}

/** A row link used on service, admin and editorial pages. */
function service_link($url, $label, $hint = '') {
    return '<a class="service-link" href="' .
        esc_url($url) .
        '"><span><strong>' .
        esc_html($label) .
        '</strong>' .
        ($hint !== '' ? '<small>' . esc_html($hint) . '</small>' : '') .
        '</span><span aria-hidden="true">→</span></a>';
}

function portal_shell($content, $view) {
    $user = wp_get_current_user();
    $selected = portal_nav_section($view);
    // The administration opens requests from "Verwaltung", members from "Service".
    if ($view === 'request' && manager_access()) {
        $selected = 'admin';
    }
    return '<div class="et-app shell tone-' .
        esc_attr(portal_tone($selected)) .
        '"><a class="skip-link" href="#main">Zum Inhalt springen</a><aside class="sidebar">' .
        portal_brand() .
        '<nav class="side-nav" aria-label="Mitgliederbereich">' .
        portal_nav_links(portal_nav_items(), $selected) .
        '</nav><div class="side-bottom"><a href="' .
        esc_url(home_url('/')) .
        '">Zur öffentlichen Website →</a></div></aside><div class="portal-workspace"><div class="mobile-brand">' .
        portal_brand() .
        '</div>' .
        portal_account_menu($user) .
        '<main class="portal-main" id="main" tabindex="-1">' .
        $content .
        '</main></div><nav class="bottom-nav" aria-label="Mobile Mitgliedernavigation">' .
        portal_nav_links(portal_mobile_items(), $selected) .
        '</nav></div>';
}

add_shortcode('eintrikot_portal', function () {
    // Parental consent pages work without an account (see consent.php).
    if (current_view() === 'consent') {
        return render_consent();
    }
    if (!member_access()) {
        $form = is_user_logged_in()
            ? '<p>Dein Konto ist noch nicht für das Mitgliederportal freigeschaltet.</p>'
            : wp_login_form([
                    'echo' => false,
                    'redirect' => portal_url(),
                    'label_username' => 'E-Mail-Adresse',
                    'label_password' => 'Passwort',
                    'label_log_in' => 'Anmelden',
                    'label_remember' => 'Angemeldet bleiben',
                    'value_remember' => true
                ]) .
                '<p class="auth-remember-hint">Mit Haken bleibst du auf diesem Gerät ' .
                REMEMBER_DAYS_MEMBER .
                ' Tage angemeldet. Auf fremden oder gemeinsam genutzten Geräten den Haken bitte entfernen.</p>';
        return '<div class="et-app"><header class="header">' .
            portal_brand() .
            '</header><main class="auth"><div class="auth-inner"><h1>Willkommen zurück.</h1><p>Dein Zugang zum EINTRIKOT-Mitgliederbereich.</p>' .
            $form .
            '<a class="text-link" href="' .
            esc_url(wp_lostpassword_url(portal_url())) .
            '">Passwort vergessen?</a>' .
            (is_user_logged_in()
                ? ''
                : '<aside class="auth-first"><h2>Zum ersten Mal hier?</h2><p>Sobald deine Mitgliedschaft bestätigt ist, bekommst du per E-Mail einen persönlichen Link. Damit legst du dein Passwort selbst fest.</p><p>Link nicht erhalten oder abgelaufen? Unter „Passwort vergessen?“ deine E-Mail-Adresse eingeben – du bekommst sofort einen neuen Link.</p></aside>') .
            '</div></main></div>';
    }
    $view = current_view();
    $member = isset($_GET['member']) && is_scalar($_GET['member']) ? absint($_GET['member']) : 0;
    $denied =
        '<div class="portal-empty"><h1>Kein Zugriff</h1><p>Dieser Bereich ist für deine Rolle nicht freigeschaltet.</p></div>';
    ob_start();
    if (isset($_GET['saved']) && $view !== 'request') {
        echo '<p role="status" class="portal-success" tabindex="-1">Gespeichert. Deine Änderungen sind jetzt sichtbar.</p>';
    }
    switch ($view) {
        case 'profile':
            render_profile(get_current_user_id());
            break;
        case 'members':
            render_directory();
            break;
        case 'member':
            render_member($member);
            break;
        case 'edit-member':
            if (manager_access()) {
                render_profile($member);
            } else {
                echo '<div class="portal-empty"><h1>Kein Zugriff</h1><p>Du kannst nur dein eigenes Profil bearbeiten.</p></div>';
            }
            break;
        case 'infos':
            render_infos();
            break;
        case 'events':
            render_events();
            break;
        case 'more':
        case 'service':
            render_service();
            break;
        case 'request':
            render_request(absint(directory_param('request')));
            break;
        case 'requests':
            echo manager_access() ? '' : $denied;
            if (manager_access()) {
                render_requests(true);
            }
            break;
        case 'onboarding':
            render_onboarding();
            break;
        case 'audit':
            echo manager_access() ? '' : $denied;
            if (manager_access()) {
                render_audit();
            }
            break;
        case 'admin':
            if (!manager_access()) {
                echo $denied;
                break;
            }
            echo page_head('Verwaltung', 'Anfragen bearbeiten, Profile pflegen, Änderungen nachvollziehen.') .
                '<section class="portal-section"><h2>Mitgliederservice</h2>' .
                service_link(portal_url('requests'), 'Service-Anfragen bearbeiten', open_requests_hint()) .
                service_link(
                    portal_url('onboarding'),
                    'Neue Mitglieder aufnehmen',
                    'Aus MeinVerein übernehmen, Begrüßung und Urkunde senden'
                ) .
                service_link(portal_url('members'), 'Mitgliederprofile pflegen') .
                service_link(audit_url(), 'Änderungsprotokoll öffnen') .
                '</section>';
            if (current_user_can('manage_options')) {
                echo '<section class="portal-section"><h2>Website</h2>' .
                    service_link(admin_url('admin.php?page=eintrikot-metrics'), 'Kennzahlen bearbeiten') .
                    '</section>';
            }
            break;
        case 'editorial':
            if (!current_user_can('eintrikot_edit_infos')) {
                echo $denied;
                break;
            }
            echo page_head('Redaktion', 'Geschichten teilen. Den Verein verbinden.') .
                '<section class="portal-section"><h2>Schreiben</h2>' .
                service_link(
                    admin_url('post-new.php'),
                    'Öffentliche News schreiben',
                    'Erscheint auf der Website'
                ) .
                service_link(
                    admin_url('post-new.php?post_type=et_info'),
                    'Vereinsinfo schreiben',
                    'Nur für Mitglieder im Portal'
                ) .
                service_link(
                    admin_url('edit.php?post_type=et_calendar'),
                    'Kalender pflegen',
                    'Termine im Portal'
                ) .
                '</section><section class="portal-section"><h2>Newsletter</h2><p>Der Ablauf zum Einlesen und Bearbeiten von KI-Entwürfen folgt in einem späteren Ausbauschritt.</p></section>';
            break;
        case 'documents':
            echo '<a class="text-link service-back" href="' .
                esc_url(portal_url('service')) .
                '">← Service</a>' .
                page_head('Dokumente', 'Satzung, Protokolle und Unterlagen für Mitglieder.') .
                '<p class="portal-empty">Noch keine Dokumente hinterlegt. Sobald der Vorstand Unterlagen bereitstellt, findest du sie hier.</p>';
            break;
        default:
            render_home();
    }
    return portal_shell(ob_get_clean(), $view);
});

add_filter('show_admin_bar', function ($show) {
    return ((int) get_option('eintrikot_portal_page') > 0 &&
        is_page((int) get_option('eintrikot_portal_page'))) ||
        ((int) get_option('eintrikot_audit_page') > 0 && is_page((int) get_option('eintrikot_audit_page')))
        ? false
        : $show;
});
