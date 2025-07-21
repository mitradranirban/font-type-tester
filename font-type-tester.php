<?php
/**
 * Plugin Name: Font Type Tester
 * Plugin URI: https://github.com/mitradranirban/font-type-tester
 * Description: A comprehensive font testing tool with admin interface for font management
 * Version: 1.1.4
 * Author: Anirban Mitra
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class fotyte_FontTypeTester {

    private $plugin_url;
    private $plugin_path;

    public function __construct() {
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_path = plugin_dir_path(__FILE__);

        add_action('init', array($this, 'fotyte_init'));
        add_action('wp_enqueue_scripts', array($this, 'fotyte_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'fotyte_add_dynamic_styles'), 20);
        add_action('admin_enqueue_scripts', array($this, 'fotyte_enqueue_admin_scripts'));
        add_action('admin_menu', array($this, 'fotyte_add_admin_menu'));
        add_action('wp_ajax_upload_font', array($this, 'fotyte_handle_font_upload'));
        add_action('wp_ajax_delete_font', array($this, 'fotyte_handle_font_delete'));
        add_shortcode('font_tester', array($this, 'fotyte_render_font_tester'));

        register_activation_hook(__FILE__, array($this, 'fotyte_activate'));
        register_deactivation_hook(__FILE__, array($this, 'fotyte_deactivate'));
    }

    public function fotyte_init() {
        // Create uploads directory for fonts
        $upload_dir = wp_upload_dir();
        $font_dir = $upload_dir['basedir'] . '/font-tester/';
        if (!file_exists($font_dir)) {
            wp_mkdir_p($font_dir);
        }
    }

    public function fotyte_activate() {
        // Create database table for font management
        global $wpdb;

        $table_name = $wpdb->prefix . 'font_tester_fonts';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
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

        // Create the external files
        $this->fotyte_create_external_files();
    }

    public function fotyte_deactivate() {
        // Clean up fonts directory using WP_Filesystem
        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }

        $upload_dir = wp_upload_dir();
        $font_dir = $upload_dir['basedir'] . '/font-tester/';

        if ($wp_filesystem->exists($font_dir)) {
            $wp_filesystem->rmdir($font_dir, true);
        }
    }

    public function fotyte_add_admin_menu() {
        add_options_page(
            'Font Type Tester',
            'Font Tester',
            'manage_options',
            'font-type-tester',
            array($this, 'fotyte_admin_page')
        );
    }

    public function fotyte_enqueue_scripts() {
        // Register scripts and styles first
        wp_register_script(
            'font-tester-js',
            $this->plugin_url . 'font-tester.js',
            array('jquery'),
            '1.1.4',
            array(
                'in_footer' => true,
                'strategy' => 'defer'
            )
        );
        
        wp_register_style(
            'font-tester-css',
            $this->plugin_url . 'font-tester.css',
            array(),
            '1.1.4'
        );

        // Only enqueue on pages with the shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'font_tester')) {
            wp_enqueue_script('font-tester-js');
            wp_enqueue_style('font-tester-css');
            
            wp_localize_script('font-tester-js', 'fontTester', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('font_tester_nonce'),
                'plugin_url' => $this->plugin_url
            ));
        }
    }

    public function fotyte_add_dynamic_styles() {
        if (is_admin()) return;
        
        // Only add if shortcode is present on the page
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'font_tester')) {
            wp_add_inline_style('font-tester-css', '
                /* Dynamic font styles will be injected here via JavaScript */
                #font-preview { transition: all 0.3s ease; }
                .font-face-dynamic { font-display: swap; }
            ');
        }
    }

    public function fotyte_enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_font-type-tester') {
            return;
        }

        wp_register_script(
            'font-tester-admin-js',
            $this->plugin_url . 'font-tester-admin.js',
            array('jquery'),
            '1.1.4',
            array('in_footer' => true)
        );
        
        wp_register_style(
            'font-tester-admin-css',
            $this->plugin_url . 'font-tester-admin.css',
            array(),
            '1.1.4'
        );
        
        wp_enqueue_script('font-tester-admin-js');
        wp_enqueue_style('font-tester-admin-css');

        wp_localize_script('font-tester-admin-js', 'fontTesterAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('font_tester_nonce'),
            'plugin_url' => $this->plugin_url
        ));
    }

    public function fotyte_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'font-type-tester'));
        }

        $fonts = $this->fotyte_get_uploaded_fonts();
        ?>
        <div class="wrap">
            <h1>Font Type Tester</h1>
            <div class="font-tester-admin">
                <div class="card">
                    <h2>Upload New Font</h2>
                    <form id="font-upload-form" enctype="multipart/form-data">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="font-name">Font Name</label>
                                </th>
                                <td>
                                    <input type="text" id="font-name" name="font_name" placeholder="Enter font name (optional)" class="regular-text">
                                    <p class="description">If left blank, filename will be used as font name</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="font-file">Font File</label>
                                </th>
                                <td>
                                    <input type="file" id="font-file" name="font_file" accept=".ttf,.otf,.woff,.woff2" required>
                                    <p class="description">Supported formats: TTF, OTF, WOFF, WOFF2</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="Upload Font">
                        </p>
                    </form>
                </div>
                <div class="card">
                    <h2>Uploaded Fonts</h2>
                    <div id="fonts-list">
                        <?php if (empty($fonts)): ?>
                            <p>No fonts uploaded yet.</p>
                        <?php else: ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Font Name</th>
                                        <th>Original Filename</th>
                                        <th>Upload Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fonts as $font): ?>
                                        <tr data-font-id="<?php echo esc_attr($font->id); ?>">
                                            <td><strong><?php echo esc_html($font->font_name); ?></strong></td>
                                            <td><?php echo esc_html($font->original_filename); ?></td>
                                            <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($font->upload_date))); ?></td>
                                            <td>
                                                <button class="button button-small delete-font" data-font-id="<?php echo esc_attr($font->id); ?>">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card">
                    <h2>Usage Instructions</h2>
                    <p>To display the font tester on your website, use the following shortcode:</p>
                    <code>[font_tester]</code>
                    <p>Users will be able to select from the fonts you've uploaded here and test them with various typography controls.</p>
                </div>
            </div>
        </div>
        <?php
    }

    public function fotyte_handle_font_upload() {
        check_ajax_referer('font_tester_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Validate file upload
        if (!isset($_FILES['font_file']) || empty($_FILES['font_file']['tmp_name'])) {
            wp_send_json_error('No file uploaded');
        }

        // Check all required $_FILES indices exist
        if (!isset($_FILES['font_file']['name'], $_FILES['font_file']['error'], $_FILES['font_file']['size'], $_FILES['font_file']['type'])) {
            wp_send_json_error('Invalid file upload data');
        }

        // Sanitize file data
        $file = array(
            'name' => sanitize_file_name($_FILES['font_file']['name']),
            'tmp_name' => sanitize_text_field($_FILES['font_file']['tmp_name']),
            'error' => intval($_FILES['font_file']['error']),
            'size' => intval($_FILES['font_file']['size']),
            'type' => sanitize_mime_type($_FILES['font_file']['type'])
        );

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload error');
        }

        // Validate file type
        $allowed_types = array('ttf', 'otf', 'woff', 'woff2');
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_types)) {
            wp_send_json_error('Invalid file type. Please upload TTF, OTF, WOFF, or WOFF2 files.');
        }

        $upload_dir = wp_upload_dir();
        $font_dir = $upload_dir['basedir'] . '/font-tester/';

        // Generate obfuscated filename
        $obfuscated_name = 'font_' . wp_generate_password(12, false) . '.' . $file_ext;
        $target_path = $font_dir . $obfuscated_name;

        // Use WordPress file handling functions
        $upload_file_array = array(
            'name' => $obfuscated_name,
            'type' => $file['type'],
            'tmp_name' => $file['tmp_name'],
            'error' => $file['error'],
            'size' => $file['size']
        );
        $uploaded_file = wp_handle_upload(
            $upload_file_array,
            array(
                'test_form' => false,
                'upload_error_handler' => array($this, 'fotyte_handle_upload_error')
            )
        );

        if (!empty($uploaded_file['error'])) {
            wp_send_json_error(esc_html($uploaded_file['error']));
        }

        // Move file to our custom directory
        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }

        if (!$wp_filesystem->move($uploaded_file['file'], $target_path)) {
            wp_send_json_error('Failed to move uploaded file');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'font_tester_fonts';

        // Sanitize and validate font name
        $font_name = '';
        if (isset($_POST['font_name']) && !empty($_POST['font_name'])) {
            $font_name = sanitize_text_field(wp_unslash($_POST['font_name']));
        } else {
            $font_name = pathinfo($file['name'], PATHINFO_FILENAME);
        }

        // Use prepared statement for database insertion
        $result = $wpdb->insert(
            $table_name,
            array(
                'font_name' => $font_name,
                'original_filename' => $file['name'],
                'obfuscated_filename' => $obfuscated_name,
                'file_path' => $target_path
            ),
            array('%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            wp_send_json_error('Failed to save font information to database');
        }

        $font_url = $upload_dir['baseurl'] . '/font-tester/' . $obfuscated_name;

        // Cache the font data
        $font_data = array(
            'id' => $wpdb->insert_id,
            'name' => $font_name,
            'url' => $font_url,
            'filename' => $obfuscated_name,
            'original_filename' => $file['name'],
            'upload_date' => current_time('mysql')
        );

        wp_cache_set('font_tester_' . $wpdb->insert_id, $font_data, 'font_tester', 3600);
        wp_cache_delete('font_tester_all_fonts', 'font_tester');

        wp_send_json_success($font_data);
    }

    public function fotyte_handle_font_delete() {
        check_ajax_referer('font_tester_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Validate and sanitize font ID
        if (!isset($_POST['font_id']) || empty($_POST['font_id'])) {
            wp_send_json_error('Font ID is required');
        }

        $font_id = intval($_POST['font_id']);

        if ($font_id <= 0) {
            wp_send_json_error('Invalid font ID');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'font_tester_fonts';

        // Check cache first
        $cache_key = 'font_tester_font_' . $font_id;
        $font = wp_cache_get($cache_key, 'font_tester');

        if ($font === false) {
            // Use prepared statement for database query
            $font = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$wpdb->prefix}font_tester_fonts` WHERE id = %d",
                    $font_id
                )
            );

            if ($font) {
                wp_cache_set($cache_key, $font, 'font_tester', 3600);
            }
        }

        if (!$font) {
            wp_send_json_error('Font not found');
        }

        // Delete file using WordPress function
        if (file_exists($font->file_path)) {
            wp_delete_file($font->file_path);
        }

        // Delete database record with prepared statement
        $deleted = $wpdb->delete(
            $table_name,
            array('id' => $font_id),
            array('%d')
        );

        if ($deleted === false) {
            wp_send_json_error('Failed to delete font from database');
        }

        // Clear cache
        wp_cache_delete('font_tester_' . $font_id, 'font_tester');
        wp_cache_delete('font_tester_all_fonts', 'font_tester');

        wp_send_json_success('Font deleted successfully');
    }

    private function fotyte_get_uploaded_fonts() {
        // Check cache first
        $cache_key = 'font_tester_all_fonts';
        $fonts = wp_cache_get($cache_key, 'font_tester');

        if ($fonts === false) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'font_tester_fonts';

            // Direct query is fine here since there are no variables
            $fonts = $wpdb->get_results(
                "SELECT * FROM `{$wpdb->prefix}font_tester_fonts` ORDER BY upload_date DESC"
            );

            if ($fonts) {
                $upload_dir = wp_upload_dir();
                $font_base_url = $upload_dir['baseurl'] . '/font-tester/';

                foreach ($fonts as $font) {
                    $font->url = $font_base_url . $font->obfuscated_filename;
                }

                // Cache the results
                wp_cache_set($cache_key, $fonts, 'font_tester', 3600);
            } else {
                $fonts = array();
                wp_cache_set($cache_key, $fonts, 'font_tester', 3600);
            }
        }

        return $fonts;
    }

    public function fotyte_handle_upload_error($file, $message) {
        return array('error' => $message);
    }

    public function fotyte_render_font_tester($atts) {
        $fonts = $this->fotyte_get_uploaded_fonts();

        // Enqueue styles and scripts when shortcode is used
        wp_enqueue_script('font-tester-js');
        wp_enqueue_style('font-tester-css');

        ob_start();
        ?>
        <div id="font-tester-container">
            <!-- Font Preview Section - Now at the top -->
            <div class="font-preview-area">
                <h3>Font Preview</h3>
                <div id="font-preview">
                    <p id="preview-text">Select a font to see the preview</p>
                </div>
            </div>
            <!-- Controls Section - Now below preview -->
            <div class="font-tester-controls">
                <div class="control-section">
                    <h3>Font Selection</h3>
                    <?php if (empty($fonts)): ?>
                        <p>No fonts available. Please contact the administrator to upload fonts.</p>
                    <?php else: ?>
                        <select id="font-selector">
                            <option value="">Select a font...</option>
                            <?php foreach ($fonts as $font): ?>
                                <option value="<?php echo esc_attr($font->url); ?>" data-id="<?php echo esc_attr($font->id); ?>">
                                    <?php echo esc_html($font->font_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="control-section">
                    <h3>Typography Controls</h3>
                    <div class="slider-group">
                        <label for="font-size-slider">Font Size: <span id="font-size-value">16</span>px</label>
                        <input type="range" id="font-size-slider" min="8" max="120" value="16" step="1">
                    </div>
                    <div class="slider-group">
                        <label for="line-height-slider">Line Height: <span id="line-height-value">1.4</span></label>
                        <input type="range" id="line-height-slider" min="0.8" max="3.0" value="1.4" step="0.1">
                    </div>
                    <div class="slider-group">
                        <label for="letter-spacing-slider">Letter Spacing: <span id="letter-spacing-value">0</span>px</label>
                        <input type="range" id="letter-spacing-slider" min="-5" max="20" value="0" step="0.5">
                    </div>
                    <div class="slider-group">
                        <label for="word-spacing-slider">Word Spacing: <span id="word-spacing-value">0</span>px</label>
                        <input type="range" id="word-spacing-slider" min="-10" max="50" value="0" step="1">
                    </div>
                </div>
                <div class="control-section">
                    <h3>Sample Text</h3>
                    <textarea id="sample-text" rows="4" cols="50">The quick brown fox jumps over the lazy dog. ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz 1234567890 !@#$%^&*()_+-=[]{}|;':\",./<>?</textarea>
                </div>
                <div class="control-section">
                    <h3>Font Information</h3>
                    <div id="font-info">
                        <p><strong>Source Protection:</strong> Font files are automatically renamed with random strings to protect the original source.</p>
                        <p id="current-font-info"></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function fotyte_create_external_files() {
        $plugin_dir = plugin_dir_path(__FILE__);

        // Create front-end CSS file
        file_put_contents($plugin_dir . 'font-tester.css', $this->fotyte_get_frontend_css());

        // Create front-end JS file
        file_put_contents($plugin_dir . 'font-tester.js', $this->fotyte_get_frontend_js());

        // Create admin CSS file
        file_put_contents($plugin_dir . 'font-tester-admin.css', $this->fotyte_get_admin_css());

        // Create admin JS file
        file_put_contents($plugin_dir . 'font-tester-admin.js', $this->fotyte_get_admin_js());
    }

    private function fotyte_get_frontend_css() {
        return '
#font-tester-container {
    max-width: 1200px;
    margin: 20px auto;
    font-family: Arial, sans-serif;
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.font-tester-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.control-section {
    background: white;
    padding: 20px;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.control-section h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #007cba;
    padding-bottom: 10px;
}
#font-selector,
#sample-text {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}
.slider-group {
    margin-bottom: 20px;
}
.slider-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #555;
}
input[type="range"] {
    width: 100%;
    height: 6px;
    background: #ddd;
    outline: none;
    border-radius: 3px;
    -webkit-appearance: none;
}
input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    background: #007cba;
    cursor: pointer;
    border-radius: 50%;
}
input[type="range"]::-moz-range-thumb {
    width: 20px;
    height: 20px;
    background: #007cba;
    cursor: pointer;
    border-radius: 50%;
    border: none;
}
.font-preview-area {
    background: white;
    padding: 30px;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}
.font-preview-area h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #007cba;
    padding-bottom: 10px;
}
#font-preview {
    min-height: 200px;
    padding: 20px;
    border: 2px dashed #ddd;
    border-radius: 4px;
    background: #fafafa;
}
#preview-text {
    margin: 0;
    word-wrap: break-word;
    line-height: 1.4;
    transition: all 0.3s ease;
}
#font-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    border-left: 4px solid #007cba;
}
#font-info p {
    margin: 5px 0;
    font-size: 14px;
    color: #666;
}
@media (max-width: 768px) {
    .font-tester-controls {
        grid-template-columns: 1fr;
    }
    #font-tester-container {
        margin: 10px;
        padding: 15px;
    }
}
        ';
    }

    private function fotyte_get_admin_css() {
        return '
.font-tester-admin .card {
    margin-bottom: 20px;
}
.font-tester-admin .card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #ccd0d4;
}
.font-tester-admin .form-table th {
    width: 150px;
}
.font-tester-admin .delete-font {
    color: #a00;
    border-color: #a00;
}
.font-tester-admin .delete-font:hover {
    background: #a00;
    color: white;
}
.font-tester-admin .loading {
    opacity: 0.6;
    pointer-events: none;
}
#fonts-list .wp-list-table {
    margin-top: 10px;
}
#fonts-list .wp-list-table th,
#fonts-list .wp-list-table td {
    padding: 8px 10px;
}
.font-tester-admin code {
    background: #f1f1f1;
    padding: 2px 4px;
    border-radius: 3px;
}
        ';
    }

    private function fotyte_get_frontend_js() {
        return '
jQuery(document).ready(function($) {
    let currentFontName = "";

    // Font selector change handler
    $("#font-selector").on("change", function() {
        const fontUrl = $(this).val();
        const fontId = $(this).find("option:selected").data("id");
        const fontName = $(this).find("option:selected").text();
        
        if (fontUrl) {
            loadFont(fontUrl, fontName, fontId);
            updateFontInfo(fontName, fontId);
        } else {
            resetPreview();
        }
    });

    // Slider event handlers
    $("#font-size-slider").on("input", function() {
        const value = $(this).val();
        $("#font-size-value").text(value);
        updatePreviewStyles();
    });

    $("#line-height-slider").on("input", function() {
        const value = $(this).val();
        $("#line-height-value").text(value);
        updatePreviewStyles();
    });

    $("#letter-spacing-slider").on("input", function() {
        const value = $(this).val();
        $("#letter-spacing-value").text(value);
        updatePreviewStyles();
    });

    $("#word-spacing-slider").on("input", function() {
        const value = $(this).val();
        $("#word-spacing-value").text(value);
        updatePreviewStyles();
    });

    // Sample text change handler
    $("#sample-text").on("input", function() {
        $("#preview-text").text($(this).val());
    });

    function loadFont(fontUrl, fontName, fontId) {
        // Remove existing dynamic font styles
        $("#dynamic-font-face").remove();
        
        // Create unique font family name
        currentFontName = "FontTester_" + fontId + "_" + Date.now();
        
        // Create font-face CSS
        const fontFaceCSS = `
            @font-face {
                font-family: "${currentFontName}";
                src: url("${fontUrl}");
                font-display: swap;
            }
        `;
        
        // Inject the font-face CSS
        $("<style>")
            .attr("id", "dynamic-font-face")
            .html(fontFaceCSS)
            .appendTo("head");
        
        // Apply the font to preview
        updatePreviewStyles();
    }

    function updatePreviewStyles() {
        if (!currentFontName) return;
        
        const fontSize = $("#font-size-slider").val();
        const lineHeight = $("#line-height-slider").val();
        const letterSpacing = $("#letter-spacing-slider").val();
        const wordSpacing = $("#word-spacing-slider").val();
        
        $("#preview-text").css({
            "font-family": `"${currentFontName}", sans-serif`,
            "font-size": fontSize + "px",
            "line-height": lineHeight,
            "letter-spacing": letterSpacing + "px",
            "word-spacing": wordSpacing + "px"
        });
    }

    function updateFontInfo(fontName, fontId) {
        $("#current-font-info").html(`
            <strong>Current Font:</strong> ${fontName}<br>
            <strong>Font ID:</strong> ${fontId}
        `);
    }

    function resetPreview() {
        $("#dynamic-font-face").remove();
        $("#preview-text").css({
            "font-family": "",
            "font-size": "",
            "line-height": "",
            "letter-spacing": "",
            "word-spacing": ""
        }).text("Select a font to see the preview");
        $("#current-font-info").html("");
        currentFontName = "";
    }
});
        ';
    }

    private function fotyte_get_admin_js() {
        return '
jQuery(document).ready(function($) {
    // Font upload form handler
    $("#font-upload-form").on("submit", function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const fontFile = $("#font-file")[0].files[0];
        const fontName = $("#font-name").val();
        
        if (!fontFile) {
            alert("Please select a font file.");
            return;
        }
        
        formData.append("action", "upload_font");
        formData.append("nonce", fontTesterAdmin.nonce);
        formData.append("font_file", fontFile);
        formData.append("font_name", fontName);
        
        const $submitBtn = $("#submit");
        $submitBtn.prop("disabled", true).val("Uploading...");
        
        $.ajax({
            url: fontTesterAdmin.ajax_url,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert("Font uploaded successfully!");
                    location.reload();
                } else {
                    alert("Error: " + response.data);
                }
            },
            error: function() {
                alert("An error occurred during upload.");
            },
            complete: function() {
                $submitBtn.prop("disabled", false).val("Upload Font");
            }
        });
    });

    // Font delete handler
    $(document).on("click", ".delete-font", function() {
        if (!confirm("Are you sure you want to delete this font?")) {
            return;
        }
        
        const fontId = $(this).data("font-id");
        const $row = $(this).closest("tr");
        
        $.ajax({
            url: fontTesterAdmin.ajax_url,
            type: "POST",
            data: {
                action: "delete_font",
                nonce: fontTesterAdmin.nonce,
                font_id: fontId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $(this).remove();
                        if ($("#fonts-list tbody tr").length === 0) {
                            $("#fonts-list").html("<p>No fonts uploaded yet.</p>");
                        }
                    });
                } else {
                    alert("Error: " + response.data);
                }
            },
            error: function() {
                alert("An error occurred while deleting the font.");
            }
        });
    });
});
        ';
    }
}

// Initialize the plugin
new fotyte_FontTypeTester();
?>
