<?php
/**
 * Plugin Name: Font Type Tester
 * Plugin URI: https://github.com/mitradranirban/font-type-tester
 * Description: A comprehensive font testing tool with admin interface for font management.
 * Version: 1.1.6
 * Author: Anirban Mitra
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) exit; // Prevent direct access

class fotyte_FontTypeTester {
    private $fotyte_plugin_url;

    public function __construct() {
        $this->fotyte_plugin_url = plugin_dir_url(__FILE__);

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
    }

    public function fotyte_add_admin_menu() {
        add_options_page('Font Tester', 'Font Tester', 'manage_options', 'fotyte-font-type-tester', [$this, 'fotyte_admin_page']);
    }

    public function fotyte_enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_fotyte-font-type-tester') return;

        wp_register_style('fotyte-font-tester-admin-css', false);
        wp_enqueue_style('fotyte-font-tester-admin-css');
        wp_add_inline_style('fotyte-font-tester-admin-css', $this->fotyte_get_admin_css());

        wp_register_script('fotyte-font-tester-admin-js', false, ['jquery'], null, true);
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

        wp_register_style('fotyte-font-tester-css', false);
        wp_enqueue_style('fotyte-font-tester-css');
        wp_add_inline_style('fotyte-font-tester-css', $this->fotyte_get_frontend_css());

        wp_register_script('fotyte-font-tester-js', false, ['jquery'], null, true);
        wp_enqueue_script('fotyte-font-tester-js');
        wp_add_inline_script('fotyte-font-tester-js', $this->fotyte_get_frontend_js());

        wp_localize_script('fotyte-font-tester-js', 'fotyteFontTester', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fotyte_font_tester_nonce'),
        ]);
    }

    public function fotyte_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        $fonts = $this->fotyte_get_fonts();

        echo '<div class="wrap">';
        echo '<h1>Font Type Tester</h1>';
        echo '
        <form id="fotyte-font-upload-form" enctype="multipart/form-data">
            <input type="text" name="font_name" placeholder="Font Name">
            <input type="file" name="font_file" accept=".ttf,.otf,.woff,.woff2" required>
            <input type="submit" value="Upload" class="button button-primary">
        </form>
        <hr>
        <h2>Uploaded Fonts</h2>
        ';
        if (empty($fonts)) {
            echo '<p>No fonts found.</p>';
        } else {
            echo '<ul>';
            foreach ($fonts as $font) {
                echo '<li>' . esc_html($font->font_name) . ' — <button class="button fotyte-delete-font" data-id="' . esc_attr($font->id) . '">Delete</button></li>';
            }
            echo '</ul>';
        }

        echo '<div class="card"><h2>Usage Instructions</h2>
        <p>To display the font tester on your website, use the following shortcode:</p>
        <code>[fotyte_font_tester]</code>
        <p>Users will be able to select from the fonts you have uploaded here and test them with various typography controls.</p>
        </div>';
        echo '</div>';
    }

    public function fotyte_upload_font() {
        check_ajax_referer('fotyte_font_tester_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');

        if (empty($_FILES['font_file'])) wp_send_json_error('No file');

        $font_file = $_FILES['font_file'];
        $type = wp_check_filetype($font_file['name']);
        $ext = strtolower($type['ext']);
        if (!in_array($ext, ['ttf', 'otf', 'woff', 'woff2'])) wp_send_json_error('Invalid font type');

        $upload_dir = wp_upload_dir();
        $path = $upload_dir['basedir'] . '/font-tester/';
        $url = $upload_dir['baseurl'] . '/font-tester/';
        $filename = 'fotyte_font_' . wp_generate_password(12, false) . ".$ext";
        if (!file_exists($path)) {
            wp_mkdir_p($path);
        }

        $movefile = wp_handle_upload($font_file, ['test_form' => false]);
        if (!empty($movefile['error'])) wp_send_json_error($movefile['error']);

        $target = $path . $filename;
        rename($movefile['file'], $target);

        $font_name = sanitize_text_field($_POST['font_name']) ?: pathinfo($font_file['name'], PATHINFO_FILENAME);
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'fotyte_font_tester_fonts', [
            'font_name' => $font_name,
            'original_filename' => $font_file['name'],
            'obfuscated_filename' => $filename,
            'file_path' => $target,
        ]);

        wp_send_json_success(['url' => $url . $filename, 'font_name' => $font_name]);
    }

    public function fotyte_delete_font() {
        check_ajax_referer('fotyte_font_tester_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');

        $id = intval($_POST['font_id']);
        global $wpdb;
        $font = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fotyte_font_tester_fonts WHERE id = %d", $id));
        if (!$font) wp_send_json_error('Font not found');

        if (file_exists($font->file_path)) {
            wp_delete_file($font->file_path);
        }

        $wpdb->delete($wpdb->prefix . 'fotyte_font_tester_fonts', ['id' => $id]);
        wp_send_json_success('Deleted');
    }

    private function fotyte_get_fonts() {
        global $wpdb;
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'] . '/font-tester/';
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fotyte_font_tester_fonts ORDER BY upload_date DESC");
        foreach ($results as $font) {
            $font->url = $base_url . $font->obfuscated_filename;
        }
        return $results;
    }

    public function fotyte_render_frontend() {
        $fonts = $this->fotyte_get_fonts();
        ob_start();
        ?>
        <div id="fotyte-font-tester-container">
            <div>
                <select id="fotyte-font-selector">
                    <option value="">Select a font</option>
                    <?php foreach ($fonts as $font): ?>
                    <option value="<?php echo esc_url($font->url); ?>">
                        <?php echo esc_html($font->font_name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <textarea id="fotyte-sample-text" rows="5" style="width:100%">The quick brown fox jumps over the lazy dog.</textarea>
            <div id="fotyte-font-preview" style="margin-top:20px;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function fotyte_get_frontend_css() {
        return '
#fotyte-font-tester-container { padding: 20px; background: #fff; border: 1px solid #ccc; margin: 10px 0; }
#fotyte-font-preview { padding: 10px; border: 1px dashed #ccc; background: #f9f9f9; }
        ';
    }

    private function fotyte_get_admin_css() {
        return '
form#fotyte-font-upload-form { margin-bottom: 20px; }
form#fotyte-font-upload-form input[type="text"],
form#fotyte-font-upload-form input[type="file"] { display: block; margin: 5px 0; }
        ';
    }

    private function fotyte_get_frontend_js() {
       return <<<JS
jQuery(document).ready(function($) {
    $("#fotyte-font-selector").on("change", function() {
        const fontUrl = $(this).val();
        const fontName = $(this).find("option:selected").text();
        if (!fontUrl) {
            $("#fotyte-font-preview").text("Select a font");
            return;
        }
        const id = "fotyte-font-" + Date.now();
        const fontFace = "@font-face{font-family:'" + id + "';src:url('" + fontUrl + "');}";
        $("<style>").attr("id", id).text(fontFace).appendTo("head");
        $("#fotyte-font-preview").css("font-family", "'" + id + "', sans-serif").text($("#fotyte-sample-text").val());
    });

    $("#fotyte-sample-text").on("input", function() {
        $("#fotyte-font-preview").text($(this).val());
    });
});
JS;
    }

    private function fotyte_get_admin_js() {
        return '
jQuery(document).ready(function($) {
    $("#fotyte-font-upload-form").on("submit", function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append("action", "fotyte_upload_font");
        formData.append("nonce", fotyteFontTesterAdmin.nonce);
        $.ajax({
            url: fotyteFontTesterAdmin.ajax_url,
            method: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success(res) {
                if (res.success) {
                    alert("Font uploaded!");
                    location.reload();
                } else {
                    alert("Error: " + res.data);
                }
            }
        });
    });

    $(".fotyte-delete-font").on("click", function() {
        if (!confirm("Delete this font?")) return;
        const id = $(this).data("id");
        $.post(fotyteFontTesterAdmin.ajax_url, {
            action: "fotyte_delete_font",
            nonce: fotyteFontTesterAdmin.nonce,
            font_id: id
        }, function(res) {
            if (res.success) location.reload();
            else alert("Error: " + res.data);
        });
    });
});
        ';
    }
}

new fotyte_FontTypeTester();
?>
