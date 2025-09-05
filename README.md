# DG10 Elementor Form Anti-Spam

Advanced anti-spam protection for Elementor forms with intelligent validation, honeypot fields, and customizable settings.

## Features

- **Advanced Field Validation**
  - Phone number validation with spam number detection
  - Email validation with spam TLD filtering
  - Name field validation with minimum length requirements
  - Custom validation rules for different field types

- **Intelligent Spam Detection**
  - Honeypot fields to catch automated submissions
  - Time-based submission validation
  - Spam keyword filtering
  - IP-based rate limiting

- **Customizable Settings**
  - Minimum name length configuration
  - Maximum submissions per hour limit
  - Enable/disable honeypot protection
  - Enable/disable time-based checks
  - Enable/disable spam keyword filtering
  - Custom error messages

- **Performance Optimized**
  - Efficient client-side validation
  - Minimal database queries
  - Lightweight and fast processing

- **Developer Friendly**
  - Clean, well-documented code
  - WordPress coding standards compliant
  - Extensible architecture
  - Hooks and filters for customization

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Elementor Pro (latest version recommended)

## Installation

1. Upload the plugin files to `/wp-content/plugins/dg10-elementor-form-anti-spam`
2. Activate the plugin through the WordPress plugins screen
3. Configure the plugin settings under Settings > DG10 Anti-Spam

## Configuration

### General Settings

- **Minimum Name Length**: Set the minimum required length for name fields (default: 2)
- **Max Submissions per Hour**: Limit the number of form submissions from a single IP (default: 5)
- **Enable Honeypot**: Add hidden fields to catch automated submissions
- **Enable Time Check**: Validate submission timing to prevent rapid submissions
- **Enable Spam Keywords**: Filter submissions containing known spam keywords
- **Custom Error Message**: Set your own error message for invalid submissions

### Usage

Once activated and configured, the plugin automatically protects all Elementor forms on your website. No additional configuration is needed in the Elementor form builder.

## Support

For support questions, bug reports, or feature requests, please use the WordPress.org plugin support forums or visit our website.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

GPL v2 or later