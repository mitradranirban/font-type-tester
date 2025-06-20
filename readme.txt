=== Font Type Tester ===
Contributors: mitradranirban
Donate link: https://your-domain.com/donate
Tags: fonts, typography, font-tester, web-fonts, font-preview, typography-tools, font-upload, static-fonts
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
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

1. Add the shortcode `[font_tester]` to any page or post where you want the font tester to appear
2. Upload your font files using the upload form
3. Select fonts from the dropdown and start testing!

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

Yes! The plugin includes a textarea where you can input any custom text to test how fonts render with your specific content.

= Is there a file size limit for font uploads? =

The plugin respects your WordPress and server upload limits. Most font files are well within typical limits, but very large font files may need server configuration adjustments.

= Does this work with variable fonts? =

This version focuses on static fonts. Variable font support may be added in future versions based on user feedback.

= Can I delete uploaded fonts? =

Yes! Each uploaded font can be deleted individually through the interface, which removes both the file and database record.

== Screenshots ==

1. **Main Interface** - The complete font testing interface with all controls
2. **Font Upload** - Simple drag-and-drop font upload area
3. **Typography Controls** - Real-time sliders for font adjustments
4. **Font Preview** - Live preview area showing your selected font
5. **Mobile View** - Responsive design works perfectly on mobile devices
6. **Font Management** - Easy font selection and deletion

== Changelog ==

= 1.0.0 =
* Initial release
* Font upload functionality with TTF, OTF, WOFF, WOFF2 support
* Real-time typography controls (size, line height, letter spacing, word spacing)
* Font source obfuscation for security
* Custom sample text input
* Responsive design
* Secure AJAX handling
* Database integration for font management
* Automatic cleanup on deactivation

== Upgrade Notice ==

= 1.0.0 =
Initial release of Font Type Tester. Install to start testing fonts with advanced typography controls.

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
