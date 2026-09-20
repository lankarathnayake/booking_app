<?php
require_once __DIR__ . '/../../common/bootstrap.php';
require_once __DIR__ . '/../../core/Service.php';
require_once __DIR__ . '/../../core/Booking.php';
require_once __DIR__ . '/../../core/BookingFieldValue.php';

$serviceModel = new Service();
$bookingModel = new Booking();

$filters = [
	'service_id' => (int) ($_GET['service_id'] ?? 0) ?: null,
	'status' => $_GET['status'] ?? null,
	'date_from' => $_GET['date_from'] ?? null,
	'date_to' => $_GET['date_to'] ?? null,
	'field_query' => trim($_GET['field_query'] ?? '') ?: null,
];

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
	$fieldValueModel = new BookingFieldValue();
	$result = $bookingModel->search($filters, 1, 5000);

	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="bookings.csv"');
	$out = fopen('php://output', 'w');

	// Collect the union of field labels present in this result set for stable columns.
	$fieldLabels = [];
	$rowFieldMap = [];
	foreach ($result['rows'] as $row) {
		$values = $fieldValueModel->getForBooking($row['id']);
		$map = [];
		foreach ($values as $v) {
			$fieldLabels[$v['field_key']] = $v['field_label_snapshot'];
			$map[$v['field_key']] = $v['value_text'];
		}
		$rowFieldMap[$row['id']] = $map;
	}

	$header = ['Reference', 'Service', 'Date', 'Time', 'Status', 'Created At'];
	foreach ($fieldLabels as $label) { $header[] = $label; }
	fputcsv($out, $header);

	foreach ($result['rows'] as $row) {
		$line = [$row['booking_reference'], $row['service_name_snapshot'], $row['appointment_date'], $row['appointment_time'], $row['status'], $row['created_at']];
		foreach (array_keys($fieldLabels) as $key) {
			$line[] = $rowFieldMap[$row['id']][$key] ?? '';
		}
		fputcsv($out, $line);
	}
	fclose($out);
	exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$result = $bookingModel->search($filters, $page, 20);
$totalPages = max(1, (int) ceil($result['total'] / 20));
$services = $serviceModel->getAll();

function buildQuery($overrides) {
	$params = array_merge($_GET, $overrides);
	return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Bookings - Booking App</title>
	<?php include __DIR__ . '/../../common/header.php'; ?>
</head>
<body>
	<div class="d-flex">
		<?php include __DIR__ . '/../../common/nav.php'; ?>
		<div class="main-content">
			<?php include __DIR__ . '/../../common/top_nav_bar.php'; ?>
			<div class="container-fluid p-4">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h4 class="mb-0">Bookings</h4>
					<a class="btn btn-outline-secondary" href="<?php echo buildQuery(['export' => 'csv']); ?>"><i class="bi bi-download"></i> Export CSV</a>
				</div>

				<form class="row g-2 mb-3" method="get">
					<div class="col-auto">
						<select name="service_id" class="form-select">
							<option value="">All services</option>
							<?php foreach ($services as $s) { ?>
								<option value="<?php echo (int) $s['id']; ?>" <?php echo $filters['service_id'] == $s['id'] ? 'selected' : ''; ?>><?php echo h($s['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-auto">
						<select name="status" class="form-select">
							<option value="">All statuses</option>
							<?php foreach (['confirmed', 'cancelled', 'completed'] as $st) { ?>
								<option value="<?php echo $st; ?>" <?php echo $filters['status'] === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-auto">
						<input type="date" name="date_from" class="form-control" value="<?php echo h($filters['date_from']); ?>" placeholder="From">
					</div>
					<div class="col-auto">
						<input type="date" name="date_to" class="form-control" value="<?php echo h($filters['date_to']); ?>" placeholder="To">
					</div>
					<div class="col-auto">
						<input type="text" name="field_query" class="form-control" value="<?php echo h($filters['field_query']); ?>" placeholder="Search any field...">
					</div>
					<div class="col-auto">
						<button type="submit" class="btn btn-primary">Filter</button>
						<a href="index.php" class="btn btn-outline-secondary">Reset</a>
					</div>
				</form>

				<div class="card">
					<div class="table-responsive">
						<table class="table mb-0 align-middle">
							<thead><tr><th>Reference</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
							<tbody>
								<?php if (!$result['rows']) { ?>
									<tr><td colspan="6" class="text-secondary">No bookings match these filters.</td></tr>
								<?php } ?>
								<?php foreach ($result['rows'] as $b) {
									$badge = ['confirmed' => 'success', 'cancelled' => 'danger', 'completed' => 'secondary'][$b['status']];
								?>
									<tr>
										<td><?php echo h($b['booking_reference']); ?></td>
										<td><?php echo h($b['service_name_snapshot']); ?></td>
										<td><?php echo h(date('j M Y', strtotime($b['appointment_date']))); ?></td>
										<td><?php echo h(date('g:i A', strtotime($b['appointment_time']))); ?></td>
										<td><span class="badge bg-<?php echo $badge; ?>"><?php echo h($b['status']); ?></span></td>
										<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?php echo (int) $b['id']; ?>">View</a></td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				</div>

				<?php if ($totalPages > 1) { ?>
					<nav class="mt-3">
						<ul class="pagination">
							<?php for ($p = 1; $p <= $totalPages; $p++) { ?>
								<li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
									<a class="page-link" href="<?php echo buildQuery(['page' => $p]); ?>"><?php echo $p; ?></a>
								</li>
							<?php } ?>
						</ul>
					</nav>
				<?php } ?>
			</div>
			<?php include __DIR__ . '/../../common/footer_main.php'; ?>
		</div>
	</div>
	<?php include __DIR__ . '/../../common/footer.php'; ?>
</body>
</html>
