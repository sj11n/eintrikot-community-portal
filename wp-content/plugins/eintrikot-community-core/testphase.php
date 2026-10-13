<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
/**
 * Test phase on the development site: no e-mails, a visible notice, a feedback list for the administration, and a
 * tool that creates and removes the test accounts. The phase is recognised by the address (*.myemmel.com), so it
 * ends by itself when the site moves to eintrikot.de. A database switch would travel with the move.
 */
function testphase_active() {
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    return (bool) apply_filters('eintrikot_testphase', str_ends_with($host, '.myemmel.com'));
}

add_filter('pre_wp_mail', function ($short_circuit) {
    return testphase_active() ? false : $short_circuit;
});

const FEEDBACK_STATES = [
    'new' => 'Neu',
    'discuss' => 'Besprechen',
    'do' => 'Umsetzen',
    'drop' => 'Verwerfen',
    'done' => 'Erledigt'
];
const FEEDBACK_KINDS = ['unclear' => 'Unklar', 'bug' => 'Fehler', 'wish' => 'Wunsch'];

add_action('init', function () {
    // Author stays 0, so the entries survive when the test accounts are deleted.
    register_post_type('et_feedback', [
        'label' => 'Test-Rückmeldungen',
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'eintrikot-community-setup',
        'show_in_rest' => false,
        'supports' => ['title', 'editor'],
        'capabilities' => array_fill_keys(
            [
                'edit_post',
                'read_post',
                'delete_post',
                'edit_posts',
                'edit_others_posts',
                'publish_posts',
                'read_private_posts',
                'delete_posts',
                'delete_private_posts',
                'delete_published_posts',
                'delete_others_posts',
                'edit_private_posts',
                'edit_published_posts',
                'create_posts'
            ],
            'manage_options'
        )
    ]);
});

/** The notice with the feedback form, above every portal page during the test phase. */
function testphase_bar() {
    if (!testphase_active()) {
        return '';
    }
    $kinds = '';
    foreach (FEEDBACK_KINDS as $key => $label) {
        $kinds .= '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
    }
    return '<div class="test-bar"><p><strong>Testumgebung</strong> – keine echten Daten, es werden keine E-Mails verschickt.</p>' .
        (isset($_GET['rueckmeldung'])
            ? '<p class="test-thanks" role="status">Danke für deine Rückmeldung.</p>'
            : '') .
        '<details><summary>Rückmeldung geben</summary><form method="post" action="' .
        esc_url(admin_url('admin-post.php')) .
        '"><input type="hidden" name="action" value="et_feedback"><input type="hidden" name="page" value="' .
        esc_attr(current_view()) .
        '">' .
        wp_nonce_field('et_feedback', '_wpnonce', true, false) . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core builds the hidden nonce field
        '<label>Art<select name="kind">' .
        $kinds .
        '</select></label><label>Was ist dir aufgefallen?<textarea name="text" rows="4" maxlength="1500" required></textarea></label>' .
        '<button type="submit">Rückmeldung senden</button></form></details></div>';
}

add_action('admin_post_et_feedback', function () {
    if (!member_access() || !testphase_active()) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer('et_feedback');
    $user = wp_get_current_user();
    $key = 'et_feedback_count_' . gmdate('Ymd');
    $count = (int) get_user_meta($user->ID, $key, true);
    $text = post_text('text', '', 1500);
    if ($text !== '' && $count < 30) {
        $id = wp_insert_post([
            'post_type' => 'et_feedback',
            'post_status' => 'publish',
            'post_author' => 0,
            'post_title' => mb_substr($text, 0, 60),
            'post_content' => $text
        ]);
        if ($id) {
            update_post_meta($id, 'et_fb_status', 'new');
            update_post_meta($id, 'et_fb_kind', post_choice('kind', array_keys(FEEDBACK_KINDS), 'unclear'));
            update_post_meta($id, 'et_fb_by', $user->display_name);
            update_post_meta($id, 'et_fb_view', post_choice('page', array_keys(view_names()), 'portal'));
            update_post_meta($id, 'et_fb_device', wp_is_mobile() ? 'Handy' : 'Computer');
            update_user_meta($user->ID, $key, $count + 1);
        }
    }
    $back = wp_get_referer();
    wp_safe_redirect(
        add_query_arg('rueckmeldung', '1', remove_query_arg('rueckmeldung', $back ?: portal_url()))
    );
    exit();
});

