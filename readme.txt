=== Font Type Tester ===
Contributors: mitradranirban
Donate link: https://paypal.me/dranirban
Tags: fonts, font-tester, font-preview, typography-tools, static-fonts
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.11
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive font testing tool with real-time typography controls and font source obfuscation for secure font preview.

== Description ==

Font Type Tester is a powerful WordPress plugin designed for designers, developers, and typography enthusiasts who need to test and preview static fonts with precision control over typography settings.

**Key Features:**

* **Font Upload & Management** - Upload TTF, OTF, WOFF, and WOFF2 font files
* **Real-time Typography Controls** - Adjust font size, line height, letter spacing, and word spacing with intuitive sliders
* **Font Source Protection** - Automatic obfuscation of font filenames to protect original sources
* **Custom Sample Text** - Test fonts with your own content
* **Responsive Interface** - Works perfectly on desktop and mobile devices
* **Secure File Handling** - Validates file types and implements security measures
* **Easy Integration** - Simple shortcode implementation

**Perfect for:**

* Web designers testing fonts for client projects
* Typography enthusiasts exploring font characteristics
* Developers needing font preview functionality
* Anyone who wants to test fonts without revealing source files

**Privacy & Security:**

The plugin automatically renames uploaded font files with random strings, ensuring that the original font source remains protected. This is particularly useful when testing premium fonts or when you need to share font previews without exposing the actual font files.

== Installation ==

**Automatic Installation:**

1. Log in to your WordPress admin panel
2. Go to Plugins > Add New
3. Search for "Font Type Tester"
4. Click "Install Now" and then "Activate"

**Manual Installation:**

1. Download the plugin files
2. Upload the `font-type-tester` folder to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. The plugin will automatically create necessary directories and database tables

**Usage:**

1. Admin Side:

Go to Settings → Font Tester in WordPress admin
Upload fonts with optional custom names
Manage (view/delete) uploaded fonts
Copy the shortcode [fotyte_font_tester] for use


2. Front-end:

Add [fotyte_font_tester] shortcode to any page/post
Users can select from available fonts via dropdown
Full typography controls remain available
No upload interface visible to regular users

== Frequently Asked Questions ==

= What font formats are supported? =

The plugin supports TTF, OTF, WOFF, and WOFF2 font formats, which cover the majority of web font needs.

= Are my font files secure? =

Yes! The plugin automatically obfuscates font filenames by renaming them with random strings. This protects the original source while still allowing full functionality.

= Can I use this plugin on multiple pages? =

Absolutely! You can use the `[font_tester]` shortcode on any page, post, or widget area where shortcodes are supported.

= What happens to uploaded fonts when I deactivate the plugin? =

When the plugin is deactivated, all uploaded font files and database entries are automatically cleaned up to keep your site tidy.

= Can I customize the sample text? =

Yes! The plugin includes a textarea where user can input any custom text to test how fonts render with your specific content.

= Is there a file size limit for font uploads? =

The plugin respects your WordPress and server upload limits. Most font files are well within typical limits, but very large font files may need server configuration adjustments.

= Does this work with variable fonts? =

This version focuses on static fonts. Variable font support is available in our <a href="https://github.com/mitradranirban/variable-font-sampler">Variable Font sampler</a>.

= Can I delete uploaded fonts? =

Yes! Each uploaded font can be deleted individually through the interface, which removes both the file and database record.


== Changelog ==
= 1.1.11 =
* Implemented custom font upload handler bypassing WordPress MIME restrictions
* Added binary file signature validation for TTF, OTF, WOFF, and WOFF2 formats
* Enhanced security with multi-layer file validation (extension + signature + size)
* Improved error handling with specific error messages and file cleanup
* Added upload success notifications with font name display
* Increased maximum upload size from 5MB to 10MB

= 1.1.10  =
**Security & Code Quality Release**
* SECURITY FIX: Properly sanitized file upload inputs to prevent potential security vulnerabilities
* SECURITY FIX: Eliminated unprepared SQL statements and improved database query security
* SECURITY FIX: Removed intermediate SQL variables that could pose security risks
* CODE QUALITY: Updated database queries to follow WordPress coding standards (PHPCS compliance)
* CODE QUALITY: Improved prepared statement handling for better security practices
* PERFORMANCE: Maintained existing caching functionality while improving query security
* COMPATIBILITY: No breaking changes - fully backward compatible with previous versions

