=== DG10 Elementor Form Anti-Spam ===
Contributors: dg10agency
Tags: elementor, anti-spam, form validation, security, honeypot, ai, spam protection
Requires at least: 5.6
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced form validation and spam protection for Elementor Pro forms with AI-powered spam detection, honeypot fields, and geographic blocking.

== Description ==

DG10 Elementor Form Anti-Spam provides comprehensive protection for your Elementor forms against spam, bots, and malicious submissions. The plugin offers both server-side validation (with Elementor Pro) and client-side validation (Lite mode) to ensure maximum compatibility.

= Key Features =

* **Dual Mode Operation**
  * Pro Mode: Full server-side validation for Elementor Pro users
  * Lite Mode: Client-side validation for Elementor Free users
  * Automatic detection and graceful degradation

* **Advanced Spam Protection**
  * Honeypot fields to catch automated submissions
  * Time-based submission validation
  * IP-based rate limiting with database persistence
  * Spam keyword filtering
  * AI-powered spam detection (DeepSeek & Gemini)

* **Geographic Blocking**
  * Block or allow submissions based on visitor's country
  * Free GeoIP detection without external API calls
  * Whitelist specific IP addresses
  * Country-based statistics

* **Time-Based Rules**
  * Business hours restrictions
  * Weekend and holiday mode
  * Different security levels for different times
  * Timezone support

* **Intelligent Validation**
  * Phone number validation with spam detection
  * Email validation with TLD filtering
  * Name field validation with minimum length
  * Custom validation rules

* **Performance Optimized**
  * Efficient database queries with proper indexing
  * Minimal resource usage
  * Automatic cleanup of old data
  * Lightweight and fast processing

= Requirements =

* WordPress 5.6 or higher
* PHP 7.4 or higher
* Elementor (Free or Pro)
  * Pro Mode: Elementor Pro for full server-side validation
  * Lite Mode: Elementor Free for client-side validation

= Installation =

1. Upload the plugin files to `/wp-content/plugins/dg10-elementor-form-anti-spam/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin settings under Settings > DG10 Anti-Spam

= Frequently Asked Questions =

= Does this plugin work with Elementor Free? =

Yes! The plugin includes a Lite Mode that provides client-side validation for Elementor Free users. While you won't get server-side enforcement, you'll still benefit from honeypot protection, time-based checks, and basic field validation.

= What's the difference between Pro and Lite mode? =

* **Pro Mode** (with Elementor Pro): Full server-side validation, IP rate limiting, AI spam detection, geographic blocking, and time-based rules
* **Lite Mode** (Elementor Free): Client-side validation with honeypot, time checks, and basic field validation

= Do I need API keys for AI features? =

Yes, you'll need API keys from DeepSeek and/or Google Gemini to use the AI-powered spam detection features. These are optional and the plugin works perfectly without them.

= Is my data secure? =

Absolutely. The plugin follows WordPress security best practices, includes proper input sanitization and output escaping, and doesn't store sensitive user data. All API communications are encrypted.

= Can I customize error messages? =

Yes, you can customize error messages for different validation scenarios through the plugin settings.

= Does the plugin affect site performance? =

No, the plugin is designed to be lightweight and efficient. It uses minimal resources and includes automatic cleanup of old data.

= Screenshots ==

1. Plugin settings page with comprehensive configuration options
2. Statistics dashboard showing blocked attempts and protection metrics
3. Geographic blocking configuration with country selection
4. Time-based rules setup with business hours and holiday modes
5. AI settings configuration for DeepSeek and Gemini integration
6. Lite mode settings for Elementor Free users

== Changelog ==

= 1.0.0 =
* Initial release
* Advanced form validation and spam protection
* Dual mode operation (Pro/Lite)
* AI-powered spam detection (DeepSeek & Gemini)
* Geographic blocking with country-based restrictions
* Time-based rules with business hours and holiday modes
* Honeypot fields and time-based validation
* IP rate limiting with database persistence
* Comprehensive admin interface
* Full internationalization support
* WordPress.org compliance

== Upgrade Notice ==

= 1.0.0 =
Initial release of DG10 Elementor Form Anti-Spam. Install to start protecting your Elementor forms from spam and malicious submissions.

== Markdown Examples ==

### Basic Usage

Once activated, the plugin automatically protects all Elementor forms on your website. No additional configuration is needed in the Elementor form builder.

### Configuration

Navigate to **Settings > DG10 Anti-Spam** to configure:

* **General Settings**: Basic validation rules and error messages
* **AI Settings**: Configure DeepSeek and Gemini API keys
* **Geographic Blocking**: Set up country-based restrictions
* **Time Rules**: Configure business hours and holiday modes
* **Lite Mode**: Settings for Elementor Free users

### Hooks and Filters

The plugin provides several hooks for developers:

```php
// Modify validation rules
add_filter('dg10_validation_rules', 'my_custom_validation_rules');

// Add custom spam detection
add_action('dg10_before_validation', 'my_custom_spam_check');

// Modify error messages
add_filter('dg10_error_message', 'my_custom_error_message');
```

== Support ==

For support questions, bug reports, or feature requests, please use the WordPress.org plugin support forums or visit our website at [www.dg10.agency](https://www.dg10.agency).

== Privacy Policy ==

This plugin does not collect, store, or transmit any personal data. All form validation is performed locally on your server. API keys for AI services are stored securely in your WordPress database and are only used for spam detection purposes.

== Credits ==

Developed by [DG10 Agency](https://www.dg10.agency) - Professional WordPress development services.

== Technical Details ==

* **Database Tables**: Creates one custom table for submission logging
* **Options**: Stores settings in WordPress options table
* **User Meta**: Stores dismissed notices per user
* **Cron Jobs**: None (uses WordPress hooks for cleanup)
* **File Uploads**: None
* **External Requests**: Only when AI features are enabled and API keys are provided

