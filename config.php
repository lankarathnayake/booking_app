<?php
/**
 * Central configuration for the standalone Booking App.
 *
 * The values below are PRODUCTION defaults. For local development, drop a
 * config.local.php next to this file (git-ignored) that define()s whatever
 * differs locally - it is loaded first and its definitions win.
 */

// Local overrides win - load them before the defaults below.
if (is_file(__DIR__ . '/config.local.php')) {
	require __DIR__ . '/config.local.php';
}

// ---- Database (own DB, fully independent of kcj.lk) ----
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_USER') || define('DB_USER', 'booking_app');
defined('DB_PASS') || define('DB_PASS', 'CHANGE_ME_IN_PROD');
defined('DB_NAME') || define('DB_NAME', 'booking_app');

// ---- Public URL (no trailing slash) ----
defined('APP_URL') || define('APP_URL', 'https://book.example.com');

// ---- SMTP (used by core/EmailService.php) ----
defined('SMTP_HOST')       || define('SMTP_HOST', 'smtp.example.com');
defined('SMTP_PORT')       || define('SMTP_PORT', 587);
defined('SMTP_SECURE')     || define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
defined('SMTP_USERNAME')   || define('SMTP_USERNAME', '');
defined('SMTP_PASSWORD')   || define('SMTP_PASSWORD', '');
defined('SMTP_FROM_EMAIL') || define('SMTP_FROM_EMAIL', 'no-reply@example.com');
defined('SMTP_FROM_NAME')  || define('SMTP_FROM_NAME', 'Booking App');

// ---- Session ----
defined('SESSION_NAME') || define('SESSION_NAME', 'booking_app_session');

// ---- API ----
// Static key for server-to-server calls (api/links/generate.php). Blank
// disables the API entirely (every request is rejected). Set a long random
// value in config.local.php / production config.php - never commit a real one.
defined('API_KEY') || define('API_KEY', '');

// ---- Timezone ----
// All date/time logic (availability, lead-time cutoffs, email content) runs
// against this. Without an explicit setting PHP falls back to php.ini's
// date.timezone (often not the business's actual timezone), which silently
// breaks "is this slot still in the future" checks. Set explicitly.
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'UTC');
date_default_timezone_set(APP_TIMEZONE);
