<?php
require_once __DIR__ . '/../../common/bootstrap.php';
require_once __DIR__ . '/../../core/Service.php';
require_once __DIR__ . '/../../core/ServiceWeekOverride.php';
require_once __DIR__ . '/../../core/ServiceSchedule.php';

$serviceModel = new Service();
$weekOverrideModel = new ServiceWeekOverride();
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
	$weekStart = $_POST['week_start'] ?? '';

	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
		echo json_encode(['success' => false, 'message' => 'Invalid week.']);
		exit;
	}

	if ($operation === 'add-slot') {
		echo json_encode($weekOverrideModel->addSlot($serviceId, $weekStart, $_POST['date'] ?? '', $_POST['start_time'] ?? '', $_POST['end_time'] ?? ''));
		exit;
	}
	if ($operation === 'delete-slot') {
		echo json_encode($weekOverrideModel->deleteSlot((int) ($_POST['id'] ?? 0)));
		exit;
	}
	if ($operation === 'clear-week') {
		echo json_encode($weekOverrideModel->clearWeek($serviceId, $weekStart));
		exit;
	}
	if ($operation === 'restore-default') {
		echo json_encode($weekOverrideModel->restoreDefault($serviceId, $weekStart));
		exit;
	}

	echo json_encode(['success' => false, 'message' => 'Unknown operation.']);
	exit;
}

$month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
$weeks = ServiceWeekOverride::weeksForMonth($month);
foreach ($weeks as &$w) {
	$w['customized'] = $weekOverrideModel->isCustomized($serviceId, $w['start']);
}
unset($w);

$selectedWeekStart = $_GET['week'] ?? null;
$selectedWeek = null;
foreach ($weeks as $w) {
	if ($w['start'] === $selectedWeekStart) { $selectedWeek = $w; break; }
}

$prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));