= 1.1.9 =
* **SECURITY UPDATE**: Fixed all WordPress Coding Standards warnings and security vulnerabilities
* Implemented proper input sanitization for $_FILES['font_file'] using wp_check_filetype() and wp_handle_upload()
* Fixed SQL injection vulnerabilities by properly escaping table names with backticks in prepared statements
* Replaced direct database queries with WordPress built-in methods ($wpdb->insert(), $wpdb->delete())
* Enhanced caching implementation with wp_cache_get(), wp_cache_set(), and proper cache invalidation
* Added comprehensive nonce verification and capability checks for all admin functions
* Implemented secure file upload handling with MIME type validation
* Added proper error handling and user feedback for upload/delete operations
* Enhanced input validation using sanitize_text_field(), wp_unslash(), and absint()
* Improved database query performance with object caching (3600 second cache timeout)
* Fixed WordPress.Security.ValidatedSanitizedInput.InputNotSanitized warnings
* Fixed WordPress.DB.PreparedSQL.InterpolatedNotPrepared warnings
* Fixed WordPress.DB.DirectDatabaseQuery.DirectQuery and NoCaching warnings

= 1.1.8 =
* Sanitized all `$_POST` and `$_FILES` inputs with `sanitize_text_field()` and `wp_unslash()`
* Added `isset()` and `is_numeric()` guards for all external input
* Used proper `$wpdb->prepare()` syntax — avoided direct string interpolation and removed unsafe `$table` placeholders
* Implemented WP object caching via `wp_cache_get()` and `wp_cache_set()` for font listing and lookup
* Used `wp_cache_delete()` on upload/delete to invalidate cache
* Added version parameters (`'1.1.8'`) to all styles/scripts to fix browser cache busting
* Passed PHPCS codesniffing standards with WordPress-Extra + WordPress-Docs + WordPress-VIP rules

= 1.1.7 =
* Fixed `$wpdb` query interpolation warnings
* Sanitized inputs and replaced `rename()` with `WP_Filesystem->move()`
* Implemented object caching pattern for all fetching queries
* Fixed database queries to use `$wpdb->prepare()` — no raw variable interpolation
* Improved sanitization and validation for `$_POST['font_name']` using `isset()` and `wp_unslash()`
* Validated and sanitized `$_FILES['font_file']` input properly
* Replaced `rename()` with `$wp_filesystem->move()` per WPCoding standards
* Added `version` to all uses of `wp_register_style()` and `wp_register_script()` to fix browser cache busting
* Added object caching (`wp_cache_get`, `wp_cache_set`) to SELECT queries
* Invalidated caches on insert/delete using `wp_cache_delete()`
* Retained backwards-compatible shortcode: `[fotyte_font_tester]`

= 1.1.6 =
* Residual use of heredoc syntax cleaned

= 1.1.5 =
* Fully prefixed all functions, actions, shortcodes, and handles with `fotyte_`

= 1.1.4 =
* Switched from writing JS/CSS files to using `wp_add_inline_style` and `wp_add_inline_script`

= 1.1.3 =
* Prepend function names with unique characters fotyte_ 
= 1.1.2 = 
* Rearrange UI to put Font Preview on top 
= 1.1.1 = 
* Correcte Readme
= 1.1.0 =
1. Admin Interface Added

New admin menu item under Settings → Font Tester
Clean admin interface for font upload and management
Table view of all uploaded fonts with delete functionality
Usage instructions for the shortcode

2. Front-end Changes

Removed font upload form completely
Simplified interface focusing only on font testing
Users can only select from fonts uploaded by administrators
Shows message when no fonts are available

3. Security Improvements

Added capability checks (manage_options) for all admin functions
Only administrators can upload and delete fonts
Enhanced permission validation

4. New Files Created
The plugin now creates 4 files:

font-tester.css - Front-end styles
font-tester.js - Front-end JavaScript
font-tester-admin.css - Admin interface styles
font-tester-admin.js - Admin interface JavaScript

5. Enhanced Functionality

Better caching system
Improved database queries with prepared statements
Cleaner admin interface with WordPress styling
Automatic page reload after font upload for immediate feedback

 
 
== Technical Requirements ==

* WordPress 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher (for font metadata storage)
* Modern browser with FontFace API support
* Sufficient upload directory permissions

== Support ==

For support, feature requests, or bug reports, please visit our support page or contact the developer.

== Privacy Policy ==

This plugin:
* Stores uploaded font files locally on your server
* Does not transmit any data to external services
* Obfuscates font filenames for privacy protection
* Stores minimal metadata in the WordPress database
* Automatically cleanups data upon deactivation

== Developer Notes ==

The plugin uses modern web APIs including the FontFace API for dynamic font loading. It implements WordPress best practices for:

* Secure AJAX handling with nonces
* Proper database table creation and cleanup
* File upload validation and security
* Responsive CSS grid layouts
* Cross-browser compatibility

For developers looking to extend functionality, the plugin provides clean hooks and follows WordPress coding standards.

== Credits ==

Developed with ❤️ for the WordPress community.

Special thanks to the WordPress core team for providing excellent APIs and documentation that make plugins like this possible.
