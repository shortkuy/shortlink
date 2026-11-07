<?php
/**
 * ============================================================
 *  SafeLink Shared — Database Configuration
 * ------------------------------------------------------------
 *  This file contains SENSITIVE credentials.
 *  PROTECTED by .htaccess — DO NOT make public.
 *
 *  Setup:
 *  1. Edit values below with your cPanel database info
 *  2. Ensure .htaccess file (in same folder) blocks direct access
 *  3. Test: visit config.php in browser → should return 403 Forbidden
 * ============================================================
 */

// === Database Credentials (from cPanel → MySQL Databases) ===
define('DB_HOST', 'localhost');           // Usually 'localhost' on shared hosting
define('DB_NAME', 'your_username_safelink');  // From cPanel: username_dbname
define('DB_USER', 'your_username_safelink_user');  // From cPanel: username_dbuser
define('DB_PASS', 'YourStrongPassword123!');  // The password you set in cPanel
define('DB_CHARSET', 'utf8mb4');

// === API Security ===
// This key protects admin operations (list, delete).
// Anyone with this key can manage links — keep it secret!
// Generate a new one: php -r "echo bin2hex(random_bytes(24));"
define('API_KEY', 'change-this-to-random-24-byte-hex-string');

// === Static Site URL (for the `go` page to load CSS/JS) ===
// The URL where your static site (HTML/CSS/JS) is deployed.
// Examples:
//   "https://username.github.io/safelink"    (GitHub Pages)
//   "https://safelink.yourdomain.com"         (custom domain)
//   "https://myapp.netlify.app"               (Netlify)
// Leave empty ("") if static files are in the SAME folder as api.php
define('STATIC_SITE_URL', 'https://yourdomain.com');

// === Allowed Origins (CORS) ===
// Set to your website URL. Multiple origins separated by comma.
// Example: 'https://yourdomain.com, https://www.yourdomain.com'
// Use '*' only for testing — NOT recommended for production.
define('ALLOWED_ORIGINS', 'https://yourdomain.com');

// === Error Reporting ===
// Set to false in production (don't leak DB errors to attackers)
define('DEBUG_MODE', false);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
