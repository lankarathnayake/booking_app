<?php
require_once __DIR__ . '/../../common/bootstrap.php';
require_once __DIR__ . '/../../core/Service.php';
require_once __DIR__ . '/../../core/OneTimeLink.php';
require_once __DIR__ . '/../../core/Booking.php';

$serviceModel = new Service();
$linkModel = new OneTimeLink();
$bookingModel = new Booking();

$activeServices = $serviceModel->count(false);
$unusedLinks = $linkModel->countByStatus('unused');
$upcomingBookings = $bookingModel->countUpcoming();
$totalBookings = $bookingModel->countTotal();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Dashboard - Booking App</title>
	<?php include __DIR__ . '/../../common/header.php'; ?>
</head>
<body>
	<div class="d-flex">
		<?php include __DIR__ . '/../../common/nav.php'; ?>
		<div class="main-content">
			<?php include __DIR__ . '/../../common/top_nav_bar.php'; ?>
			<div class="container-fluid p-4">
				<h4 class="mb-3">Dashboard</h4>
				<div class="row g-3">
					<div class="col-md-3">
						<div class="card"><div class="card-body">
							<div class="text-secondary small">Active Services</div>
							<div class="fs-3 fw-bold"><?php echo $activeServices; ?></div>
						</div></div>
					</div>
					<div class="col-md-3">
						<div class="card"><div class="card-body">
							<div class="text-secondary small">Unused Links</div>
							<div class="fs-3 fw-bold"><?php echo $unusedLinks; ?></div>
						</div></div>
					</div>
					<div class="col-md-3">
						<div class="card"><div class="card-body">
							<div class="text-secondary small">Upcoming Bookings</div>
							<div class="fs-3 fw-bold"><?php echo $upcomingBookings; ?></div>
						</div></div>
					</div>
					<div class="col-md-3">
						<div class="card"><div class="card-body">
							<div class="text-secondary small">Total Bookings</div>
							<div class="fs-3 fw-bold"><?php echo $totalBookings; ?></div>
						</div></div>
					</div>
				</div>

				<div class="row g-3 mt-1">
					<div class="col-md-4">
						<a href="../services/index.php" class="card text-decoration-none text-dark h-100">
							<div class="card-body"><i class="bi bi-briefcase fs-3"></i><h5 class="mt-2">Manage Services</h5></div>
						</a>
					</div>
					<div class="col-md-4">
						<a href="../links/index.php" class="card text-decoration-none text-dark h-100">
							<div class="card-body"><i class="bi bi-link-45deg fs-3"></i><h5 class="mt-2">Generate Links</h5></div>
						</a>
					</div>
					<div class="col-md-4">
						<a href="../bookings/index.php" class="card text-decoration-none text-dark h-100">
							<div class="card-body"><i class="bi bi-journal-check fs-3"></i><h5 class="mt-2">View Bookings</h5></div>
						</a>
					</div>
				</div>
			</div>
			<?php include __DIR__ . '/../../common/footer_main.php'; ?>
		</div>
	</div>
	<?php include __DIR__ . '/../../common/footer.php'; ?>
</body>
</html>
