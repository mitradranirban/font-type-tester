<?php
/**
 * Plugin Name: Font Type Tester
 * Plugin-url: https://github.com/mitradranirban/font-type-tester
 * Description: A comprehensive font testing tool with real-time typography controls
 * submitter: mitradranirban
 * Author: mitradranirban
 * Version: 1.1.11
 * License: GPL v2 or later
 * Text Domain: font-type-tester
 */

if (!defined('ABSPATH')) {
    exit;
}

class FotyteWordPressFontTester {
    
    private $version = '1.1.11';
    
    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'fotyte_on_activation']);
        register_deactivation_hook(__FILE__, [$this, 'fotyte_on_deactivation']);
        // Enable font uploads
        add_filter('upload_mimes', [$this, 'fotyte_allow_font_uploads']);
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
    public function fotyte_allow_font_uploads($mime_types) {
        $mime_types['ttf'] = 'font/ttf';
        $mime_types['otf'] = 'font/otf';
        $mime_types['woff'] = 'font/woff';
        $mime_types['woff2'] = 'font/woff2';
        return $mime_types;
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
        
        // Validate file upload with proper isset() checks
if (!isset($_FILES['font_file']) || !isset($_FILES['font_file']['error']) || $_FILES['font_file']['error'] !== UPLOAD_ERR_OK) {
    wp_die(esc_html__('No file uploaded or upload error occurred', 'font-type-tester'));
}

// Sanitize file data
$font_file = array_map('sanitize_text_field', $_FILES['font_file']);
// Exception for tmp_name which needs to remain unsanitized for file operations
$font_file['tmp_name'] = $_FILES['font_file']['tmp_name'];

// Enhanced file validation with sanitized data
$file_size = isset($font_file['size']) ? absint($font_file['size']) : 0;
$file_name = isset($font_file['name']) ? sanitize_file_name($font_file['name']) : '';

if (empty($file_name)) {
    wp_die(esc_html__('Invalid file name.', 'font-type-tester'));
}

// Validate file size (limit to 10MB for fonts)
if ($file_size > 10 * 1024 * 1024) {
    wp_die(esc_html__('File too large. Maximum size is 10MB.', 'font-type-tester'));
}

// Validate file extension (case insensitive)
$allowed_extensions = ['ttf', 'otf', 'woff', 'woff2'];
$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
if (!in_array($file_extension, $allowed_extensions, true)) {
    wp_die(esc_html__('Invalid font file type. Only TTF, OTF, WOFF, and WOFF2 files are allowed.', 'font-type-tester'));
}

        
        // Additional security: Check if file is actually a font by reading first few bytes
        $file_content = file_get_contents($font_file['tmp_name'], false, null, 0, 4);
        $font_signatures = [
            'ttf' => ["\x00\x01\x00\x00", "true"], // TTF signature
            'otf' => ["OTTO"], // OTF signature  
            'woff' => ["wOFF"], // WOFF signature
            'woff2' => ["wOF2"] // WOFF2 signature
        ];
        
        $is_valid_font = false;
        foreach ($font_signatures[$file_extension] as $signature) {
            if (strpos($file_content, $signature) === 0) {
                $is_valid_font = true;
                break;
            }
        }
        
        if (!$is_valid_font && $file_extension !== 'ttf') { // TTF has multiple possible signatures
            wp_die(esc_html__('Invalid font file format.', 'font-type-tester'));
        }
        
        // Create custom upload directory
        $upload_dir = wp_upload_dir();
        $font_dir = $upload_dir['basedir'] . '/font-tester/';
        
        if (!wp_mkdir_p($font_dir)) {
            wp_die(esc_html__('Could not create upload directory.', 'font-type-tester'));
        }
        
        // Generate safe filename
        $original_filename = sanitize_file_name($font_file['name']);
        $obfuscated_filename = wp_unique_filename($font_dir, $original_filename);
        $target_path = $font_dir . $obfuscated_filename;
        $file_url = $upload_dir['baseurl'] . '/font-tester/' . $obfuscated_filename;
        
       // Initialize WP_Filesystem
      global $wp_filesystem;
      require_once ABSPATH . 'wp-admin/includes/file.php';
      WP_Filesystem();

      // Move uploaded file to our custom directory using WP_Filesystem
      $file_contents = $wp_filesystem->get_contents($font_file['tmp_name']);
      if (!$wp_filesystem->put_contents($target_path, $file_contents, 0644)) {
      wp_die(esc_html__('Failed to move uploaded file.', 'font-type-tester'));
}
        
        // Set proper file permissions
        chmod($target_path, 0644);
        
        // Get font name from form or use filename
        $font_name = isset($_POST['font_name']) && !empty($_POST['font_name']) ? 
            sanitize_text_field(wp_unslash($_POST['font_name'])) : 
            pathinfo($original_filename, PATHINFO_FILENAME);
        
        // Save to database
        global $wpdb;
        $table = $wpdb->prefix . 'fotyte_font_tester_fonts';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            $table,
            [
                'font_name' => $font_name,
                'original_filename' => $original_filename,
                'obfuscated_filename' => $obfuscated_filename,
                'file_path' => $file_url
            ],
            ['%s', '%s', '%s', '%s']
        );
        
        if (false === $inserted) {
            // Clean up file if database insert fails
            if (file_exists($target_path)) {
                wp_delete_file($target_path);
            }
            wp_die(esc_html__('Database error while saving font information.', 'font-type-tester'));
        }
        
        // Clear cache
        wp_cache_delete('fotyte_all_fonts', 'fotyte_fonts');
        
        // Redirect with success message
        $redirect_url = add_query_arg([
            'page' => 'fotyte-font-type-tester',
            'uploaded' => '1',
            'font_name' => urlencode($font_name),
            '_wpnonce' => wp_create_nonce('fotyte_font_upload_success')
        ], admin_url('options-general.php'));
        wp_redirect($redirect_url);
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

     // Add nonce verification for GET parameters
     if (isset($_GET['uploaded']) || isset($_GET['font_name'])) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'fotyte_font_upload_success')) {
            wp_die(esc_html__('Invalid security token', 'font-type-tester'));
        }
     }

     if (isset($_GET['uploaded']) && $_GET['uploaded'] === '1') {
        $font_name = isset($_GET['font_name']) ? sanitize_text_field(wp_unslash($_GET['font_name'])) : '';
        echo '<div class="notice notice-success is-dismissible">';
        if ($font_name) {
            printf(esc_html__('Font "%s" uploaded successfully!', 'font-type-tester'), esc_html($font_name));
        } else {
            esc_html_e('Font uploaded successfully!', 'font-type-tester');
        }
        echo '</div>';
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
            return '<div class="fotyte-no-fonts"><p>' . esc_html__('No fonts available for testing. Please upload fonts from the admin panel.', 'font-type-tester') . '</p></div>';
        }
        
        ob_start();
        ?>
        <div id="fotyte-font-tester" class="fotyte-font-tester-container">
            <!-- Font Selection -->
            <div class="fotyte-control-group">
                <label for="fotyte-font-select"><?php esc_html_e('Select Font:', 'font-type-tester'); ?></label>
                <select id="fotyte-font-select" class="fotyte-font-select">
                    <option value=""><?php esc_html_e('Choose a font...', 'font-type-tester'); ?></option>
                    <?php foreach ($fonts as $font): ?>
                        <option value="<?php echo esc_attr($font['id']); ?>" 
                                data-font-url="<?php echo esc_url($font['file_path']); ?>"
                                data-font-name="<?php echo esc_attr($font['font_name']); ?>">
                            <?php echo esc_html($font['font_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Typography Controls -->
            <div class="fotyte-controls-row">
                <div class="fotyte-control-group">
                    <label for="fotyte-font-size"><?php esc_html_e('Font Size:', 'font-type-tester'); ?> <span id="fotyte-size-display">18px</span></label>
                    <input type="range" id="fotyte-font-size" class="fotyte-slider" min="8" max="120" value="18" step="1">
                </div>
                
                <div class="fotyte-control-group">
                    <label for="fotyte-line-height"><?php esc_html_e('Line Height:', 'font-type-tester'); ?> <span id="fotyte-line-display">1.4</span></label>
                    <input type="range" id="fotyte-line-height" class="fotyte-slider" min="0.8" max="3" value="1.4" step="0.1">
                </div>
                
                <div class="fotyte-control-group">
                    <label for="fotyte-letter-spacing"><?php esc_html_e('Letter Spacing:', 'font-type-tester'); ?> <span id="fotyte-spacing-display">0px</span></label>
                    <input type="range" id="fotyte-letter-spacing" class="fotyte-slider" min="-5" max="10" value="0" step="0.1">
                </div>
            </div>
            
            <!-- Font Weight & Style Controls -->
            <div class="fotyte-controls-row">
                <div class="fotyte-control-group">
                    <label for="fotyte-font-weight"><?php esc_html_e('Font Weight:', 'font-type-tester'); ?></label>
                    <select id="fotyte-font-weight" class="fotyte-select">
                        <option value="100">100 - Thin</option>
                        <option value="200">200 - Extra Light</option>
                        <option value="300">300 - Light</option>
                        <option value="400" selected>400 - Normal</option>
                        <option value="500">500 - Medium</option>
                        <option value="600">600 - Semi Bold</option>
                        <option value="700">700 - Bold</option>
                        <option value="800">800 - Extra Bold</option>
                        <option value="900">900 - Black</option>
                    </select>
                </div>
                
                <div class="fotyte-control-group">
                    <label for="fotyte-font-style"><?php esc_html_e('Font Style:', 'font-type-tester'); ?></label>
                    <select id="fotyte-font-style" class="fotyte-select">
                        <option value="normal" selected><?php esc_html_e('Normal', 'font-type-tester'); ?></option>
                        <option value="italic"><?php esc_html_e('Italic', 'font-type-tester'); ?></option>
                        <option value="oblique"><?php esc_html_e('Oblique', 'font-type-tester'); ?></option>
                    </select>
                </div>
                
                <div class="fotyte-control-group">
                    <label for="fotyte-text-transform"><?php esc_html_e('Text Transform:', 'font-type-tester'); ?></label>
                    <select id="fotyte-text-transform" class="fotyte-select">
                        <option value="none" selected><?php esc_html_e('None', 'font-type-tester'); ?></option>
                        <option value="uppercase"><?php esc_html_e('Uppercase', 'font-type-tester'); ?></option>
                        <option value="lowercase"><?php esc_html_e('Lowercase', 'font-type-tester'); ?></option>
                        <option value="capitalize"><?php esc_html_e('Capitalize', 'font-type-tester'); ?></option>
                    </select>
                </div>
            </div>
            
            <!-- Color Controls -->
            <div class="fotyte-controls-row">
                <div class="fotyte-control-group">
                    <label for="fotyte-text-color"><?php esc_html_e('Text Color:', 'font-type-tester'); ?></label>
                    <input type="color" id="fotyte-text-color" class="fotyte-color-picker" value="#333333">
                </div>
                
                <div class="fotyte-control-group">
                    <label for="fotyte-bg-color"><?php esc_html_e('Background Color:', 'font-type-tester'); ?></label>
                    <input type="color" id="fotyte-bg-color" class="fotyte-color-picker" value="#ffffff">
                </div>
            </div>
            
            <!-- Sample Text Input -->
            <div class="fotyte-control-group fotyte-full-width">
                <label for="fotyte-sample-text"><?php esc_html_e('Sample Text:', 'font-type-tester'); ?></label>
                <textarea id="fotyte-sample-text" class="fotyte-textarea" rows="3" placeholder="<?php esc_attr_e('Type your text here...', 'font-type-tester'); ?>">The quick brown fox jumps over the lazy dog. ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz 1234567890 !@#$%^&amp;*()_+-=[]{}|;:&quot;,./&lt;&gt;?</textarea>
            </div>
            
            <!-- Preview Area -->
            <div class="fotyte-preview-area">
                <h3><?php esc_html_e('Font Preview:', 'font-type-tester'); ?></h3>
                <div id="fotyte-preview" class="fotyte-preview-text">
                    <?php esc_html_e('Please select a font to see the preview.', 'font-type-tester'); ?>
                </div>
            </div>
            
            <!-- Preset Text Samples -->
            <div class="fotyte-presets">
                <h4><?php esc_html_e('Quick Text Samples:', 'font-type-tester'); ?></h4>
                <div class="fotyte-preset-buttons">
                    <button type="button" class="fotyte-preset-btn" data-text="The quick brown fox jumps over the lazy dog."><?php esc_html_e('Pangram', 'font-type-tester'); ?></button>
                    <button type="button" class="fotyte-preset-btn" data-text="ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz"><?php esc_html_e('Alphabet', 'font-type-tester'); ?></button>
                    <button type="button" class="fotyte-preset-btn" data-text="1234567890 !@#$%^&*()_+-=[]{}|;:&quot;,./&lt;&gt;?"><?php esc_html_e('Numbers & Symbols', 'font-type-tester'); ?></button>
                    <button type="button" class="fotyte-preset-btn" data-text="Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua."><?php esc_html_e('Lorem Ipsum', 'font-type-tester'); ?></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function fotyte_get_frontend_css() {
        return '
        .fotyte-font-tester-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
            font-family: Arial, sans-serif;
        }
        
        .fotyte-control-group {
            margin-bottom: 15px;
        }
        
        .fotyte-control-group.fotyte-full-width {
            width: 100%;
        }
        
        .fotyte-control-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .fotyte-controls-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .fotyte-controls-row .fotyte-control-group {
            flex: 1;
            min-width: 200px;
        }
        
        .fotyte-font-select,
        .fotyte-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            background: white;
        }
        
        .fotyte-slider {
            width: 100%;
            margin: 5px 0;
        }
        
        .fotyte-color-picker {
            width: 50px;
            height: 35px;
            border: 1px solid #ccc;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .fotyte-textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
        }
        
        .fotyte-preview-area {
            margin: 30px 0;
            padding: 20px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .fotyte-preview-area h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 18px;
        }
        
        .fotyte-preview-text {
            min-height: 100px;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 4px;
            background: #fafafa;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .fotyte-presets {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .fotyte-presets h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .fotyte-preset-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .fotyte-preset-btn {
            padding: 8px 12px;
            border: 1px solid #0073aa;
            background: #0073aa;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .fotyte-preset-btn:hover {
            background: #005a87;
            border-color: #005a87;
        }
        
        .fotyte-no-fonts {
            text-align: center;
            padding: 40px 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .fotyte-controls-row {
                flex-direction: column;
            }
            
            .fotyte-controls-row .fotyte-control-group {
                min-width: auto;
            }
            
            .fotyte-preset-buttons {
                flex-direction: column;
            }
            
            .fotyte-preset-btn {
                width: 100%;
            }
        }
        ';
    }
    
    private function fotyte_get_frontend_js() {
        return '
        jQuery(document).ready(function($) {
            var currentFontFamily = "";
            var loadedFonts = {};
            
            // Load font dynamically
            function loadFont(fontUrl, fontName, fontId) {
                if (loadedFonts[fontId]) {
                    return;
                }
                
                var fontFace = new FontFace(fontName, "url(" + fontUrl + ")");
                fontFace.load().then(function(loadedFont) {
                    document.fonts.add(loadedFont);
                    loadedFonts[fontId] = true;
                    updatePreview();
                }).catch(function(error) {
                    console.error("Font loading failed:", error);
                    alert("Failed to load font. Please check the font file.");
                });
            }
            
            // Update preview text with current settings
            function updatePreview() {
                var preview = $("#fotyte-preview");
                var sampleText = $("#fotyte-sample-text").val() || "Please select a font and enter some text.";
                
                preview.html(sampleText.replace(/\\n/g, "<br>"));
                
                if (currentFontFamily) {
                    var styles = {
                        "font-family": currentFontFamily + ", Arial, sans-serif",
                        "font-size": $("#fotyte-font-size").val() + "px",
                        "line-height": $("#fotyte-line-height").val(),
                        "letter-spacing": $("#fotyte-letter-spacing").val() + "px",
                        "font-weight": $("#fotyte-font-weight").val(),
                        "font-style": $("#fotyte-font-style").val(),
                        "text-transform": $("#fotyte-text-transform").val(),
                        "color": $("#fotyte-text-color").val(),
                        "background-color": $("#fotyte-bg-color").val()
                    };
                    
                    preview.css(styles);
                }
            }
            
            // Update display values for sliders
            function updateSliderDisplays() {
                $("#fotyte-size-display").text($("#fotyte-font-size").val() + "px");
                $("#fotyte-line-display").text($("#fotyte-line-height").val());
                $("#fotyte-spacing-display").text($("#fotyte-letter-spacing").val() + "px");
            }
            
            // Font selection change
            $("#fotyte-font-select").change(function() {
                var selectedOption = $(this).find("option:selected");
                var fontUrl = selectedOption.data("font-url");
                var fontName = selectedOption.data("font-name");
                var fontId = selectedOption.val();
                
                if (fontUrl && fontName && fontId) {
                    currentFontFamily = fontName;
                    loadFont(fontUrl, fontName, fontId);
                } else {
                    currentFontFamily = "";
                    updatePreview();
                }
            });
            
            // Slider changes
            $(".fotyte-slider").on("input", function() {
                updateSliderDisplays();
                updatePreview();
            });
            
            // Select changes
            $(".fotyte-select, .fotyte-color-picker").change(function() {
                updatePreview();
            });
            
            // Sample text changes
            $("#fotyte-sample-text").on("input", function() {
                updatePreview();
            });
            
            // Preset buttons
            $(".fotyte-preset-btn").click(function() {
                var presetText = $(this).data("text");
                $("#fotyte-sample-text").val(presetText);
                updatePreview();
            });
            
            // Initialize displays
            updateSliderDisplays();
            updatePreview();
        });
        ';
    }
    
    private function fotyte_get_admin_css() {
        return '
        .fotyte-admin-container {
            max-width: 800px;
        }
        
        .fotyte-admin-container .form-table th {
            width: 150px;
        }
        
        .fotyte-admin-container .notice {
            margin: 10px 0;
        }
        
        .wp-list-table.fotyte-fonts-table {
            margin-top: 20px;
        }
        
        .fotyte-usage-info {
            background: #f1f1f1;
            padding: 15px;
            border-left: 4px solid #0073aa;
            margin: 20px 0;
        }
        
        .fotyte-usage-info code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        ';
    }
    
    private function fotyte_get_admin_js() {
        return '
        jQuery(document).ready(function($) {
            // Add any admin-specific JavaScript here
            console.log("Font Tester Admin loaded");
        });
        ';
    }
    
}

new FotyteWordPressFontTester();
?>