$daysInWeek = [];
$weekIsCustomized = false;
if ($selectedWeek) {
	$weekIsCustomized = $weekOverrideModel->isCustomized($serviceId, $selectedWeek['start']);
	$cursor = DateTime::createFromFormat('Y-m-d', $selectedWeek['start']);
	$end = DateTime::createFromFormat('Y-m-d', $selectedWeek['end']);
	while ($cursor <= $end) {
		$dateStr = $cursor->format('Y-m-d');
		if ($weekIsCustomized) {
			$slots = $weekOverrideModel->getOverrideSlotsForDate($serviceId, $selectedWeek['start'], $dateStr);
		} else {
			// Preview only - these aren't real override rows until the week is first edited.
			$slots = $scheduleModel->getActiveForServiceAndWeekday($serviceId, (int) $cursor->format('w'));
		}
		$daysInWeek[] = ['date' => $dateStr, 'label' => $cursor->format('l, j M'), 'slots' => $slots, 'isPreview' => !$weekIsCustomized];
		$cursor->modify('+1 day');
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Week Schedule - <?php echo h($service['name']); ?></title>
	<?php include __DIR__ . '/../../common/header.php'; ?>
</head>
<body>
	<div class="d-flex">
		<?php include __DIR__ . '/../../common/nav.php'; ?>
		<div class="main-content">
			<?php include __DIR__ . '/../../common/top_nav_bar.php'; ?>
			<div class="container-fluid p-4">
				<a href="index.php" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Back to Services</a>
				<h4 class="my-3">Per-Week Schedule - <?php echo h($service['name']); ?></h4>
				<div class="alert alert-secondary small">
					<i class="bi bi-info-circle"></i>
					Use this page to make <strong>one specific week</strong> different from usual - e.g. "closed this Tuesday but open every other Tuesday."
					Weeks not touched here just follow the <a href="schedule.php?service_id=<?php echo $serviceId; ?>">default schedule</a> automatically (shown greyed out below as a preview).
					Adding or removing a single slot on a week forks it into its own independent copy - after that, changes to the default schedule no longer affect it.
					<strong>Clear Entire Week</strong> leaves a week deliberately bookable-free (not the same as "not yet customized"); <strong>Restore to Default</strong> discards the customization and goes back to following the default again.
				</div>

				<div class="d-flex align-items-center justify-content-between mb-3">
					<a class="btn btn-sm btn-outline-secondary" href="?service_id=<?php echo $serviceId; ?>&month=<?php echo $prevMonth; ?>"><i class="bi bi-chevron-left"></i> <?php echo date('F Y', strtotime($prevMonth . '-01')); ?></a>
					<h5 class="mb-0"><?php echo h(date('F Y', strtotime($month . '-01'))); ?></h5>
					<a class="btn btn-sm btn-outline-secondary" href="?service_id=<?php echo $serviceId; ?>&month=<?php echo $nextMonth; ?>"><?php echo date('F Y', strtotime($nextMonth . '-01')); ?> <i class="bi bi-chevron-right"></i></a>
				</div>

				<div class="d-flex flex-wrap gap-2 mb-4">
					<?php foreach ($weeks as $i => $w) { ?>
						<a href="?service_id=<?php echo $serviceId; ?>&month=<?php echo $month; ?>&week=<?php echo $w['start']; ?>"
							class="btn <?php echo ($selectedWeek && $selectedWeek['start'] === $w['start']) ? 'btn-primary' : ($w['customized'] ? 'btn-outline-info' : 'btn-outline-secondary'); ?>">
							Week <?php echo $i + 1; ?> (<?php echo h($w['label']); ?>)
							<?php echo $w['customized'] ? '<span class="badge bg-info ms-1">Custom</span>' : ''; ?>
						</a>
					<?php } ?>
				</div>

				<?php if ($selectedWeek) { ?>
					<div class="card">
						<div class="card-header d-flex justify-content-between align-items-center">
							<span>
								<?php echo h($selectedWeek['label']); ?>
								<?php if ($weekIsCustomized) { ?>
									<span class="badge bg-info ms-2">Custom schedule</span>
								<?php } else { ?>
									<span class="badge bg-secondary ms-2">Using default</span>
								<?php } ?>
							</span>
							<span>
								<button class="btn btn-sm btn-outline-danger" onclick="clearWeek();">Clear Entire Week</button>
								<?php if ($weekIsCustomized) { ?>
									<button class="btn btn-sm btn-outline-secondary" onclick="restoreDefault();">Restore to Default</button>
								<?php } ?>
							</span>
						</div>
						<div class="card-body">
							<?php if (!$weekIsCustomized) { ?>
								<div class="alert alert-secondary py-2 small">This week isn't customized yet - it's showing what the default weekly schedule would offer. Add or remove a slot below to fork this week into its own schedule.</div>
							<?php } ?>
							<div class="row g-3">
								<?php foreach ($daysInWeek as $day) { ?>
									<div class="col-md-6 col-lg-4">
										<div class="card h-100">
											<div class="card-header d-flex justify-content-between align-items-center">
												<?php echo h($day['label']); ?>
												<button class="btn btn-sm btn-outline-primary" onclick='openAddModal(<?php echo json_encode($day['date']); ?>);'><i class="bi bi-plus-lg"></i></button>
											</div>
											<ul class="list-group list-group-flush">
												<?php if (!$day['slots']) { ?>
													<li class="list-group-item text-secondary small"><?php echo $weekIsCustomized ? 'No slots (blank)' : 'No slots'; ?></li>
												<?php } ?>
												<?php foreach ($day['slots'] as $slot) { ?>
													<li class="list-group-item d-flex justify-content-between align-items-center <?php echo $day['isPreview'] ? 'text-secondary' : ''; ?>">
														<span>
															<?php echo h(substr($slot['start_time'], 0, 5)); ?> - <?php echo h(substr($slot['end_time'], 0, 5)); ?>
															<?php if ($day['isPreview']) { ?><span class="small">(default)</span><?php } ?>
														</span>
														<?php if (!$day['isPreview']) { ?>
															<button class="btn btn-sm btn-outline-danger" onclick="deleteSlot(<?php echo (int) $slot['id']; ?>);"><i class="bi bi-trash"></i></button>
														<?php } ?>
													</li>
												<?php } ?>
											</ul>
										</div>
									</div>
								<?php } ?>
							</div>
						</div>
					</div>
				<?php } else { ?>
					<div class="alert alert-secondary">Choose a week above to view or edit its schedule.</div>
				<?php } ?>
			</div>
			<?php include __DIR__ . '/../../common/footer_main.php'; ?>
		</div>
	</div>

	<div class="modal fade" id="addSlotModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<form id="addSlotForm">
					<div class="modal-header">
						<h5 class="modal-title">Add Time Slot - <span id="addSlotDateLabel"></span></h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div id="addSlotFormError" class="alert alert-danger py-2 d-none"></div>
						<input type="hidden" id="addSlotDate" name="date">
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
		const serviceId = <?php echo $serviceId; ?>;
		const weekStart = <?php echo json_encode($selectedWeek['start'] ?? null); ?>;

		const addSlotModal = new bootstrap.Modal(document.getElementById('addSlotModal'));
		function openAddModal(date){
			$('#addSlotForm')[0].reset();
			$('#addSlotFormError').addClass('d-none').text('');
			$('#addSlotDate').val(date);
			$('#addSlotDateLabel').text(date);
			addSlotModal.show();
		}
		$('#addSlotForm').on('submit', function(e){
			e.preventDefault();
			const data = $(this).serialize() + '&operation=add-slot&service_id=' + serviceId + '&week_start=' + weekStart;
			$.ajax({ type: 'POST', url: 'week-schedule.php', data: data, dataType: 'json',
				success: function(result){
					if(result.success){ location.reload(); }
					else { $('#addSlotFormError').removeClass('d-none').text(result.message || 'Something went wrong.'); }
				} });
		});

		function deleteSlot(id){
			if(!confirm('Delete this time slot?')) return;
			$.ajax({ type: 'POST', url: 'week-schedule.php', dataType: 'json',
				data: { operation: 'delete-slot', id: id, service_id: serviceId, week_start: weekStart },
				success: function(result){ if(result.success) location.reload(); } });
		}

		function clearWeek(){
			if(!confirm('Leave this week with no available time slots? Customers will not be able to book anything during this week until you add slots back or restore the default. Continue?')) return;
			$.ajax({ type: 'POST', url: 'week-schedule.php', dataType: 'json',
				data: { operation: 'clear-week', service_id: serviceId, week_start: weekStart },
				success: function(result){ if(result.success) location.reload(); } });
		}

		function restoreDefault(){
			if(!confirm('Discard this week\'s custom schedule and use the default weekly schedule again?')) return;
			$.ajax({ type: 'POST', url: 'week-schedule.php', dataType: 'json',
				data: { operation: 'restore-default', service_id: serviceId, week_start: weekStart },
				success: function(result){ if(result.success) location.reload(); } });
		}
	</script>
</body>
</html>
