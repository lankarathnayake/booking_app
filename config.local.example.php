<?php
/**
 * Copy this file to config.local.php and fill in your own values.
 * config.local.php is git-ignored and loaded first by config.php, so
 * anything defined here overrides the defaults in config.php.
 * NEVER commit config.local.php - it holds real credentials.
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_USER', 'booking_app');
define('DB_PASS', 'a-strong-password');
define('DB_NAME', 'booking_app');

// ---- Public URL of this app, no trailing slash ----
define('APP_URL', 'https://book.example.com');

// ---- Timezone all date/time logic runs in (PHP timezone identifier) ----
define('APP_TIMEZONE', 'UTC');

// ---- SMTP (booking confirmation emails) ----
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // 'tls' (STARTTLS) or 'ssl' (implicit TLS, usually port 465)
define('SMTP_USERNAME', 'smtp-user');
define('SMTP_PASSWORD', 'smtp-password');
define('SMTP_FROM_EMAIL', 'no-reply@example.com');
define('SMTP_FROM_NAME', 'Your Business');

// ---- API key for api/links/generate.php ----
// Generate with: php -r "echo bin2hex(random_bytes(24));"
// Leave blank to disable the API entirely.
define('API_KEY', '');