/** Known portal views, so the stored page name cannot be anything else. */
function view_names() {
    return array_fill_keys(
        [
            'portal',
            'members',
            'member',
            'profile',
            'aktuelles',
            'service',
            'request',
            'more',
            'documents',
            'birthday',
            'editorial',
            'admin',
            'requests',
            'onboarding',
            'audit',
            'preview'
        ],
        1
    );
}

add_filter('manage_et_feedback_posts_columns', function () {
    return [
        'cb' => '<input type="checkbox">',
        'title' => 'Rückmeldung',
        'et_status' => 'Status',
        'et_kind' => 'Art',
        'et_by' => 'Von',
        'et_view' => 'Seite',
        'et_device' => 'Gerät',
        'date' => 'Datum'
    ];
});
add_action(
    'manage_et_feedback_posts_custom_column',
    function ($column, $id) {
        $map = [
            'et_status' => 'et_fb_status',
            'et_kind' => 'et_fb_kind',
            'et_by' => 'et_fb_by',
            'et_view' => 'et_fb_view',
            'et_device' => 'et_fb_device'
        ];
        if (!isset($map[$column])) {
            return;
        }
        $value = (string) get_post_meta($id, $map[$column], true);
        echo esc_html(
            $column === 'et_status'
                ? FEEDBACK_STATES[$value] ?? $value
                : ($column === 'et_kind'
                    ? FEEDBACK_KINDS[$value] ?? $value
                    : $value)
        );
    },
    10,
    2
);

add_action('add_meta_boxes_et_feedback', function () {
    add_meta_box(
        'et_feedback_status',
        'Bearbeitung',
        function ($post) {
            wp_nonce_field('et_feedback_status', 'et_feedback_nonce');
            $current = (string) get_post_meta($post->ID, 'et_fb_status', true);
            echo '<p><label>Status<br><select name="et_fb_status">';
            foreach (FEEDBACK_STATES as $key => $label) {
                echo '<option value="' .
                    esc_attr($key) .
                    '"' .
                    selected($current, $key, false) .
                    '>' .
                    esc_html($label) .
                    '</option>';
            }
            echo '</select></label></p><p class="description">Umgesetzt wird nur, was auf „Umsetzen“ steht.</p>';
        },
        'et_feedback',
        'side'
    );
});
add_action('save_post_et_feedback', function ($id) {
    if (
        wp_is_post_revision($id) ||
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
        !current_user_can('manage_options') ||
        empty($_POST['et_feedback_nonce'])
    ) {
        return;
    }
    $nonce = $_POST['et_feedback_nonce']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- this is the nonce itself, checked on the next line
    if (!is_string($nonce) || !wp_verify_nonce($nonce, 'et_feedback_status')) {
        return;
    }
    update_post_meta($id, 'et_fb_status', post_choice('et_fb_status', array_keys(FEEDBACK_STATES), 'new'));
});

/** All feedback as plain text, to paste into the chat. */
function feedback_as_text() {
    $lines = [];
    $posts = get_posts([
        'post_type' => 'et_feedback',
        'post_status' => 'any',
        'numberposts' => 500,
        'orderby' => 'date',
        'order' => 'ASC'
    ]);
    foreach ($posts as $i => $post) {
        $meta = fn($key) => (string) get_post_meta($post->ID, $key, true);
        $lines[] = sprintf(
            "%d. [%s] %s – %s, Seite „%s“, %s, %s\n%s\n",
            $i + 1,
            FEEDBACK_STATES[$meta('et_fb_status')] ?? $meta('et_fb_status'),
            FEEDBACK_KINDS[$meta('et_fb_kind')] ?? $meta('et_fb_kind'),
            $meta('et_fb_by'),
            $meta('et_fb_view'),
            $meta('et_fb_device'),
            get_the_date('j.n.Y', $post),
            $post->post_content
        );
    }
    return $lines ? implode("\n", $lines) : 'Noch keine Rückmeldungen.';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'eintrikot-community-setup',
        'Testphase',
        'Testphase',
        'manage_options',
        'eintrikot-testphase',
        __NAMESPACE__ . '\testphase_page'
    );
});

