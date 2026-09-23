<?php
namespace Eintrikot\Community;
if (!defined('ABSPATH')) {
    exit();
}
function directory_param($key) {
    $v = $_GET[$key] ?? '';
    return is_scalar($v) ? mb_substr(sanitize_text_field(wp_unslash((string) $v)), 0, 160) : '';
}
function shared_stations($data) {
    return ($data['visibility']['stations'] ?? 'private') === 'members' && is_array($data['stations'] ?? null)
        ? $data['stations']
        : [];
}
/**
 * Whether a portal account appears in the member directory.
 * Automatic: accounts with an EINTRIKOT role are listed, technical accounts (e.g. a pure administrator)
 * are not. The administration can override this per account ("show" / "hide").
 */
function directory_listed_by_role($user) {
    return (bool) array_intersect((array) $user->roles, [
        'eintrikot_member',
        'eintrikot_editor',
        'eintrikot_board'
    ]);
}
function directory_listed($user) {
    $choice = profile_data($user->ID)['directory_listing'] ?? '';
    if ($choice === 'show' || $choice === 'hide') {
        return $choice === 'show';
    }
    return directory_listed_by_role($user);
}

/** Directory state (search, filters, page) that a profile visit carries along and restores. */
function directory_state_keys() {
    return ['q', 'team', 'age_class', 'phase', 'region', 'member_page'];
}
function directory_state() {
    $state = [];
    foreach (directory_state_keys() as $key) {
        $value = directory_param($key);
        if ($value !== '') {
            $state[$key] = $value;
        }
    }
    return $state;
}
/** Back link from a profile: the directory exactly as it was, scrolled to the visited card. */
function directory_back_url($member_id) {
    $back = directory_param('back');
    $args = [];
    if ($back !== '') {
        parse_str($back, $parsed);
        foreach (directory_state_keys() as $key) {
            if (isset($parsed[$key]) && is_scalar($parsed[$key]) && $parsed[$key] !== '') {
                $args[$key] = mb_substr(sanitize_text_field((string) $parsed[$key]), 0, 160);
            }
        }
    }
    return portal_url('members', $args) . '#m-' . (int) $member_id;
}

