<?php
/**
 * Session + CSRF helpers shared by the admin pages and the sign-in page.
 */

require_once __DIR__ . '/../config.php';

function start_app_session() {
	if (session_status() === PHP_SESSION_ACTIVE) {
		return;
	}
	$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	// Scope the cookie to this app's URL path so it isn't sent to sibling apps on the same host.
	$path = parse_url(APP_URL, PHP_URL_PATH) ?: '/';

	session_name(SESSION_NAME);
	session_set_cookie_params([
		'lifetime' => 0,
		'path' => $path,
		'secure' => $secure,
		'httponly' => true,
		'samesite' => 'Lax',
	]);
	session_start();
}

function csrf_token() {
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

/**
 * True if the request carries the session's CSRF token, either in the
 * X-CSRF-Token header (jQuery AJAX) or a csrf_token form field.
 */
function csrf_valid() {
	$sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
	$expected = $_SESSION['csrf_token'] ?? '';
	return is_string($sent) && $sent !== '' && $expected !== '' && hash_equals($expected, $sent);
}
