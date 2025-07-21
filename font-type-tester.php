<?php
/**
 * Plugin Name: Font Type Tester
 * Plugin URI: https://github.com/mitradranirban/font-type-tester
 * Description: A comprehensive font testing tool with admin interface for font management.
 * Version: 1.1.5
 * Author: Anirban Mitra
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) exit; // Prevent direct access

class fotyte_FontTypeTester {
    private $plugin_url;

    public function __construct() {
        $this->plugin_url = plugin_dir_url(__FILE__);

        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_shortcode('font_tester', [$this, 'render_frontend']);
        
        add_action('wp_ajax_upload_font', [$this, 'upload_font']);
        add_action('wp_ajax_delete_font', [$this, 'delete_font']);

        register_activation_hook(__FILE__, [$this, 'on_activation']);
        register_deactivation_hook(__FILE__, [$this, 'on_deactivation']);
    }

    public function init() {
        $upload_dir = wp_upload_dir();
        $font_dir = $
