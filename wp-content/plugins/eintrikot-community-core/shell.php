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
        'infos' => 'Neuigkeiten',
        'events' => 'Termine',
        'profile' => 'Profil',
        'more' => 'Mehr'
    ];
    if (current_user_can('eintrikot_edit_infos')) {
        $items['editorial'] = 'Redaktion';
    }
    if (manager_access()) {
        $items['admin'] = 'Verwaltung';
    }
    return $items;
}

/** Mobile bottom bar: the four most used areas. "Mehr" sits in the header. */
function portal_mobile_items() {
    return ['portal' => 'Start', 'members' => 'Mitglieder', 'events' => 'Termine', 'profile' => 'Profil'];
}

/** Which navigation entry a view belongs to. */
function portal_nav_section($view) {
    $map = [
        'member' => 'members',
        'edit-member' => 'members',
        'service' => 'more',
        'request' => 'more',
        'documents' => 'more',
        'requests' => 'admin',
        'audit' => 'admin'
    ];
    return $map[$view] ?? $view;
}

function portal_nav_links($items, $selected) {
    $html = '';
    foreach ($items as $key => $label) {
        $classes = array_filter([
            in_array($key, ['editorial', 'admin'], true) ? 'role-nav' : '',
            $selected === $key ? 'active' : ''
        ]);
        $html .=
            '<a href="' .
            esc_url(portal_url($key)) .
            '"' .
            ($classes ? ' class="' . esc_attr(implode(' ', $classes)) . '"' : '') .
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
function portal_shell($content, $view) {
    $user = wp_get_current_user();
    $selected = portal_nav_section($view);
    $account =
        '<header class="account-header"><details class="account-menu"><summary>' .
        member_avatar($user->ID, $user->display_name) .
        '<span>' .
        esc_html($user->display_name) .
        '</span><span aria-hidden="true">⌄</span></summary><nav aria-label="Mein Konto"><a href="' .
        esc_url(portal_url('profile')) .
        '">Mein Profil bearbeiten</a><a href="' .
        esc_url(wp_logout_url(home_url('/'))) .
        '">Abmelden</a></nav></details></header>';
    $more_link =
        '<a class="mobile-more' .
        ($selected === 'more' ? ' active" aria-current="page' : '') .
        '" href="' .
        esc_url(portal_url('more')) .
        '">Mehr</a>';
    return '<div class="et-app shell"><aside class="sidebar">' .
        portal_brand() .
        '<nav class="side-nav" aria-label="Mitgliederbereich">' .
        portal_nav_links(portal_nav_items(), $selected) .
        '</nav><div class="side-bottom"><a href="' .
        esc_url(home_url('/')) .
        '">Zur öffentlichen Website →</a></div></aside><div class="portal-workspace"><div class="mobile-brand">' .
        portal_brand() .
        $more_link .
        '</div>' .
        $account .
        '<main class="portal-main" id="main">' .
        $content .
        '</main></div><nav class="bottom-nav" aria-label="Mobile Mitgliedernavigation">' .
        portal_nav_links(portal_mobile_items(), $selected) .
        '</nav></div>';
}

/** "Mehr": service, documents, contact, role areas and account links. */
function render_more() {
    if (!member_access()) {
        return;
    }
    $link = fn($url, $label, $hint = '') => '<a class="service-link" href="' .
        esc_url($url) .
        '"><span><strong>' .
        esc_html($label) .
        '</strong>' .
        ($hint !== '' ? '<small>' . esc_html($hint) . '</small>' : '') .
        '</span><span aria-hidden="true">→</span></a>';
    echo '<div class="profile-title"><h1>Mehr.</h1><p>Service, Dokumente und dein Konto.</p></div>';
    echo '<section class="portal-section"><h2>Service</h2>' .
        $link(portal_url('service'), 'Service-Anfragen', 'Mitmachen, fördern, Daten ändern') .
        $link(portal_url('service', ['service' => 'contact']), 'Vorstand kontaktieren') .
        $link(portal_url('documents'), 'Dokumente') .
        '</section>';
    if (current_user_can('eintrikot_edit_infos') || manager_access()) {
        echo '<section class="portal-section"><h2>Vereinsarbeit</h2>';
        if (current_user_can('eintrikot_edit_infos')) {
            echo $link(portal_url('editorial'), 'Redaktion', 'News, Vereinsinfos, Kalender');
        }
        if (manager_access()) {
            echo $link(portal_url('admin'), 'Verwaltung', 'Anfragen, Profile, Protokoll');
        }
        echo '</section>';
    }
    echo '<section class="portal-section"><h2>Konto</h2>' .
        $link(portal_url('profile'), 'Mein Profil bearbeiten') .
        $link(home_url('/'), 'Zur öffentlichen Website') .
        $link(wp_logout_url(home_url('/')), 'Abmelden') .
        '</section>';
}

add_shortcode('eintrikot_portal', function () {
    if (!member_access()) {
        $form = is_user_logged_in()
            ? '<p>Dein Konto ist noch nicht für das Mitgliederportal freigeschaltet.</p>'
            : wp_login_form([
                'echo' => false,
                'redirect' => portal_url(),
                'label_username' => 'Benutzername oder E-Mail-Adresse',
                'label_password' => 'Passwort',
                'label_log_in' => 'Anmelden',
                'label_remember' => 'Angemeldet bleiben'
            ]);
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
    $view = isset($_GET['view']) && is_string($_GET['view']) ? sanitize_key($_GET['view']) : 'portal';
    $member = isset($_GET['member']) && is_scalar($_GET['member']) ? absint($_GET['member']) : 0;
    ob_start();
    if (isset($_GET['saved'])) {
        echo '<p role="status" class="portal-success">Deine Änderungen wurden gespeichert.</p>';
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
                echo '<h1>Kein Zugriff</h1><p>Du kannst nur dein eigenes Profil bearbeiten.</p>';
            }
            break;
        case 'infos':
            render_infos();
            break;
        case 'events':
            render_events();
            break;
        case 'more':
            render_more();
            break;
        case 'service':
            render_service();
            break;
        case 'request':
            render_request(absint(directory_param('request')));
            break;
        case 'requests':
            if (manager_access()) {
                render_requests(true);
            } else {
                echo '<h1>Kein Zugriff</h1>';
            }
            break;
        case 'audit':
            if (manager_access()) {
                render_audit();
            } else {
                echo '<h1>Kein Zugriff</h1>';
            }
            break;
        case 'admin':
            if (!manager_access()) {
                echo '<h1>Kein Zugriff</h1>';
                break;
            }
            echo '<div class="profile-title"><h1>Verwaltung.</h1><p>Einträge pflegen. Änderungen nachvollziehen.</p></div><section class="portal-section"><h2>Mitgliederservice</h2><a class="service-link" href="' .
                esc_url(portal_url('requests')) .
                '">Service-Anfragen bearbeiten <span>→</span></a><a class="service-link" href="' .
                esc_url(portal_url('members')) .
                '">Mitgliederprofile pflegen <span>→</span></a><a class="service-link" href="' .
                esc_url(audit_url()) .
                '">Änderungsprotokoll öffnen <span>→</span></a></section>';
            if (current_user_can('manage_options')) {
                echo '<a class="text-link" href="' .
                    esc_url(admin_url('admin.php?page=eintrikot-metrics')) .
                    '">Kennzahlen bearbeiten →</a>';
            }
            break;
        case 'editorial':
            if (!current_user_can('eintrikot_edit_infos')) {
                echo '<h1>Kein Zugriff</h1>';
                break;
            }
            echo '<div class="profile-title"><h1>Redaktion.</h1><p>Geschichten teilen. Den Verein verbinden.</p></div><a class="service-link" href="' .
                esc_url(admin_url('post-new.php')) .
                '">Öffentliche News schreiben <span>→</span></a><a class="service-link" href="' .
                esc_url(admin_url('post-new.php?post_type=et_info')) .
                '">Vereinsinfo schreiben <span>→</span></a><a class="service-link" href="' .
                esc_url(admin_url('edit.php?post_type=et_calendar')) .
                '">EINTRIKOT-Kalender pflegen <span>→</span></a><section class="portal-section"><h2>Newsletter</h2><p>Der Ablauf zum Einlesen und Bearbeiten von KI-Entwürfen folgt in einem späteren Ausbauschritt.</p></section>';
            break;
        case 'documents':
            echo '<a class="text-link service-back" href="' .
                esc_url(portal_url('more')) .
                '">← Mehr</a><h1>Gut zu wissen.</h1><p>Dokumente für unsere Mitglieder.</p><p>Noch keine Dokumente hinterlegt.</p>';
            break;
        default:
            echo '<section class="welcome"><h1>Dein Netzwerk.</h1><p>Finde Menschen, die dein Spiel teilen.</p></section><section class="network-entry"><h2>Wen möchtest du wiederfinden?</h2><p>Jugend, Damen, Herren und Ehemalige. Alle an einem Ort.</p><a class="button solid" href="' .
                esc_url(portal_url('members')) .
                '">Mitglieder entdecken →</a></section><div class="portal-grid compact-home"><section><h2>Neuigkeiten</h2><article class="news-piece"><h3>Willkommen bei EINTRIKOT</h3><p>Dein Profil verbindet dich mit unserem Netzwerk. Ergänze, was du teilen möchtest.</p><a class="text-link" href="' .
                esc_url(portal_url('profile')) .
                '">Mein Profil ansehen →</a></article><a class="text-link" href="' .
                esc_url(portal_url('infos')) .
                '">Alle Neuigkeiten →</a></section><section><h2>Was möchtest du bewegen?</h2><p>Eine Idee teilen, dich einbringen oder EINTRIKOT zusätzlich fördern.</p><a class="text-link" href="' .
                esc_url(portal_url('service')) .
                '">Zum Service →</a></section></div>';
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