/** Test accounts carry a marker; "end test phase" removes exactly these. */
function testphase_user_ids() {
    return array_map(
        'intval',
        get_users(['meta_key' => 'eintrikot_testkonto', 'meta_value' => '1', 'fields' => 'ID'])
    );
}
function testphase_post_ids() {
    return array_map(
        'intval',
        get_posts([
            'post_type' => ['et_calendar', 'et_info'],
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
            'meta_key' => 'et_testdata',
            'meta_value' => '1'
        ])
    );
}

function testphase_demo_people() {
    // Invented people. Birthdays are given as ages so they stay plausible whenever the data is created.
    return [
        [
            'Lena',
            'Hartmann',
            'Damen',
            'Aktiv',
            'Köln',
            'Nordrhein-Westfalen',
            'Physiotherapeutin',
            'Sportmedizin',
            24,
            'day'
        ],
        ['Jonas', 'Berger', 'Herren', 'Aktiv', 'Hamburg', 'Hamburg', 'Student', 'Maschinenbau', 22, ''],
        ['Marie', 'Vogel', 'Damen', 'Alumni', 'München', 'Bayern', 'Ärztin', 'Gesundheit', 34, 'full'],
        ['Tobias', 'Lindner', 'Herren', 'Alumni', 'Berlin', 'Berlin', 'Projektleiter', 'IT', 38, 'day'],
        [
            'Sophie',
            'Krämer',
            'Damen',
            'Aktiv',
            'Düsseldorf',
            'Nordrhein-Westfalen',
            'Auszubildende',
            'Handel',
            19,
            ''
        ],
        ['Felix', 'Roth', 'Herren', 'Alumni', 'Frankfurt', 'Hessen', 'Rechtsanwalt', 'Recht', 41, 'full'],
        [
            'Clara',
            'Neumann',
            'Damen',
            'Alumni',
            'Stuttgart',
            'Baden-Württemberg',
            'Lehrerin',
            'Bildung',
            36,
            'day'
        ],
        ['Paul', 'Schubert', 'Herren', 'Aktiv', 'Leipzig', 'Sachsen', 'Werkstudent', 'Medien', 21, ''],
        [
            'Hannah',
            'Fischer',
            'Damen',
            'Alumni',
            'Mannheim',
            'Baden-Württemberg',
            'Trainerin',
            'Sport',
            29,
            'full'
        ],
        ['Max', 'Wagner', 'Herren', 'Alumni', 'Hannover', 'Niedersachsen', 'Ingenieur', 'Energie', 45, 'day'],
        [
            'Emilia',
            'Brandt',
            'Damen',
            'Aktiv',
            'Bonn',
            'Nordrhein-Westfalen',
            'Studentin',
            'Biologie',
            20,
            ''
        ],
        ['Leon', 'Köhler', 'Herren', 'Alumni', 'Dresden', 'Sachsen', 'Berater', 'Beratung', 33, 'day'],
        [
            'Mia',
            'Schäfer',
            'Damen',
            'Alumni',
            'Münster',
            'Nordrhein-Westfalen',
            'Grafikerin',
            'Medien',
            31,
            'full'
        ],
        ['Noah', 'Winter', 'Herren', 'Aktiv', 'Kiel', 'Schleswig-Holstein', 'Schüler', 'Schule', 18, ''],
        [
            'Lisa',
            'Albrecht',
            'Damen',
            'Alumni',
            'Nürnberg',
            'Bayern',
            'Steuerberaterin',
            'Finanzen',
            39,
            'day'
        ],
        ['Ben', 'Hoffmann', 'Herren', 'Alumni', 'Bremen', 'Bremen', 'Physiotherapeut', 'Gesundheit', 27, ''],
        [
            'Johanna',
            'Engel',
            'Damen',
            'Alumni',
            'Freiburg',
            'Baden-Württemberg',
            'Architektin',
            'Bau',
            35,
            'full'
        ],
        [
            'David',
            'Kaiser',
            'Herren',
            'Alumni',
            'Essen',
            'Nordrhein-Westfalen',
            'Geschäftsführer',
            'Handwerk',
            48,
            'day'
        ]
    ];
}

