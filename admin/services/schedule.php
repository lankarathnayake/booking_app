<?php
require_once __DIR__ . '/../../common/bootstrap.php';
require_once __DIR__ . '/../../core/Service.php';
require_once __DIR__ . '/../../core/ServiceSchedule.php';

$serviceModel = new Service();
$scheduleModel = new ServiceSchedule();

$serviceId = (int) ($_GET['service_id'] ?? $_POST['service_id'] ?? 0);
$service = $serviceModel->find($serviceId);
if (!$service) {
	http_response_code(404);
	die('Service not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['operation'])) {
	header('Content-Type: application/json');
	$operation = $_POST['operation'];

	if ($operation === 'add-slot') {
		echo json_encode($scheduleModel->addSlot($serviceId, (int) ($_POST['weekday'] ?? 0), $_POST['start_time'] ?? '', $_POST['end_time'] ?? ''));
		exit;
	}
	if ($operation === 'toggle-slot') {
		echo json_encode($scheduleModel->toggleActive((int) ($_POST['id'] ?? 0), (int) ($_POST['is_active'] ?? 0)));
		exit;
	}
	if ($operation === 'delete-slot') {
		echo json_encode($scheduleModel->delete((int) ($_POST['id'] ?? 0)));
		exit;
	}

	echo json_encode(['success' => false, 'message' => 'Unknown operation.']);
	exit;
}

$slots = $scheduleModel->getForService($serviceId);
$byWeekday = array_fill(0, 7, []);
foreach ($slots as $slot) {
	$byWeekday[(int) $slot['weekday']][] = $slot;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Schedule - <?php echo h($service['name']); ?></title>
	<?php include __DIR__ . '/../../common/header.php'; ?>
</head>
<body>
	<div class="d-flex">
		<?php include __DIR__ . '/../../common/nav.php'; ?>
		<div class="main-content">
			<?php include __DIR__ . '/../../common/top_nav_bar.php'; ?>
			<div class="container-fluid p-4">
				<a href="index.php" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Back to Services</a>
				<div class="d-flex justify-content-between align-items-center my-3">
					<h4 class="mb-0">Weekly Schedule - <?php echo h($service['name']); ?></h4>
					<button class="btn btn-primary" onclick="openAddModal();"><i class="bi bi-plus-lg"></i> Add Time Slot</button>
				</div>
				<div class="alert alert-secondary small">
					<i class="bi bi-info-circle"></i>
					This is the <strong>default schedule</strong> for this service - what a slot like "Tuesday 10:00-10:30" here means is <em>every</em> Tuesday, indefinitely (this week, next month, next year), until you change it here.
					It applies to every week automatically <strong>unless</strong> that specific week has been customized on the
					<a href="week-schedule.php?service_id=<?php echo $serviceId; ?>">Weeks</a> page - a customized week fully replaces this default for its own dates, so editing this default schedule won't affect weeks you've already customized.
					Disabling or deleting a slot here only stops <em>future</em> bookings for that day/time - it does not cancel any appointment already booked.
				</div>

				<div class="row g-3">
					<?php foreach (ServiceSchedule::WEEKDAYS as $weekday => $label) { ?>
						<div class="col-md-6 col-lg-4">
							<div class="card h-100">
								<div class="card-header fw-bold"><?php echo h($label); ?></div>
								<ul class="list-group list-group-flush">
									<?php if (!$byWeekday[$weekday]) { ?>
										<li class="list-group-item text-secondary small">No slots</li>
									<?php } ?>
									<?php foreach ($byWeekday[$weekday] as $slot) { ?>
										<li class="list-group-item d-flex justify-content-between align-items-center">
											<span class="<?php echo (int) $slot['is_active'] ? '' : 'text-decoration-line-through text-secondary'; ?>">
												<?php echo h(substr($slot['start_time'], 0, 5)); ?> - <?php echo h(substr($slot['end_time'], 0, 5)); ?>
											</span>
											<span>
												<?php if ((int) $slot['is_active']) { ?>
													<button class="btn btn-sm btn-outline-warning" onclick="toggleSlot(<?php echo (int) $slot['id']; ?>, 0);">Disable</button>
												<?php } else { ?>
													<button class="btn btn-sm btn-outline-success" onclick="toggleSlot(<?php echo (int) $slot['id']; ?>, 1);">Enable</button>
												<?php } ?>
												<button class="btn btn-sm btn-outline-danger" onclick="deleteSlot(<?php echo (int) $slot['id']; ?>);"><i class="bi bi-trash"></i></button>
											</span>
										</li>
									<?php } ?>
								</ul>
							</div>
						</div>
					<?php } ?>
				</div>
			</div>
			<?php include __DIR__ . '/../../common/footer_main.php'; ?>
		</div>
	</div>

	<div class="modal fade" id="slotModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<form id="slotForm">
					<div class="modal-header">
						<h5 class="modal-title">Add Time Slot</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div id="slotFormError" class="alert alert-danger py-2 d-none"></div>
						<div class="mb-3">
							<label class="form-label">Day</label>
							<select name="weekday" class="form-select" required>
								<?php foreach (ServiceSchedule::WEEKDAYS as $weekday => $label) { ?>
									<option value="<?php echo $weekday; ?>"><?php echo h($label); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="row">
							<div class="col-6 mb-3">
								<label class="form-label">Start Time</label>
								<input type="time" name="start_time" class="form-control" required>
							</div>
							<div class="col-6 mb-3">
								<label class="form-label">End Time</label>
								<input type="time" name="end_time" class="form-control" required>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-primary">Add</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<?php include __DIR__ . '/../../common/footer.php'; ?>
	<script>
		const slotModal = new bootstrap.Modal(document.getElementById('slotModal'));
		function openAddModal(){
			$('#slotForm')[0].reset();
			$('#slotFormError').addClass('d-none').text('');
			slotModal.show();
		}
		$('#slotForm').on('submit', function(e){
			e.preventDefault();
			const data = $(this).serialize() + '&operation=add-slot&service_id=<?php echo $serviceId; ?>';
			$.ajax({ type: 'POST', url: 'schedule.php', data: data, dataType: 'json',
				success: function(result){
					if(result.success){ location.reload(); }
					else { $('#slotFormError').removeClass('d-none').text(result.message || 'Something went wrong.'); }
				} });
		});
		function toggleSlot(id, isActive){
			$.ajax({ type: 'POST', url: 'schedule.php', dataType: 'json',
				data: { operation: 'toggle-slot', id: id, is_active: isActive, service_id: <?php echo $serviceId; ?> },
				success: function(result){ if(result.success) location.reload(); } });
		}
		function deleteSlot(id){
			if(!confirm('Delete this time slot?')) return;
			$.ajax({ type: 'POST', url: 'schedule.php', dataType: 'json',
				data: { operation: 'delete-slot', id: id, service_id: <?php echo $serviceId; ?> },
				success: function(result){ if(result.success) location.reload(); } });
		}
	</script>
</body>
</html>
