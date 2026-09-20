<?php require_once __DIR__ . '/session.php'; ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
	body { background: #f4f6f9; }
	.sidebar { width: 220px; min-height: 100vh; background: #1f2937; }
	.sidebar .nav-link { color: #cbd5e1; }
	.sidebar .nav-link.active, .sidebar .nav-link:hover { color: #ffffff; background: #2c7ea4; }
	.sidebar .brand { color: #ffffff; font-weight: 600; }
	.main-content { flex: 1; min-width: 0; }
	.top-bar { background: #ffffff; border-bottom: 1px solid #e2e8f0; }
</style>