function testphase_create_demo() {
    $made = 0;
    foreach (testphase_demo_people() as $i => $p) {
        $login = 'testmitglied' . ($i + 1);
        if (username_exists($login)) {
            continue;
        }
        $id = wp_insert_user([
            'user_login' => $login,
            'user_email' => $login . '@example.test',
            'user_pass' => wp_generate_password(24),
            'role' => 'eintrikot_member',
            'first_name' => $p[0],
            'last_name' => $p[1],
            'display_name' => $p[0] . ' ' . $p[1]
        ]);
        if (is_wp_error($id)) {
            continue;
        }
        $age_class = $p[3] === 'Aktiv' ? 'U21' : 'A-Nationalteam';
        $from = (int) gmdate('Y') - ($p[3] === 'Aktiv' ? 3 : 14);
        $profile = [
            'city' => $p[4],
            'region' => $p[5],
            'team' => $p[2],
            'age_class' => $age_class,
            'phase' => $p[3],
            'job' => $p[6],
            'industry' => $p[7],
            'birthday' => gmdate('Y-m-d', strtotime('-' . $p[8] . ' years -' . ($i * 17) % 300 . ' days')),
            'birthday_visibility' => $p[9],
            'stations' => [
                [
                    'role' => $i % 5 === 0 ? 'Kapitän/in' : 'Spieler/in',
                    'organisation' => $p[2],
                    'age_class' => $age_class,
                    'from' => (string) $from,
                    'to' => $p[3] === 'Aktiv' ? '' : (string) ($from + 4)
                ]
            ]
        ];
        update_user_meta($id, 'eintrikot_profile', $profile);
        update_user_meta($id, 'eintrikot_testkonto', '1');
        update_user_meta($id, 'eintrikot_member_number', 9001 + $i);
        update_user_meta($id, 'eintrikot_activated_at', time());
        $made++;
    }
    $posts = [
        [
            'et_calendar',
            'Sommertreffen in Köln',
            '+21 days',
            [
                'et_place' => 'Vereinsheim (Beispiel)',
                'et_time' => '18:00',
                'et_format' => 'treffen',
                'et_signup' => '1'
            ]
        ],
        [
            'et_calendar',
            'Alumni-Turnier',
            '+45 days',
            [
                'et_end_date' => gmdate('Y-m-d', strtotime('+46 days')),
                'et_format' => 'unterwegs',
                'et_signup' => '1'
            ]
        ],
        [
            'et_calendar',
            'Online-Austausch Beruf und Karriere',
            '+10 days',
            ['et_time' => '20:00', 'et_format' => 'online', 'et_signup' => '1']
        ],
        ['et_info', 'Willkommen im Testportal', 'now', []],
        ['et_info', 'Das Jahresevent 2027 nimmt Form an', '-3 days', []]
    ];
    foreach ($posts as $post) {
        $id = wp_insert_post([
            'post_type' => $post[0],
            'post_status' => 'publish',
            'post_title' => $post[1],
            'post_content' => 'Beispieltext für den Test. Alle Angaben sind erfunden.',
            'post_date' => gmdate('Y-m-d H:i:s', strtotime($post[2] === 'now' ? 'now' : '-1 day'))
        ]);
        if ($id) {
            update_post_meta($id, 'et_testdata', '1');
            if ($post[0] === 'et_calendar') {
                update_post_meta($id, 'et_date', gmdate('Y-m-d', strtotime($post[2])));
            }
            foreach ($post[3] as $k => $v) {
                update_post_meta($id, $k, $v);
            }
        }
    }
    return $made;
}

