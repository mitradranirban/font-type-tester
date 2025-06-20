<?php
/**
 * Plugin Name: Font Type Tester
 * Plugin URI: https://github.com/mitradranirban/font-type-tester
 * Description: A comprehensive font testing tool with sliders for typography controls and font source obfuscation
 * Version: 1.0.0
 * Author: Anirban Mitra
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FontTypeTester {
    
    private $plugin_url;
    private $plugin_path;
    
    public function __construct() {
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_path = plugin_dir_path(__FILE__);
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_upload_font', array($this, 'handle_font_upload'));
        add_action('wp_ajax_nopriv_upload_font', array($this, 'handle_font_upload'));
        add_action('wp_ajax_delete_font', array($this, 'handle_font_delete'));
        add_action('wp_ajax_nopriv_delete_font', array($this, 'handle_font_delete'));
        add_shortcode('font_tester', array($this, 'render_font_tester'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Create uploads directory for fonts
        $upload_dir = wp_upload_dir();
        $font_dir = $upload_dir['basedir'] . '/font-tester/';
        if (!file_exists($font_dir)) {
            wp_mkdir_p($font_dir);
        }
    }
    
    public function activate() {
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
    }
    
    public function deactivate() {
        // Clean up fonts directory
        $upload_dir = wp_upload_dir();
        $font_dir = $upload_dir['basedir'] . '/font-tester/';
        if (file_exists($font_dir)) {
            $this->delete_directory($font_dir);
        }
    }
    
    private function delete_directory($dir) {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('font-tester-js', $this->plugin_url . 'font-tester.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('font-tester-css', $this->plugin_url . 'font-tester.css', array(), '1.0.0');
        
        wp_localize_script('font-tester-js', 'fontTester', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('font_tester_nonce'),
            'plugin_url' => $this->plugin_url
        ));
    }
    
    public function handle_font_upload() {
        check_ajax_referer('font_tester_nonce', 'nonce');
        
        if (!isset($_FILES['font_file'])) {
            wp_die('No file uploaded');
        }
        
        $file = $_FILES['font_file'];
        $allowed_types = array('ttf', 'otf', 'woff', 'woff2');
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            wp_die('Invalid file type. Please upload TTF, OTF, WOFF, or WOFF2 files.');
        }
        
        $upload_dir = wp_upload_dir();
        $font_dir = $upload_dir['basedir'] . '/font-tester/';
        
        // Generate obfuscated filename
        $obfuscated_name = 'font_' . wp_generate_password(12, false) . '.' . $file_ext;
        $target_path = $font_dir . $obfuscated_name;
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'font_tester_fonts';
            
            $font_name = sanitize_text_field($_POST['font_name']) ?: pathinfo($file['name'], PATHINFO_FILENAME);
            
            $wpdb->insert(
                $table_name,
                array(
                    'font_name' => $font_name,
                    'original_filename' => $file['name'],
                    'obfuscated_filename' => $obfuscated_name,
                    'file_path' => $target_path
                )
            );
            
            $font_url = $upload_dir['baseurl'] . '/font-tester/' . $obfuscated_name;
            
            wp_send_json_success(array(
                'id' => $wpdb->insert_id,
                'name' => $font_name,
                'url' => $font_url,
                'filename' => $obfuscated_name
            ));
        } else {
            wp_die('Failed to upload font file');
        }
    }
    
    public function handle_font_delete() {
        check_ajax_referer('font_tester_nonce', 'nonce');
        
        $font_id = intval($_POST['font_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'font_tester_fonts';
        
        $font = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $font_id));
        
        if ($font) {
            // Delete file
            if (file_exists($font->file_path)) {
                unlink($font->file_path);
            }
            
            // Delete database record
            $wpdb->delete($table_name, array('id' => $font_id));
            
            wp_send_json_success('Font deleted successfully');
        } else {
            wp_send_json_error('Font not found');
        }
    }
    
    private function get_uploaded_fonts() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'font_tester_fonts';
        $fonts = $wpdb->get_results("SELECT * FROM $table_name ORDER BY upload_date DESC");
        
        $upload_dir = wp_upload_dir();
        $font_base_url = $upload_dir['baseurl'] . '/font-tester/';
        
        foreach ($fonts as $font) {
            $font->url = $font_base_url . $font->obfuscated_filename;
        }
        
        return $fonts;
    }
    
    public function render_font_tester($atts) {
        $fonts = $this->get_uploaded_fonts();
        
        ob_start();
        ?>
        <div id="font-tester-container">
            <div class="font-tester-controls">
                <div class="control-section">
                    <h3>Font Upload</h3>
                    <form id="font-upload-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="font-name">Font Name (optional):</label>
                            <input type="text" id="font-name" name="font_name" placeholder="Enter font name">
                        </div>
                        <div class="form-group">
                            <label for="font-file">Select Font File:</label>
                            <input type="file" id="font-file" name="font_file" accept=".ttf,.otf,.woff,.woff2" required>
                        </div>
                        <button type="submit">Upload Font</button>
                    </form>
                </div>
                
                <div class="control-section">
                    <h3>Font Selection</h3>
                    <select id="font-selector">
                        <option value="">Select a font...</option>
                        <?php foreach ($fonts as $font): ?>
                            <option value="<?php echo esc_attr($font->url); ?>" data-id="<?php echo esc_attr($font->id); ?>">
                                <?php echo esc_html($font->font_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button id="delete-font-btn" style="display: none;" class="delete-btn">Delete Font</button>
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
            
            <div class="font-preview-area">
                <h3>Font Preview</h3>
                <div id="font-preview">
                    <p id="preview-text">Select a font to see the preview</p>
                </div>
            </div>
        </div>
        
        <style id="dynamic-font-styles"></style>
        <?php
        return ob_get_clean();
    }
}

// Initialize the plugin
new FontTypeTester();

// CSS Styles (save as font-tester.css in the same directory)
if (!function_exists('font_tester_css_content')) {
    function font_tester_css_content() {
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

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #555;
}

.form-group input[type="text"],
.form-group input[type="file"],
#font-selector,
#sample-text {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

button {
    background: #007cba;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s;
}

button:hover {
    background: #005a87;
}

.delete-btn {
    background: #dc3545;
    margin-left: 10px;
}

.delete-btn:hover {
    background: #c82333;
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

.loading {
    opacity: 0.6;
    pointer-events: none;
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
}

// JavaScript (save as font-tester.js in the same directory)
if (!function_exists('font_tester_js_content')) {
    function font_tester_js_content() {
        return "
jQuery(document).ready(function($) {
    let currentFontId = null;
    
    // Font upload handler
    $('#font-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const fileInput = $('#font-file')[0];
        const fontName = $('#font-name').val();
        
        if (!fileInput.files[0]) {
            alert('Please select a font file');
            return;
        }
        
        formData.append('action', 'upload_font');
        formData.append('nonce', fontTester.nonce);
        formData.append('font_file', fileInput.files[0]);
        formData.append('font_name', fontName);
        
        $('#font-tester-container').addClass('loading');
        
        $.ajax({
            url: fontTester.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Add new font to selector
                    $('#font-selector').append(
                        $('<option></option>').attr('value', response.data.url)
                                              .attr('data-id', response.data.id)
                                              .text(response.data.name)
                    );
                    
                    // Reset form
                    $('#font-upload-form')[0].reset();
                    
                    alert('Font uploaded successfully!');
                } else {
                    alert('Error uploading font: ' + response.data);
                }
            },
            error: function() {
                alert('Error uploading font. Please try again.');
            },
            complete: function() {
                $('#font-tester-container').removeClass('loading');
            }
        });
    });
    
    // Font selection handler
    $('#font-selector').on('change', function() {
        const fontUrl = $(this).val();
        const fontId = $(this).find(':selected').data('id');
        const fontName = $(this).find(':selected').text();
        
        if (fontUrl) {
            loadFont(fontUrl, fontName);
            currentFontId = fontId;
            $('#delete-font-btn').show();
        } else {
            $('#preview-text').text('Select a font to see the preview');
            $('#current-font-info').html('');
            $('#delete-font-btn').hide();
            currentFontId = null;
        }
    });
    
    // Font deletion handler
    $('#delete-font-btn').on('click', function() {
        if (!currentFontId) return;
        
        if (!confirm('Are you sure you want to delete this font?')) return;
        
        $.ajax({
            url: fontTester.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_font',
                nonce: fontTester.nonce,
                font_id: currentFontId
            },
            success: function(response) {
                if (response.success) {
                    $('option[data-id=\"' + currentFontId + '\"]').remove();
                    $('#font-selector').val('');
                    $('#preview-text').text('Select a font to see the preview');
                    $('#current-font-info').html('');
                    $('#delete-font-btn').hide();
                    currentFontId = null;
                    alert('Font deleted successfully!');
                } else {
                    alert('Error deleting font: ' + response.data);
                }
            },
            error: function() {
                alert('Error deleting font. Please try again.');
            }
        });
    });
    
    // Slider handlers
    $('#font-size-slider').on('input', function() {
        const value = $(this).val();
        $('#font-size-value').text(value);
        updatePreview();
    });
    
    $('#line-height-slider').on('input', function() {
        const value = $(this).val();
        $('#line-height-value').text(value);
        updatePreview();
    });
    
    $('#letter-spacing-slider').on('input', function() {
        const value = $(this).val();
        $('#letter-spacing-value').text(value);
        updatePreview();
    });
    
    $('#word-spacing-slider').on('input', function() {
        const value = $(this).val();
        $('#word-spacing-value').text(value);
        updatePreview();
    });
    
    // Sample text handler
    $('#sample-text').on('input', function() {
        const text = $(this).val();
        $('#preview-text').text(text);
    });
    
    function loadFont(fontUrl, fontName) {
        const fontFace = new FontFace('CustomTestFont', 'url(' + fontUrl + ')');
        
        fontFace.load().then(function(loadedFont) {
            document.fonts.add(loadedFont);
            $('#preview-text').css('font-family', 'CustomTestFont, Arial, sans-serif');
            $('#preview-text').text($('#sample-text').val());
            $('#current-font-info').html('<strong>Loaded Font:</strong> ' + fontName + '<br><strong>Status:</strong> Font source is obfuscated for protection');
            updatePreview();
        }).catch(function(error) {
            console.error('Font loading failed:', error);
            alert('Failed to load font. Please check the file format.');
        });
    }
    
    function updatePreview() {
        const fontSize = $('#font-size-slider').val() + 'px';
        const lineHeight = $('#line-height-slider').val();
        const letterSpacing = $('#letter-spacing-slider').val() + 'px';
        const wordSpacing = $('#word-spacing-slider').val() + 'px';
        
        $('#preview-text').css({
            'font-size': fontSize,
            'line-height': lineHeight,
            'letter-spacing': letterSpacing,
            'word-spacing': wordSpacing
        });
    }
});
        ";
    }
}

// Create the CSS and JS files when the plugin is activated
register_activation_hook(__FILE__, function() {
    $plugin_dir = plugin_dir_path(__FILE__);
    
    // Create CSS file
    file_put_contents($plugin_dir . 'font-tester.css', font_tester_css_content());
    
    // Create JS file  
    file_put_contents($plugin_dir . 'font-tester.js', font_tester_js_content());
});
?>
