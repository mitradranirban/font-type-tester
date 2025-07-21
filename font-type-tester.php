<?php
/**
 * Plugin Name: Font Type Tester
 * Plugin-url: https://github.com/mitradranirban/font-type-tester
 * Description: A comprehensive font testing tool with real-time typography controls
 * submitter: mitradranirban
 * Author: mitradranirban
 * Version: 1.1.9
 * License: GPL v3 or later
 * Text Domain: font-type-tester
 */

if (!defined('ABSPATH')) {
    exit;
}

class FotyteWordPressFontTester {
    
    private $version = '1.1.9';
    
    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'fotyte_on_activation']);
        register_deactivation_hook(__FILE__, [$this, 'fotyte_on_deactivation']);
        
        add_action('admin_menu', [$this, 'fotyte_add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'fotyte_enqueue_admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'fotyte_enqueue_frontend_assets']);
        add_action('wp_ajax_fotyte_upload_font', [$this, 'fotyte_handle_font_upload']);
        add_action('wp_ajax_fotyte_delete_font', [$this, 'fotyte_handle_font_delete']);
        
        add_shortcode('fotyte_font_tester', [$this, 'fotyte_font_tester_shortcode']);
    }
    
    public function fotyte_on_activation() {
        global $wpdb;
        
        $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'] . '/font-tester/';
        wp_mkdir_p($dir);
        
        $table = $wpdb->prefix . 'fotyte_font_tester_fonts';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            font_name varchar(255) NOT NULL,
            original_filename varchar(255) NOT NULL,
            obfuscated_filename varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            upload_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function fotyte_on_deactivation() {
        global $wp_filesystem;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        
        $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'] . '/font-tester/';
        if ($wp_filesystem->exists($dir)) {
            $wp_filesystem->rmdir($dir, true);
        }
        
        wp_cache_flush();
    }
    
    public function fotyte_handle_font_upload() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access', 'font-type-tester'));
        }
        
        if (!isset($_POST['fotyte_font_tester_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fotyte_font_tester_nonce'])), 'fotyte_font_tester_nonce')) {
            wp_die(esc_html__('Invalid nonce', 'font-type-tester'));
        }
        
        // Validate and sanitize file upload
        if (!isset($_FILES['font_file']) || !is_array($_FILES['font_file'])) {
            wp_die(esc_html__('No file uploaded', 'font-type-tester'));
        }
        
        // Properly sanitize file input
        $font_file = array_map('sanitize_text_field', wp_unslash($_FILES['font_file']));
        
        // Validate file type
        $allowed_mimes = [
            'ttf' => 'font/ttf',
            'otf' => 'font/otf', 
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        
        $filetype = wp_check_filetype(sanitize_file_name($font_file['name']), $allowed_mimes);
        if (!$filetype['ext']) {
            wp_die(esc_html__('Invalid font file type', 'font-type-tester'));
        }
        
        // Use WordPress safe file upload with original $_FILES for wp_handle_upload
        $upload_overrides = ['test_form' => false];
        $movefile = wp_handle_upload($_FILES['font_file'], $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Sanitize additional inputs
            $font_name = isset($_POST['font_name']) ? 
                sanitize_text_field(wp_unslash($_POST['font_name'])) : '';
            $original_filename = sanitize_file_name($font_file['name']);
            $obfuscated_filename = basename($movefile['file']);
            $file_path = esc_url_raw($movefile['url']);
            
            // Use WordPress built-in insert method instead of raw query
            global $wpdb;
            $table = $wpdb->prefix . 'fotyte_font_tester_fonts';
            
            // Direct database call necessary for custom table - no WordPress API alternative
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $inserted = $wpdb->insert(
                $table,
                [
                    'font_name' => $font_name,
                    'original_filename' => $original_filename,
                    'obfuscated_filename' => $obfuscated_filename,
                    'file_path' => $file_path
                ],
                ['%s', '%s', '%s', '%s']
            );
            
            if (false === $inserted) {
                wp_die(esc_html__('Database error while saving font', 'font-type-tester'));
            }
            
            // Invalidate cache
            wp_cache_delete('fotyte_all_fonts', 'fotyte_fonts');
            
        } else {
            wp_die(esc_html($movefile['error']));
        }
        
        wp_redirect(admin_url('options-general.php?page=fotyte-font-type-tester'));
        exit;
    }
    
    public function fotyte_handle_font_delete() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access', 'font-type-tester'));
        }
        
        if (!isset($_POST['fotyte_font_tester_nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fotyte_font_tester_nonce'])), 'fotyte_font_tester_nonce')) {
            wp_die(esc_html__('Invalid nonce', 'font-type-tester'));
        }
        
        $font_id = isset($_POST['font_id']) && is_numeric($_POST['font_id']) ? 
            absint($_POST['font_id']) : 0;
        
        if ($font_id > 0) {
            global $wpdb;
            $table = $wpdb->prefix . 'fotyte_font_tester_fonts';
            
            // Use WordPress built-in delete method - direct query necessary for custom table
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $deleted = $wpdb->delete($table, ['id' => $font_id], ['%d']);
            
            if ($deleted) {
                // Invalidate cache
                wp_cache_delete('fotyte_font_' . $font_id, 'fotyte_fonts');
                wp_cache_delete('fotyte_all_fonts', 'fotyte_fonts');
            }
        }
        
        wp_redirect(admin_url('options-general.php?page=fotyte-font-type-tester'));
        exit;
    }
    
    // Safe database query methods with caching
    public function fotyte_get_font_by_id($font_id) {
        if (!is_numeric($font_id)) {
            return null;
        }
        
        $font_id = absint($font_id);
        $cache_key = 'fotyte_font_' . $font_id;
        $font = wp_cache_get($cache_key, 'fotyte_fonts');
        
        if (false === $font) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'fotyte_font_tester_fonts';
            
            // Direct database query necessary for custom table - no WordPress API alternative
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $font = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}fotyte_font_tester_fonts WHERE id = %d",
                    $font_id
                ),
                ARRAY_A
            );
            
            wp_cache_set($cache_key, $font, 'fotyte_fonts', 3600);
        }
        
        return $font;
    }
    
    public function fotyte_get_fonts() {
        $cache_key = 'fotyte_all_fonts';
        $fonts = wp_cache_get($cache_key, 'fotyte_fonts');
        
        if (false === $fonts) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'fotyte_font_tester_fonts';
            
            // Direct database query necessary for custom table - no WordPress API alternative  
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $fonts = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}fotyte_font_tester_fonts ORDER BY upload_date DESC",
                ARRAY_A
            );
            
            wp_cache_set($cache_key, $fonts, 'fotyte_fonts', 3600);
        }
        
        return $fonts ? $fonts : [];
    }
    
    public function fotyte_add_admin_menu() {
        add_options_page(
            'Font Tester', 
            'Font Tester', 
            'manage_options', 
            'fotyte-font-type-tester', 
            [$this, 'fotyte_admin_page']
        );
    }
    
    public function fotyte_enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_fotyte-font-type-tester') {
            return;
        }
        
        wp_register_style('fotyte-font-tester-admin-css', false, [], $this->version);
        wp_enqueue_style('fotyte-font-tester-admin-css');
        wp_add_inline_style('fotyte-font-tester-admin-css', $this->fotyte_get_admin_css());
        
        wp_register_script('fotyte-font-tester-admin-js', false, ['jquery'], $this->version, true);
        wp_enqueue_script('fotyte-font-tester-admin-js');
        wp_add_inline_script('fotyte-font-tester-admin-js', $this->fotyte_get_admin_js());
        
        wp_localize_script('fotyte-font-tester-admin-js', 'fotyteFontTesterAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fotyte_font_tester_nonce'),
        ]);
    }
    
    public function fotyte_enqueue_frontend_assets() {
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'fotyte_font_tester')) {
            return;
        }
        
        wp_register_style('fotyte-font-tester-css', false, [], $this->version);
        wp_enqueue_style('fotyte-font-tester-css');
        wp_add_inline_style('fotyte-font-tester-css', $this->fotyte_get_frontend_css());
        
        wp_register_script('fotyte-font-tester-js', false, ['jquery'], $this->version, true);
        wp_enqueue_script('fotyte-font-tester-js');
        wp_add_inline_script('fotyte-font-tester-js', $this->fotyte_get_frontend_js());
        
        wp_localize_script('fotyte-font-tester-js', 'fotyteFontTester', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fotyte_font_tester_nonce'),
        ]);
    }
    
    public function fotyte_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied', 'font-type-tester'));
        }
        
        $fonts = $this->fotyte_get_fonts();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Font Tester', 'font-type-tester'); ?></h1>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php?action=fotyte_upload_font')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('fotyte_font_tester_nonce', 'fotyte_font_tester_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Font Name', 'font-type-tester'); ?></th>
                        <td><input type="text" name="font_name" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Font File', 'font-type-tester'); ?></th>
                        <td><input type="file" name="font_file" accept=".ttf,.otf,.woff,.woff2" required /></td>
                    </tr>
                </table>
                <?php submit_button(esc_html__('Upload Font', 'font-type-tester')); ?>
            </form>
            
            <h2><?php esc_html_e('Uploaded Fonts', 'font-type-tester'); ?></h2>
            <?php if (empty($fonts)): ?>
                <p><?php esc_html_e('No fonts found.', 'font-type-tester'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Font Name', 'font-type-tester'); ?></th>
                            <th><?php esc_html_e('Original Filename', 'font-type-tester'); ?></th>
                            <th><?php esc_html_e('Upload Date', 'font-type-tester'); ?></th>
                            <th><?php esc_html_e('Actions', 'font-type-tester'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fonts as $font): ?>
                            <tr>
                                <td><?php echo esc_html($font['font_name']); ?></td>
                                <td><?php echo esc_html($font['original_filename']); ?></td>
                                <td><?php echo esc_html($font['upload_date']); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php?action=fotyte_delete_font')); ?>" style="display:inline;">
                                        <?php wp_nonce_field('fotyte_font_tester_nonce', 'fotyte_font_tester_nonce'); ?>
                                        <input type="hidden" name="font_id" value="<?php echo absint($font['id']); ?>" />
                                        <input type="submit" class="button button-secondary" value="<?php esc_attr_e('Delete', 'font-type-tester'); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure?', 'font-type-tester'); ?>');" />
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <h3><?php esc_html_e('Usage', 'font-type-tester'); ?></h3>
            <p><?php esc_html_e('Use shortcode:', 'font-type-tester'); ?></p>
            <code>[fotyte_font_tester]</code>
        </div>
        <?php
    }
    
    public function fotyte_font_tester_shortcode($atts) {
        $fonts = $this->fotyte_get_fonts();
        
        if (empty($fonts)) {
            return '<p>' . esc_html__('No fonts available for testing.', 'font-type-tester') . '</p>';
        }
        
        // Shortcode output would go here...
        return '<div id="fotyte-font-tester">Font tester interface</div>';
    }
    
    // CSS and JS methods would remain the same...
    private function fotyte_get_admin_css() {
        return '/* Admin CSS */';
    }
    
    private function fotyte_get_admin_js() {
        return '/* Admin JS */';
    }
    
    private function fotyte_get_frontend_css() {
        return '/* Frontend CSS */';
    }
    
    private function fotyte_get_frontend_js() {
        return '/* Frontend JS */';
    }
}

new FotyteWordPressFontTester();
?>