/** Creates one account per row of the form. Returns the lines shown once (name, login, password). */
function testphase_create_testers(array $rows) {
    $lines = [];
    $number = 9101;
    foreach ($rows as $row) {
        $name = sanitize_text_field((string) ($row['name'] ?? ''));
        $email = sanitize_email((string) ($row['email'] ?? ''));
        if ($name === '' || !is_email($email) || email_exists($email)) {
            continue;
        }
        $login = sanitize_user(strtolower(remove_accents(str_replace(' ', '.', $name))), true);
        if ($login === '' || username_exists($login)) {
            continue;
        }
        $password = wp_generate_password(14, false);
        $parts = explode(' ', $name, 2);
        $id = wp_insert_user([
            'user_login' => $login,
            'user_email' => $email,
            'user_pass' => $password,
            'role' => 'eintrikot_member',
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? '',
            'display_name' => $name
        ]);
        if (is_wp_error($id)) {
            continue;
        }
        update_user_meta($id, 'eintrikot_testkonto', '1');
        update_user_meta($id, 'eintrikot_member_number', $number++);
        update_user_meta($id, 'eintrikot_activated_at', time());
        $lines[] = [$name, $login, $password];
    }
    return $lines;
}

/** Removes the marked accounts and posts. Returns what is left, which should be nothing. */
function testphase_end() {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach (testphase_user_ids() as $id) {
        wp_delete_user($id);
    }
    foreach (testphase_post_ids() as $id) {
        wp_delete_post($id, true);
    }
    global $wpdb;
    $orphans = 0;
    $table = signup_table();
    $signed = $wpdb->get_col("SELECT DISTINCT user_id FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- table name is built from the prefix, no input
    foreach ($signed as $uid) {
        if (!get_userdata((int) $uid)) {
            $wpdb->delete(signup_table(), ['user_id' => (int) $uid], ['%d']);
            $orphans++;
        }
    }
    return [
        'users' => count(testphase_user_ids()),
        'posts' => count(testphase_post_ids()),
        'orphans' => $orphans
    ];
}

function testphase_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $users = count(testphase_user_ids());
    $posts = count(testphase_post_ids());
    $feedback = (int) wp_count_posts('et_feedback')->publish;
    echo '<div class="wrap"><h1>Testphase</h1>';
    foreach (
        [
            'demo' => 'Testdaten angelegt.',
            'ended' =>
                'Testphase beendet. Alle Testkonten, Anfragen, Anmeldungen und Beispieltermine sind gelöscht.'
        ]
        as $key => $text
    ) {
        if (isset($_GET[$key])) {
            echo '<div class="notice notice-success"><p>' . esc_html($text) . '</p></div>';
        }
    }
    echo '<p>' .
        (testphase_active()
            ? 'Die Testphase ist <strong>aktiv</strong>: Banner im Portal, Feedback-Button, keine E-Mails.'
            : 'Die Testphase ist <strong>nicht aktiv</strong> (diese Adresse ist keine Entwicklungsseite). Die Werkzeuge unten stehen trotzdem bereit.') .
        '</p><p>Testkonten: <strong>' .
        (int) $users .
        '</strong>, Beispiel-Termine und -Infos: <strong>' .
        (int) $posts .
        '</strong>, Rückmeldungen: <strong>' .
        (int) $feedback .
        '</strong></p>';
    $post = function ($action, $label, $extra = '', $confirm = '') {
        return '<form method="post" action="' .
            esc_url(admin_url('admin-post.php')) .
            '"' .
            ($confirm !== '' ? ' onsubmit="return confirm(\'' . esc_js($confirm) . '\')"' : '') .
            '><input type="hidden" name="action" value="' .
            esc_attr($action) .
            '">' .
            wp_nonce_field($action, '_wpnonce', true, false) . // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress core builds the hidden nonce field
            $extra .
            '<p><button type="submit" class="button button-primary">' .
            esc_html($label) .
            '</button></p></form>';
    };
    echo '<h2>1. Testdaten anlegen</h2><p>18 erfundene Mitglieder (Adressen auf example.test), 3 Termine und 2 Vereinsinfos.</p>';
    echo wp_kses($post('et_testphase_demo', 'Testdaten anlegen'), testphase_form_tags());
    echo '<h2>2. Zugänge für den Beirat anlegen</h2><p>Name und E-Mail eintragen. Die Passwörter erzeugt das Portal und zeigt sie <strong>einmal</strong> an, sie werden nirgends gespeichert. Die Konten haben die Rolle Mitglied.</p>';
    $rows = '';
    for ($i = 0; $i < 5; $i++) {
        $rows .=
            '<p><input type="text" name="testers[' .
            $i .
            '][name]" placeholder="Vorname Nachname" class="regular-text"> <input type="email" name="testers[' .
            $i .
            '][email]" placeholder="E-Mail-Adresse" class="regular-text"></p>';
    }
    echo wp_kses($post('et_testphase_testers', 'Zugänge anlegen', $rows), testphase_form_tags());
    echo '<h2>3. Testphase beenden</h2><p>Löscht alle Testkonten mit ihren Anfragen und Anmeldungen sowie die Beispiel-Termine und -Infos. Die Rückmeldungen bleiben. Das lässt sich nicht rückgängig machen.</p>';
    echo wp_kses(
        $post(
            'et_testphase_end',
            'Testphase beenden und Testdaten löschen',
            '',
            'Alle Testkonten und Beispieldaten jetzt löschen?'
        ),
        testphase_form_tags()
    );
    echo '<h2>Rückmeldungen als Text</h2><p>Zum Einfügen in den Chat.</p><textarea readonly rows="12" class="large-text code">' .
        esc_textarea(feedback_as_text()) .
        '</textarea></div>';
}
function testphase_form_tags() {
    return [
        'form' => ['method' => true, 'action' => true, 'onsubmit' => true],
        'input' => [
            'type' => true,
            'name' => true,
            'value' => true,
            'placeholder' => true,
            'class' => true,
            'id' => true
        ],
        'p' => [],
        'button' => ['type' => true, 'class' => true]
    ];
}

