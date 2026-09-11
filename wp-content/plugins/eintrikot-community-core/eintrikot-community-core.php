<?php
/**
 * Plugin Name: EINTRIKOT Community Core
 * Description: Getrennte Einrichtung und Berechtigungen für das EINTRIKOT-Portal. Entwicklungsstand.
 * Version: 0.1.0
 * Requires PHP: 8.1
 */
namespace Eintrikot\Community;
if ( ! defined( 'ABSPATH' ) ) { exit; }

function activate() {
    add_role( 'eintrikot_member', 'EINTRIKOT Mitglied', array( 'read' => true, 'eintrikot_portal' => true ) );
    add_role( 'eintrikot_editor', 'EINTRIKOT Redaktion', array( 'read' => true, 'eintrikot_portal' => true ) );
    add_role( 'eintrikot_board', 'EINTRIKOT Vorstand', array( 'read' => true, 'eintrikot_portal' => true, 'eintrikot_manage_members' => true ) );
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $admin->add_cap( 'eintrikot_portal' );
        $admin->add_cap( 'eintrikot_manage_members' );
    }
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );

function pages() {
    return array( 'startseite' => 'Startseite', 'verein' => 'Der Verein', 'menschen' => 'Menschen', 'engagement' => 'Engagement', 'unterstuetzen' => 'Unterstützen' );
}
add_action( 'admin_menu', function () {
    add_menu_page( 'EINTRIKOT Community', 'Community-Aufbau', 'manage_options', 'eintrikot-community-setup', __NAMESPACE__ . '\setup_page', 'dashicons-groups' );
} );
function setup_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    echo '<div class="wrap"><h1>EINTRIKOT Community aufbauen</h1><p>Entwicklungsstand 0.1.0. Dieser Schritt erstellt nur neue Seitenentwürfe. Er veröffentlicht nichts und ändert weder Startseite noch bestehende Inhalte.</p>';
    if ( isset( $_GET['et_created'] ) ) { echo '<div class="notice notice-success"><p>Einrichtung geprüft. Die erstellten Seiten findest du unter Seiten → Entwürfe.</p></div>'; }
    echo '<h2>Bearbeitbare Seiten vorbereiten</h2><p>Texte, Cover-Bilder und weitere Bilder werden anschließend direkt im Seiteneditor gepflegt. Öffentliche News werden als normale Beiträge erstellt.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'eintrikot_prepare_pages' );
    echo '<input type="hidden" name="action" value="eintrikot_prepare_pages">';
    submit_button( 'Seitenentwürfe anlegen' );
    echo '</form><h2>Noch in Umsetzung</h2><p>Profile, Mitgliederverzeichnis, geschützte Vereinsinfos, Kalender, Service-Anfragen und Änderungsprotokoll. Diese Funktionen sind in diesem Paket noch nicht verfügbar.</p></div>';
}
add_action( 'admin_post_eintrikot_prepare_pages', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) ); }
    check_admin_referer( 'eintrikot_prepare_pages' );
    $theme = wp_get_theme( 'eintrikot-community' );
    if ( ! $theme->exists() ) { wp_die( 'Bitte zuerst das Theme EINTRIKOT Community installieren.' ); }
    $created = get_option( 'eintrikot_draft_pages', array() );
    foreach ( pages() as $slug => $title ) {
        if ( ! empty( $created[$slug] ) && get_post( $created[$slug] ) ) { continue; }
        $file = $theme->get_stylesheet_directory() . '/patterns/' . $slug . '.php';
        if ( ! is_readable( $file ) ) { wp_die( 'Eine Seitenvorlage fehlt.' ); }
        // Read authored block markup, never execute the pattern PHP during setup.
        $source = file_get_contents( $file );
        $end = strpos( $source, '?>' );
        if ( false === $end ) { wp_die( 'Ungültige Seitenvorlage.' ); }
        $content = trim( substr( $source, $end + 2 ) );
        $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => $title . ' – Community', 'post_name' => 'community-' . $slug, 'post_content' => wp_slash( $content ) ), true );
        if ( is_wp_error( $id ) ) { wp_die( esc_html( $id->get_error_message() ) ); }
        $created[$slug] = $id;
        update_option( 'eintrikot_draft_pages', $created, false );
    }
    wp_safe_redirect( admin_url( 'admin.php?page=eintrikot-community-setup&et_created=1' ) );
    exit;
} );
