<?php
require_once __DIR__ . '/../../common/bootstrap.php';
require_once __DIR__ . '/../../core/Booking.php';
require_once __DIR__ . '/../../core/BookingFieldValue.php';

$bookingModel = new Booking();
$fieldValueModel = new BookingFieldValue();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$booking = $bookingModel->find($id);
if (!$booking) {
	http_response_code(404);
	die('Booking not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['operation'])) {
	header('Content-Type: application/json');
	if ($_POST['operation'] === 'cancel-booking') {
		echo json_encode($bookingModel->cancel($id));
		exit;
	}
	echo json_encode(['success' => false, 'message' => 'Unknown operation.']);
	exit;
}

$values = $fieldValueModel->getForBooking($id);
$badge = ['confirmed' => 'success', 'cancelled' => 'danger', 'completed' => 'secondary'][$booking['status']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Booking <?php echo h($booking['booking_reference']); ?> - Booking App</title>
	<?php include __DIR__ . '/../../common/header.php'; ?>
</head>
<body>
	<div class="d-flex">
		<?php include __DIR__ . '/../../common/nav.php'; ?>
		<div class="main-content">
			<?php include __DIR__ . '/../../common/top_nav_bar.php'; ?>
			<div class="container-fluid p-4">
				<a href="index.php" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Back to Bookings</a>

				<div class="card mt-3">
					<div class="card-body">
						<div class="d-flex justify-content-between align-items-start">
							<div>
								<h4><?php echo h($booking['booking_reference']); ?></h4>
								<p class="text-secondary mb-0"><?php echo h($booking['service_name_snapshot']); ?></p>
							</div>
							<span class="badge bg-<?php echo $badge; ?> fs-6"><?php echo h($booking['status']); ?></span>
						</div>
						<hr>
						<div class="row mb-3">
							<div class="col-md-4"><strong>Date:</strong> <?php echo h(date('l, j F Y', strtotime($booking['appointment_date']))); ?></div>
							<div class="col-md-4"><strong>Time:</strong> <?php echo h(date('g:i A', strtotime($booking['appointment_time']))); ?></div>
							<div class="col-md-4"><strong>Duration:</strong> <?php echo (int) $booking['service_duration_snapshot']; ?> min</div>
						</div>

						<h6 class="text-uppercase text-secondary small">Submitted Details</h6>
						<?php if (!$values) { ?>
							<p class="text-secondary">No additional details submitted.</p>
						<?php } ?>
						<?php foreach ($values as $v) { ?>
							<p class="mb-2"><strong><?php echo h($v['field_label_snapshot']); ?>:</strong> <?php echo nl2br(h($v['value_text'])); ?></p>
						<?php } ?>

						<?php if ($booking['status'] === 'confirmed') { ?>
							<hr>
							<button class="btn btn-outline-danger" onclick="cancelBooking();">Cancel Booking</button>
						<?php } ?>
					</div>
				</div>
			</div>
			<?php include __DIR__ . '/../../common/footer_main.php'; ?>
		</div>
	</div>
	<?php include __DIR__ . '/../../common/footer.php'; ?>
	<script>
		function cancelBooking(){
			if(!confirm('Cancel this booking? This frees the time slot for a new booking.')) return;
			$.ajax({ type: 'POST', url: 'view.php', dataType: 'json',
				data: { operation: 'cancel-booking', id: <?php echo (int) $id; ?> },
				success: function(result){ if(result.success) location.reload(); } });
		}
	</script>
</body>
</html>