function directory_projection($user) {
    $data = profile_data($user->ID);
    $shared = [];
    foreach (profile_fields() as $key => $label) {
        $v = visible_value($data, $key);
        $shared[$key] = is_scalar($v) ? (string) $v : '';
    }
    $station_words = [];
    foreach (shared_stations($data) as $row) {
        if (is_array($row)) {
            foreach ($row as $v) {
                if (is_scalar($v)) {
                    $station_words[] = (string) $v;
                }
            }
        }
    }
    return [
        'user' => $user,
        'shared' => $shared,
        'search' => mb_strtolower(
            implode(' ', array_merge([$user->display_name], array_values($shared), $station_words))
        )
    ];
}
function render_directory() {
    if (!member_access()) {
        return;
    }
    $q = directory_param('q');
    $keys = ['team' => 'Team', 'age_class' => 'Altersklasse', 'phase' => 'Trikotphase', 'region' => 'Region'];
    $filters = [];
    $options = array_fill_keys(array_keys($keys), []);
    $rows = [];
    foreach (
        get_users([
            'capability' => 'eintrikot_portal',
            'number' => -1,
            'orderby' => 'display_name',
            'order' => 'ASC'
        ])
        as $user
    ) {
        if (!directory_listed($user)) {
            continue;
        }
        $row = directory_projection($user);
        $rows[] = $row;
        foreach ($keys as $key => $label) {
            if ($row['shared'][$key] !== '') {
                $options[$key][$row['shared'][$key]] = $row['shared'][$key];
            }
        }
    }
    foreach ($keys as $key => $label) {
        $filters[$key] = directory_param($key);
    }
    $matches = array_values(
        array_filter($rows, function ($row) use ($q, $filters) {
            foreach ($filters as $key => $value) {
                if ($value !== '' && $row['shared'][$key] !== $value) {
                    return false;
                }
            }
            foreach (preg_split('/\s+/u', mb_strtolower(trim($q))) as $term) {
                if ($term !== '' && !str_contains($row['search'], $term)) {
                    return false;
                }
            }
            return true;
        })
    );
    $total = count($matches);
    $pages = max(1, (int) ceil($total / 25));
    $page = min($pages, max(1, (int) directory_param('member_page')));
    $active = array_filter($filters);
    $listed = count($rows);
    echo page_head(
        'Mitglieder',
        esc_html(
            $listed .
                ' ' .
                ($listed === 1 ? 'Mitglied' : 'Mitglieder') .
                ' aus Jugend, Damen, Herren und Ehemaligen. Du siehst nur, was jede und jeder teilt.'
        ),
        member_search_form($q, $filters),
        'members'
    );
    echo '<form class="directory-filters-form" action="' .
        esc_url(get_permalink((int) get_option('eintrikot_portal_page'))) .
        '" method="get"><input type="hidden" name="view" value="members">' .
        ($q !== '' ? '<input type="hidden" name="q" value="' . esc_attr($q) . '">' : '') .
        '<details class="directory-filters"' .
        ($active ? ' open' : '') .
        '><summary>Filter' .
        ($active ? ' <span class="filter-count">' . count($active) . '</span>' : '') .
        '</summary><div class="filter-fields">';
    foreach ($keys as $key => $label) {
        natcasesort($options[$key]);
        echo '<label>' .
            esc_html($label) .
            '<select name="' .
            esc_attr($key) .
            '" data-autosubmit><option value="">Alle</option>';
        foreach ($options[$key] as $value) {
            echo '<option value="' .
                esc_attr($value) .
                '" ' .
                selected($filters[$key], $value, false) .
                '>' .
                esc_html($value) .
                '</option>';
        }
        echo '</select></label>';
    }
    echo '<button class="button filter-apply" type="submit">Anwenden</button></div></details></form>';

    // Active search and filters as chips; each chip removes exactly its own criterion.
    $state = array_merge(['q' => $q], $filters);
    $chips = [];
    if ($q !== '') {
        $chips[] = ['„' . $q . '“', 'q'];
    }
    foreach ($keys as $key => $label) {
        if ($filters[$key] !== '') {
            $chips[] = [$label . ': ' . $filters[$key], $key];
        }
    }
    echo '<div class="directory-results"><div class="result-bar"><p class="result-count" role="status">' .
        esc_html($total . ' ' . ($total === 1 ? 'Treffer' : 'Treffer')) .
        ($chips ? '' : esc_html(' · alle Mitglieder')) .
        '</p>';
    if ($chips) {
        echo '<ul class="filter-chips" aria-label="Aktive Filter">';
        foreach ($chips as [$text, $key]) {
            $without = array_filter(array_merge($state, [$key => '']));
            echo '<li><a class="chip" href="' .
                esc_url(portal_url('members', $without)) .
                '" aria-label="' .
                esc_attr($text . ' entfernen') .
                '">' .
                esc_html($text) .
                '<span aria-hidden="true">×</span></a></li>';
        }
        if (count($chips) > 1) {
            echo '<li><a class="text-link" href="' .
                esc_url(portal_url('members')) .
                '">Alle entfernen</a></li>';
        }
        echo '</ul>';
    }
    echo '</div><div class="member-grid">';
    $back = http_build_query(array_filter(array_merge($state, ['member_page' => $page > 1 ? $page : ''])));
    foreach (array_slice($matches, ($page - 1) * 25, 25) as $row) {
        $u = $row['user'];
        $data = $row['shared'];
        $meta = array_filter([$data['team'], $data['age_class'], $data['phase']]);
        $line = esc_html(implode(' · ', $meta));
        if ($data['city'] !== '') {
            $line .= ($line !== '' ? ' · ' : '') . '<b>' . esc_html($data['city']) . '</b>';
        }
        echo '<a class="et-member" id="m-' .
            (int) $u->ID .
            '" href="' .
            esc_url(portal_url('member', array_filter(['member' => $u->ID, 'back' => rawurlencode($back)]))) .
            '">' .
            member_avatar($u->ID, $u->display_name) .
            '<span><strong>' .
            esc_html($u->display_name) .
            '</strong>' .
            ($line !== '' ? '<small>' . $line . '</small>' : '<small>Noch keine Angaben geteilt</small>') .
            '</span><span class="member-arrow" aria-hidden="true">→</span></a>';
    }
    echo '</div>';
    if (!$total) {
        echo '<div class="directory-empty"><h2>' .
            ($q !== ''
                ? esc_html('Niemand gefunden für „' . $q . '“.')
                : 'Niemand passt zu diesen Filtern.') .
            '</h2><p>Prüfe die Schreibweise, suche nach Vor- oder Nachname, Team oder Ort, oder entferne einzelne Filter oben.</p>' .
            ($chips
                ? '<a class="button" href="' . esc_url(portal_url('members')) . '">Alle Mitglieder zeigen</a>'
                : '') .
            '</div>';
    }
    if ($pages > 1) {
        echo '<nav class="pagination" aria-label="Mitgliederseiten">';
        $args = array_filter(array_merge(['q' => $q], $filters));
        if ($page > 1) {
            echo '<a class="button" href="' .
                esc_url(portal_url('members', array_merge($args, ['member_page' => $page - 1]))) .
                '">← Zurück</a>';
        }
        echo '<span>Seite ' . esc_html($page . ' von ' . $pages) . '</span>';
        if ($page < $pages) {
            echo '<a class="button" href="' .
                esc_url(portal_url('members', array_merge($args, ['member_page' => $page + 1]))) .
                '">Weiter →</a>';
        }
        echo '</nav>';
    }
    echo '</div>';
}
function member_age($data) {
    if (empty($data['show_age']) || empty($data['birthday']) || !is_string($data['birthday'])) {
        return null;
    }
    $birth = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['birthday'], wp_timezone());
    $today = new \DateTimeImmutable('today', wp_timezone());
    return $birth && $birth->format('Y-m-d') === $data['birthday'] && $birth <= $today
        ? $birth->diff($today)->y
        : null;
}
function render_member($id) {
    if (!member_access() || !is_portal_user($id)) {
        echo '<div class="portal-empty"><h1>Profil nicht verfügbar.</h1><p>Dieses Profil gibt es nicht oder es ist nicht freigeschaltet.</p></div>';
        return;
    }
    $u = get_user_by('id', $id);
    $data = profile_data($id);
    echo '<a class="text-link service-back" href="' .
        esc_url(directory_back_url($id)) .
        '">← ' .
        (directory_param('back') !== '' ? 'Zurück zur Suche' : 'Mitglieder') .
        '</a><header class="member-profile-header">' .
        member_avatar($id, $u->display_name) .
        '<div><h1>' .
        esc_html($u->display_name) .
        '</h1><p>';
    $meta = array_filter([
        visible_value($data, 'team'),
        visible_value($data, 'age_class'),
        visible_value($data, 'phase')
    ]);
    $age = member_age($data);
    if ($age !== null) {
        $meta[] = $age . ' Jahre';
    }
    echo esc_html(implode(' · ', $meta));
    $city = visible_value($data, 'city');
    if ($city !== '') {
        echo ($meta ? ' · ' : '') . '<strong>' . esc_html($city) . '</strong>';
    }
    echo '</p></div>';
    if (manager_access() || $id === get_current_user_id()) {
        echo '<a class="button" href="' .
            esc_url(
                portal_url($id === get_current_user_id() ? 'profile' : 'edit-member', ['member' => $id])
            ) .
            '">Profil bearbeiten</a>';
    }
    echo '</header><div class="member-profile-sections">';
    $shown = false;
    foreach (profile_groups() as $title => $fields) {
        $values = [];
        foreach ($fields as $key => $label) {
            if (in_array($key, ['team', 'age_class', 'phase', 'city'], true)) {
                continue;
            }
            $v = visible_value($data, $key);
            if (is_scalar($v) && (string) $v !== '') {
                $values[$key] = [$label, (string) $v];
            }
        }
        $stations = $title === 'Hockey-Lebenslauf' ? shared_stations($data) : [];
        $legacy = $title === 'Hockey-Lebenslauf' ? visible_value($data, 'vita') : '';
        if (!$values && !$stations && !$legacy) {
            continue;
        }
        $shown = true;
        echo '<section class="member-profile-section"><h2>' . esc_html($title) . '</h2><dl>';
        foreach ($values as $key => $pair) {
            echo '<div class="' .
                (long_profile_field($key) ? 'full' : '') .
                '"><dt>' .
                esc_html($pair[0]) .
                '</dt><dd>' .
                nl2br(esc_html($pair[1])) .
                '</dd></div>';
        }
        echo '</dl>';
        if ($stations) {
            echo '<h3>DHB-Vita</h3><ol class="dhb-stations">';
            foreach ($stations as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $from = $row['from'] ?? '';
                $to = $row['to'] ?? '';
                echo '<li><span class="station-years">' .
                    esc_html($from !== '' ? $from . ' – ' . ($to !== '' ? $to : 'heute') : 'Zeitraum offen') .
                    '</span><div><strong>' .
                    esc_html($row['role'] ?? '') .
                    '</strong><p>' .
                    esc_html(
                        implode(' · ', array_filter([$row['organisation'] ?? '', $row['age_class'] ?? '']))
                    ) .
                    '</p></div></li>';
            }
            echo '</ol>';
        }
        if ($legacy) {
            echo '<h3>Hockey-Lebenslauf</h3><p>' . nl2br(esc_html($legacy)) . '</p>';
        }
        echo '</section>';
    }
    if (!$shown) {
        echo '<p class="profile-empty">Weitere Profilangaben wurden noch nicht mit dem Netzwerk geteilt.</p>';
    }
    echo '</div>';
}
