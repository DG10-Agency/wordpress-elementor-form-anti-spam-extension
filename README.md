# DG10 Elementor Form Anti-Spam

Advanced anti-spam protection for Elementor forms with intelligent validation, honeypot fields, and customizable settings.

## Features

- **Advanced Field Validation**
  - Phone number validation with spam number detection (supports international formats)
  - Email validation with spam TLD filtering
  - Name field validation with minimum length requirements
  - Custom validation rules for different field types

- **Intelligent Spam Detection**
  - Honeypot fields to catch automated submissions
  - Time-based submission validation
  - Spam keyword filtering
  - IP-based rate limiting with database persistence
  - AI-powered spam detection (DeepSeek & Gemini)

- **Dual Mode Operation**
  - **Pro Mode**: Full server-side validation for Elementor Pro users
  - **Lite Mode**: Client-side validation for Elementor Free users
  - Automatic detection and graceful degradation

- **Customizable Settings**
  - Minimum name length configuration
  - Maximum submissions per hour limit
  - Enable/disable honeypot protection
  - Enable/disable time-based checks
  - Enable/disable spam keyword filtering
  - Custom error messages
  - AI API key configuration

- **Performance Optimized**
  - Efficient client-side validation
  - Minimal database queries with proper indexing
  - Lightweight and fast processing
  - Automatic cleanup of old data

- **Developer Friendly**
  - Clean, well-documented code
  - WordPress coding standards compliant
  - Extensible architecture
  - Hooks and filters for customization
  - Comprehensive error handling

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Elementor (Free or Pro)
  - **Pro Mode**: Elementor Pro for full server-side validation
  - **Lite Mode**: Elementor Free for client-side validation

## Installation

1. Upload the plugin files to `/wp-content/plugins/dg10-elementor-form-anti-spam`
2. Activate the plugin through the WordPress plugins screen
3. Configure the plugin settings under Settings > DG10 Anti-Spam

## Configuration

### General Settings

- **Minimum Name Length**: Set the minimum required length for name fields (default: 2)
- **Max Submissions per Hour**: Limit the number of form submissions from a single IP (default: 5) - Pro Mode only
- **Enable Honeypot**: Add hidden fields to catch automated submissions
- **Enable Time Check**: Validate submission timing to prevent rapid submissions
- **Enable Spam Keywords**: Filter submissions containing known spam keywords - Pro Mode only
- **Custom Error Message**: Set your own error message for invalid submissions

### AI Settings (Pro Mode)

- **Enable DeepSeek AI**: Use DeepSeek AI for advanced spam detection
- **DeepSeek API Key**: Your DeepSeek API key for AI validation
- **Enable Gemini AI**: Use Google Gemini AI for spam detection
- **Gemini API Key**: Your Google Gemini API key

### Lite Mode Settings

- **Enable Lite Mode**: Activate client-side validation for non-Elementor forms
- **Lite Mode Form Selector**: CSS selector for forms to protect (e.g., `#contact-form`)

### Usage

Once activated and configured, the plugin automatically protects all Elementor forms on your website. No additional configuration is needed in the Elementor form builder.

## Support

For support questions, bug reports, or feature requests, please use the WordPress.org plugin support forums or visit our website.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

GPL v2 or later