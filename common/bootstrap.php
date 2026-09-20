<?php
/**
 * Included at the top of every admin page (one level under admin/, e.g.
 * admin/services/index.php). Starts the session, enforces login and CSRF
 * protection on POSTs, and computes the active nav item from the containing
 * folder name - no hardcoded route list to maintain as features are added.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/session.php';

start_app_session();

if (empty($_SESSION['admin_id'])) {
	header('Location: ' . rtrim(APP_URL, '/') . '/admin/signin.php');
	exit;
}

// Every state-changing admin request is a POST; require the session's CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid()) {
	http_response_code(403);
	header('Content-Type: application/json');
	echo json_encode(['success' => false, 'message' => 'Your session token is missing or expired. Reload the page and try again.']);
	exit;
}

// e.g. .../admin/services/index.php -> "services"; .../admin/dashboard/index.php -> "dashboard"
$current_page = basename(dirname($_SERVER['SCRIPT_FILENAME']));

function current_admin_id() {
	return $_SESSION['admin_id'] ?? null;
}

function current_admin_name() {
	return $_SESSION['admin_name'] ?? '';
}

function h($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
