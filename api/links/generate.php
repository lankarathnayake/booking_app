<?php
/**
 * POST /api/links/generate.php
 *
 * Server-to-server endpoint: an authorized caller requests one-time
 * booking links for a service and gets them back in the response.
 *
 * Auth: X-API-Key header, "Authorization: Bearer <key>" header, or an
 * api_key field in the request body - checked against config's API_KEY.
 *
 * Body (JSON or form-encoded):
 *   service_id   int    - the service to generate links for (or service_slug)
 *   service_slug string - alternative to service_id
 *   count        int    - how many links to generate (default 1, max 100)
 *   note         string - optional label, shown in the admin Links page
 *
 * Response: { success, service: {...}, count, links: [{code, url}, ...] }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Service.php';
require_once __DIR__ . '/../../core/OneTimeLink.php';

header('Content-Type: application/json');

function api_error($code, $message) {
	http_response_code($code);
	echo json_encode(['success' => false, 'message' => $message]);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	api_error(405, 'Method not allowed. Use POST.');
}

// Accept JSON or standard form-encoded bodies.
$input = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
	$decoded = json_decode(file_get_contents('php://input'), true);
	if (is_array($decoded)) {
		$input = $decoded;
	}
}

$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
if (!$providedKey && !empty($_SERVER['HTTP_AUTHORIZATION']) && stripos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ') === 0) {
	$providedKey = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
}
if (!$providedKey) {
	$providedKey = $input['api_key'] ?? null;
}

if (empty(API_KEY) || !$providedKey || !hash_equals(API_KEY, (string) $providedKey)) {
	api_error(401, 'Invalid or missing API key.');
}

$serviceModel = new Service();
$linkModel = new OneTimeLink();

$serviceId = isset($input['service_id']) && $input['service_id'] !== '' ? (int) $input['service_id'] : null;
$serviceSlug = isset($input['service_slug']) ? trim((string) $input['service_slug']) : null;

$service = null;
if ($serviceId) {
	$service = $serviceModel->find($serviceId);
} elseif ($serviceSlug) {
	$service = $serviceModel->findBySlug($serviceSlug);
} else {
	api_error(400, 'Provide service_id or service_slug.');
}

if (!$service) {
	api_error(404, 'Service not found.');
}
if ((int) $service['status'] !== 1) {
	api_error(400, 'This service is not active.');
}

$count = isset($input['count']) && $input['count'] !== '' ? (int) $input['count'] : 1;
if ($count < 1) {
	$count = 1;
}
$note = isset($input['note']) ? trim((string) $input['note']) : null;

// created_by stays null (no admin session here) - that's how an API-generated
// link is distinguished from one an admin made by hand in the Links page.
$result = $linkModel->generate($service['id'], $count, $note ?: null, null);

$links = array_map(function ($code) {
	return [
		'code' => $code,
		'url' => rtrim(APP_URL, '/') . '/book/index.php?link=' . $code,
	];
}, $result['codes']);

http_response_code(201);
echo json_encode([
	'success' => true,
	'service' => ['id' => (int) $service['id'], 'name' => $service['name'], 'slug' => $service['slug']],
	'count' => count($links),
	'links' => $links,
]);
