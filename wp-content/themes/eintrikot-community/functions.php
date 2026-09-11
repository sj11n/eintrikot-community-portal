<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_action( 'after_setup_theme', function () {
    add_theme_support( 'editor-styles' );
    add_editor_style( 'assets/site.css' );
} );
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'eintrikot-community', get_theme_file_uri( 'assets/site.css' ), array(), '0.1.0' );
} );
add_action( 'init', function () {
    register_block_pattern_category( 'eintrikot', array( 'label' => 'EINTRIKOT' ) );
} );
