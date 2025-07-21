<?php
/**
 * Plugin Name: Font Type Tester
 * Plugin URI: https://github.com/mitradranirban/font-type-tester
 * Description: A font testing tool with admin interface for secure font upload and typography preview
 * Version: 1.1.8
 * Author: Anirban Mitra
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

class fotyte_FontTypeTester {
    private $version = '1.1.8';

    public function __construct() {
        add_action('init', [$this, 'fotyte_init']);
        add_action('admin_menu', [$this, 'fotyte_add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'fotyte_enqueue_admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'fotyte_enqueue_frontend_assets']);
        add_shortcode('fotyte_font_tester', [$this, 'fotyte_render_frontend']);

        add_action('wp_ajax_fotyte_upload_font', [$this, 'fotyte_upload_font']);
        add_action('wp_ajax_fotyte_delete_font', [$this, 'fotyte_delete_font']);

        register_activation_hook(__FILE__, [$this, 'fotyte_on_activation']);
        register_deactivation_hook(__FILE__, [$this, 'fotyte_on_deactivation']);
    }

    public function fotyte_init() {
        $upload_dir = wp_upload_dir();
        $font_dir = $upload_dir['basedir'] . '/font-tester/';
        if (!file_exists($font_dir)) {
            wp_mkdir_p($font_dir);
        }
    }

    public function fotyte_on_activation() {
        global $wpdb;
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

    public function fotyte_add_admin_menu() {
        add_options_page('Font Tester', 'Font Tester', 'manage_options', 'fotyte-font-type-tester', [$this, 'fotyte_admin_page']);
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
            <h1>Font Type Tester</h1>
            <form id="fotyte-font-upload-form" enctype="multipart/form-data">
                <label>Font Name (optional):</label>
                <input type="text" name="font_name" />
                <label>Font File:</label>
                <input type="file" name="font_file" accept=".ttf,.otf,.woff,.woff2" required />
                <button class="button button-primary">Upload Font</button>
            </form>
            <hr />
            <h2>Uploaded Fonts</h2>
            <?php if (empty($fonts)) : ?>
                <p>No fonts found.</p>
            <?php else : ?>
                <ul>
                    <?php foreach ($fonts as $font): ?>
                        <li>
                            <?php echo esc_html($font->font_name); ?>
                            <button class="button fotyte-delete-font" data-id="<?php echo esc_attr($font->id); ?>">Delete</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p>Use shortcode: <code>[fotyte_font_tester]</code></p>
        </div>
        <?php
    }

    public function fotyte_upload_font() {
        check_ajax_referer('fotyte_font_tester_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!isset($_FILES['font_file']) || empty($_FILES['font_file']['name'])) {
            wp_send_json_error('Font file missing');
        }

        $file = $_FILES['font_file'];
        $allowed_ext = ['ttf', 'otf', 'woff', 'woff2'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            wp_send_json_error('Invalid font type');
        }

        $upload = wp_handle_upload($file, ['test_form' => false]);
        if (!empty($upload['error'])) {
            wp_send_json_error($upload['error']);
        }

        // Move using WordPress filesystem
        global $wp_filesystem;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();

        $upload_dir = wp_upload_dir();
        $font_dir = trailingslashit($upload_dir['basedir']) . 'font-tester/';
        $obfuscated_filename = 'fotyte_' . wp_generate_password(12, false) . '.' . $ext;
        $target_file = $font_dir . $obfuscated_filename;
        if (!$wp_filesystem->move($upload['file'], $target_file, true)) {
            wp_send_json_error('Storage error');
        }

        $font_name = isset($_POST['font_name']) ? sanitize_text_field(wp_unslash($_POST['font_name'])) : pathinfo($file['name'], PATHINFO_FILENAME);

        global $wpdb;
        $table = $wpdb->prefix . 'fotyte_font_tester_fonts';
        $wpdb->insert($table, [
            'font_name' => $font_name,
            'original_filename' => sanitize_file_name($file['name']),
            'obfuscated_filename' => $obfuscated_filename,
            'file_path' => $target_file,
        ]);

        wp_cache_delete('fotyte_all_fonts', 'fotyte_font_tester');

        wp_send_json_success(['font_name' => $font_name]);
    }

    public function fotyte_delete_font() {
        check_ajax_referer('fotyte_font_tester_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $font_id = isset($_POST['font_id']) ? (int) $_POST['font_id'] : 0;
        if ($font_id <= 0) {
            wp_send_json_error('Invalid ID');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'fotyte_font_tester_fonts';
        $font = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $font_id));
        if (!$font) {
            wp_send_json_error('Font not found');
        }

        if (file_exists($font->file_path)) {
            wp_delete_file($font->file_path);
        }

        $wpdb->delete($table, ['id' => $font_id]);
        wp_cache_delete('fotyte_all_fonts', 'fotyte_font_tester');

        wp_send_json_success('Deleted');
    }

    private function fotyte_get_fonts() {
        $cache = wp_cache_get('fotyte_all_fonts', 'fotyte_font_tester');
        if ($cache !== false) {
            return $cache;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'fotyte_font_tester_fonts';
        $fonts = $wpdb->get_results("SELECT * FROM {$table} ORDER BY upload_date DESC");

        $url_base = wp_upload_dir()['baseurl'] . '/font-tester/';
        foreach ($fonts as $font) {
            $font->url = $url_base . $font->obfuscated_filename;
        }

        wp_cache_set('fotyte_all_fonts', $fonts, 'fotyte_font_tester', 3600);
        return $fonts;
    }

    private function fotyte_get_frontend_css() {
        return '#fotyte-font-preview{padding:20px;border:1px dashed #ccc;background:#f9f9f9;}';
    }

    private function fotyte_get_frontend_js() {
        return "jQuery(document).ready(function($){
            $('#fotyte-font-selector').on('change', function(){
                const fontUrl = $(this).val();
                const fontId = 'fotyte-' + Date.now();
                const fontFace = '@font-face{font-family: \"' + fontId + '\"; src: url(\"' + fontUrl + '\");}';
                $('<style>').attr('id', fontId).text(fontFace).appendTo('head');
                $('#fotyte-font-preview').css('font-family', fontId).text($('#fotyte-sample-text').val());
            });
            $('#fotyte-sample-text').on('input', function(){
                $('#fotyte-font-preview').text($(this).val());
            });
        });";
    }

    private function fotyte_get_admin_css() {
        return '#fotyte-font-upload-form input{display:block;margin:5px 0;}';
    }

    private function fotyte_get_admin_js() {
        return "jQuery(document).ready(function($){
            $('#fotyte-font-upload-form').on('submit', function(e){
                e.preventDefault();
                let formData = new FormData(this);
                formData.append('action', 'fotyte_upload_font');
                formData.append('nonce', fotyteFontTesterAdmin.nonce);
                $.ajax({
                    method: 'POST',
                    url: fotyteFontTesterAdmin.ajax_url,
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res){
                        if(res.success){ location.reload(); } else { alert(res.data); }
                    }
                });
            });
            $('.fotyte-delete-font').on('click', function(){
                if (!confirm('Delete this font?')) return;
                $.post(fotyteFontTesterAdmin.ajax_url, {
                    action: 'fotyte_delete_font',
                    nonce: fotyteFontTesterAdmin.nonce,
                    font_id: $(this).data('id')
                }, function(res){
                    if(res.success){ location.reload(); } else { alert(res.data); }
                });
            });
        });";
    }

    public function fotyte_render_frontend() {
        $fonts = $this->fotyte_get_fonts();
        ob_start(); ?>
        <div id="fotyte-font-tester-container">
            <select id="fotyte-font-selector">
                <option value="">Select a font</option>
                <?php foreach ($fonts as $font): ?>
                    <option value="<?php echo esc_url($font->url); ?>"><?php echo esc_html($font->font_name); ?></option>
                <?php endforeach; ?>
            </select>
            <textarea id="fotyte-sample-text" rows="4" style="width:100%">The quick brown fox jumps over the lazy dog.</textarea>
            <div id="fotyte-font-preview"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new fotyte_FontTypeTester();