function testphase_admin_guard($action) {
    if (!current_user_can('manage_options')) {
        wp_die('Keine Berechtigung.', '', ['response' => 403]);
    }
    check_admin_referer($action);
}
add_action('admin_post_et_testphase_demo', function () {
    testphase_admin_guard('et_testphase_demo');
    testphase_create_demo();
    wp_safe_redirect(admin_url('admin.php?page=eintrikot-testphase&demo=1'));
    exit();
});
add_action('admin_post_et_testphase_end', function () {
    testphase_admin_guard('et_testphase_end');
    testphase_end();
    wp_safe_redirect(admin_url('admin.php?page=eintrikot-testphase&ended=1'));
    exit();
});
add_action('admin_post_et_testphase_testers', function () {
    testphase_admin_guard('et_testphase_testers');
    $rows = isset($_POST['testers']) && is_array($_POST['testers']) ? wp_unslash($_POST['testers']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- nonce checked in testphase_admin_guard(), every field is cleaned in testphase_create_testers()
    $lines = testphase_create_testers($rows);
    $html =
        '<h1>Zugänge angelegt</h1><p>Diese Passwörter werden <strong>nur jetzt</strong> angezeigt. Schicke Link und Passwort getrennt.</p><ul>';
    foreach ($lines as $line) {
        $html .=
            '<li>' .
            esc_html($line[0]) .
            ': Benutzername <code>' .
            esc_html($line[1]) .
            '</code>, Passwort <code>' .
            esc_html($line[2]) .
            '</code></li>';
    }
    $html .=
        '</ul>' .
        ($lines
            ? ''
            : '<p>Es wurde nichts angelegt. Name und gültige, noch nicht verwendete E-Mail-Adresse sind nötig.</p>');
    wp_die(
        wp_kses($html, ['h1' => [], 'p' => [], 'strong' => [], 'ul' => [], 'li' => [], 'code' => []]),
        'Zugänge angelegt',
        ['response' => 200, 'back_link' => true]
    );
});
