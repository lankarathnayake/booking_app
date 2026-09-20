<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../common/session.php';
require_once __DIR__ . '/../core/AdminUser.php';
require_once __DIR__ . '/../core/LoginThrottle.php';

start_app_session();

function h($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (!empty($_SESSION['admin_id'])) {
	header('Location: ' . rtrim(APP_URL, '/') . '/admin/dashboard/index.php');
	exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim($_POST['email'] ?? '');
	$password = (string) ($_POST['password'] ?? '');
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';

	$throttle = new LoginThrottle();

	if (!csrf_valid()) {
		$error = 'Your session expired. Please try again.';
	} elseif ($throttle->isBlocked($ip, $email)) {
		$error = 'Too many failed sign-in attempts. Please wait ' . LoginThrottle::WINDOW_MINUTES . ' minutes and try again.';
	} else {
		$adminUser = new AdminUser();
		$user = $adminUser->authenticate($email, $password);

		if ($user) {
			$throttle->clearForIp($ip);
			session_regenerate_id(true);
			unset($_SESSION['csrf_token']); // fresh token for the authenticated session
			$_SESSION['admin_id'] = $user['id'];
			$_SESSION['admin_name'] = $user['name'];
			$_SESSION['admin_role'] = $user['role'];
			header('Location: ' . rtrim(APP_URL, '/') . '/admin/dashboard/index.php');
			exit;
		}

		$throttle->recordFailure($ip, $email);
		$error = 'Invalid email or password.';
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Sign In - Booking App</title>
	<?php include __DIR__ . '/../common/header.php'; ?>
</head>
<body>
	<div class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
		<div class="card shadow-sm" style="width: 360px;">
			<div class="card-body p-4">
				<h4 class="mb-3 text-center"><i class="bi bi-calendar-check"></i> Booking App</h4>
				<?php if ($error) { ?>
					<div class="alert alert-danger py-2"><?php echo h($error); ?></div>
				<?php } ?>
				<form method="post">
					<input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
					<div class="mb-3">
						<label class="form-label">Email</label>
						<input type="email" name="email" class="form-control" required autofocus>
					</div>
					<div class="mb-3">
						<label class="form-label">Password</label>
						<input type="password" name="password" class="form-control" required>
					</div>
					<button type="submit" class="btn btn-primary w-100">Sign In</button>
				</form>
			</div>
		</div>
	</div>
</body>
</html>
