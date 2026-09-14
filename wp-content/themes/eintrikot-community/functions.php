<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_action( 'after_setup_theme', function () {
    add_theme_support( 'editor-styles' );
    add_editor_style( array('assets/mvp.css','assets/editor.css') );
} );
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'eintrikot-community', get_theme_file_uri( 'assets/site.css' ), array(), '0.2.0' );
} );
add_action( 'init', function () {
    register_block_pattern_category( 'eintrikot', array( 'label' => 'EINTRIKOT' ) );
} );

add_action('wp_enqueue_scripts', function () {
    if (is_page_template('mvp-public') || is_page_template('mvp-portal') || is_singular('post')) {
        wp_dequeue_style('eintrikot-community');
        wp_enqueue_style('eintrikot-mvp', get_theme_file_uri('assets/mvp.css'), array(), '0.6.0');
        wp_enqueue_style('eintrikot-wp-adapter', get_theme_file_uri('assets/wordpress.css'), array('eintrikot-mvp'), '0.6.0');
    }
}, 30);

add_action('login_enqueue_scripts',function(){wp_enqueue_style('eintrikot-login',get_theme_file_uri('assets/login.css'),array(),'0.6.0');});
add_filter('login_headerurl',fn()=>home_url('/'));
add_filter('login_headertext',fn()=>'EINTRIKOT');

// Administration remains available through wp-admin; the public design stays uncluttered.
add_filter('show_admin_bar','__return_false');
