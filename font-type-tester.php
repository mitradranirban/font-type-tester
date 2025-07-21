<?php
/**
 * Plugin Name: Font Type Tester
 * Plugin URI: https://github.com/mitradranirban/font-type-tester
 * Description: A comprehensive font testing tool with admin interface for font management.
 * Version: 1.1.7
 * Author: Anirban Mitra
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

class fotyte_FontTypeTester {
    private $version = '1.1.7';

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
        if ($hook !== 'settings_page_fotyte-font-type-tester') return;
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
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'fotyte_font_tester')) return;

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
        if (!current_user_can('manage_options')) wp_die('Access denied');
        $fonts = $this->fotyte_get_fonts();
        ?>
        <div class="wrap"><h1>Font Type Tester</h1>
            <form id="fotyte-font-upload-form" enctype="multipart/form-data">
                <input type="text" name="font_name" placeholder="Font Name (optional)">
                <input type="file" name="font_file" accept=".ttf,.otf,.woff,.woff2" required>
                <button class="button button-primary">Upload Font</button>
            </form>
            <hr />
            <h2>Uploaded Fonts</h2>
            <ul>
                <?php if (!$fonts): ?>
                    <li>No fonts found.</li>
                <?php else: foreach ($fonts as $font): ?>
                    <li><strong><?php echo esc_html($font->font_name); ?></strong> <button class="button fotyte-delete-font" data-id="<?php echo esc_attr($font->id); ?>">Delete</button></li>
                <?php endforeach; endif; ?>
            </ul>
            <hr />
            <h2>Usage</h2>
            <p>Use shortcode <code>[fotyte_font_tester]</code> in any page or post.</p>
        </div>
        <?php
    }

    public function fotyte_upload_font() {
        check_ajax_referer('fotyte_font_tester_nonce', 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
    
        if (!isset($_FILES['font_file']) || empty($_FILES['font_file']['name'])) {
            wp_send_json_error('No font file uploaded');
        }
    
        $file = $_FILES['font_file'];
    
        // Validate extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['ttf', 'otf', 'woff', 'woff2'];
        if (!in_array($ext, $allowed)) {
            wp_send_json_error('Invalid file type');
        }
    
        // Upload via WP function
        $uploaded = wp_handle_upload($file, ['test_form' => false]);
        if (!empty($uploaded['error'])) {
            wp_send_json_error($uploaded['error']);
        }
    
        $upload_dir = wp_upload_dir();
        $font_dir  = $upload_dir['basedir'] . '/font-tester/';
        $obfuscated_name = 'fotyte_' . wp_generate_password(12, false) . '.' . $ext;
        $target_path = $font_dir . $obfuscated_name;
    
        // Use the WP Filesystem API
        global $wp_filesystem;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        if (!$wp_filesystem->move($uploaded['file'], $target_path, true)) {
            wp_send_json_error('Could not move file');
        }
    
        $font_name = !empty($_POST['font_name']) ? sanitize_text_field(wp_unslash($_POST['font_name'])) : pathinfo($file['name'], PATHINFO_FILENAME);
    
        global $wpdb;
        $result = $wpdb->insert("{$wpdb->prefix}fotyte_font_tester_fonts", [
            'font_name' => $font_name,
            'original_filename' => $file['name'],
            'obfuscated_filename' => $obfuscated_name,
            'file_path' => $target_path
        ]);
    
        if (!$result) {
            wp_send_json_error('DB insert failed');
        }
    
        wp_cache_delete('fotyte_all_fonts', 'fotyte_font_tester');
        wp_send_json_success(['font_name' => $font_name]);
    }
    

    public function fotyte_delete_font() {
        check_ajax_referer('fotyte_font_tester_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        if (!isset($_POST['font_id']) || !is_numeric($_POST['font_id'])) wp_send_json_error('Invalid ID');
        $id = intval($_POST['font_id']);

        global $wpdb;
        $table = $wpdb->prefix . 'fotyte_font_tester_fonts';
        $font = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        if (!$font) wp_send_json_error('Font not found');

        if (file_exists($font->file_path)) {
            wp_delete_file($font->file_path);
        }

        $wpdb->delete($table, ['id' => $id]);
        wp_cache_delete('fotyte_all_fonts', 'fotyte_font_tester');

        wp_send_json_success('Font deleted');
    }

    private function fotyte_get_fonts() {
        $cache = wp_cache_get('fotyte_all_fonts', 'fotyte_font_tester');
        if ($cache !== false) {
            return $cache;
        }
    
        global $wpdb;
        $table = $wpdb->prefix . 'fotyte_font_tester_fonts';
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE 1=1 ORDER BY upload_date DESC");
        $fonts = $wpdb->get_results($query);
    
        $url_base = wp_upload_dir()['baseurl'] . '/font-tester/';
        foreach ($fonts as $font) {
            $font->url = $url_base . $font->obfuscated_filename;
        }
    
        wp_cache_set('fotyte_all_fonts', $fonts, 'fotyte_font_tester', HOUR_IN_SECONDS);
        return $fonts;
    }
    

    private function fotyte_get_frontend_css() {
        return '#fotyte-font-preview{padding:20px;border:1px dashed #ccc;background:#f9f9f9;}';
    }

    private function fotyte_get_frontend_js() {
        return "jQuery(document).ready(function($){
            $('#fotyte-font-selector').on('change',function(){
                const fontUrl=$(this).val();
                const fontName=$(this).find('option:selected').text();
                if(!fontUrl){$('#fotyte-font-preview').text('Select a font');return;}
                const id='fotyte-font-'+Date.now();
                const fontFace='@font-face{font-family:\"'+id+'\";src:url(\"'+fontUrl+'\");}';
                $('<style>').attr('id',id).text(fontFace).appendTo('head');
                $('#fotyte-font-preview').css('font-family','\"'+id+'\",sans-serif').text($('#fotyte-sample-text').val());
            });
            $('#fotyte-sample-text').on('input',function(){
                $('#fotyte-font-preview').text($(this).val());
            });
        });";
    }

    private function fotyte_get_admin_css() {
        return '#fotyte-font-upload-form input{display:block;margin:5px 0;}';
    }

    private function fotyte_get_admin_js() {
        return "jQuery(document).ready(function($){
            $('#fotyte-font-upload-form').on('submit',function(e){
                e.preventDefault();
                const form = new FormData(this);
                form.append('action','fotyte_upload_font');
                form.append('nonce',fotyteFontTesterAdmin.nonce);
                $.ajax({
                    method:'POST',
                    url:fotyteFontTesterAdmin.ajax_url,
                    data:form,
                    processData:false,
                    contentType:false,
                    success:function(r){
                        if(r.success){alert('Uploaded');location.reload();}else{alert('Error:'+r.data);}
                    }
                });
            });
            $('.fotyte-delete-font').on('click',function(){
                if(!confirm('Delete this font?'))return;
                $.post(fotyteFontTesterAdmin.ajax_url,{
                    action:'fotyte_delete_font',
                    nonce:fotyteFontTesterAdmin.nonce,
                    font_id:$(this).data('id')
                },function(r){
                    if(r.success)location.reload();
                    else alert('Error:'+r.data);
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
