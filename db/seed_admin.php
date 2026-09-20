<?php
/**
 * CLI-only helper to create the first admin user.
 * Usage: php db/seed_admin.php "Name" "email@example.com" "password"
 */
if (php_sapi_name() !== 'cli') {
	http_response_code(403);
	die('CLI only.');
}

require_once __DIR__ . '/../core/AdminUser.php';

[$script, $name, $email, $password] = array_pad($argv, 4, null);

if (!$name || !$email || !$password) {
	fwrite(STDERR, "Usage: php db/seed_admin.php \"Name\" \"email@example.com\" \"password\"\n");
	exit(1);
}

$adminUser = new AdminUser();
$id = $adminUser->create($name, $email, $password);
echo "Created admin user #$id ($email)\n";
