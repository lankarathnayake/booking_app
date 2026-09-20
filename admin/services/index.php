<?php
require_once __DIR__ . '/../../common/bootstrap.php';
require_once __DIR__ . '/../../core/Service.php';

$serviceModel = new Service();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['operation'])) {
	header('Content-Type: application/json');
	$operation = $_POST['operation'];

	if ($operation === 'create-service') {
		echo json_encode($serviceModel->create($_POST));
		exit;
	}
	if ($operation === 'update-service') {
		echo json_encode($serviceModel->update((int) ($_POST['id'] ?? 0), $_POST));
		exit;
	}
	if ($operation === 'set-status') {
		echo json_encode($serviceModel->setStatus((int) ($_POST['id'] ?? 0), (int) ($_POST['status'] ?? 0)));
		exit;
	}
	if ($operation === 'archive-service') {
		echo json_encode($serviceModel->archive((int) ($_POST['id'] ?? 0)));
		exit;
	}

	echo json_encode(['success' => false, 'message' => 'Unknown operation.']);
	exit;
}

$services = $serviceModel->getAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Services - Booking App</title>
	<?php include __DIR__ . '/../../common/header.php'; ?>
</head>
<body>
	<div class="d-flex">
		<?php include __DIR__ . '/../../common/nav.php'; ?>
		<div class="main-content">
			<?php include __DIR__ . '/../../common/top_nav_bar.php'; ?>
			<div class="container-fluid p-4">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h4 class="mb-0">Services</h4>
					<button class="btn btn-primary" onclick="openCreateModal();">
						<i class="bi bi-plus-lg"></i> Add Service
					</button>
				</div>

				<div class="row g-3">
					<?php if (!$services) { ?>
						<div class="col-12">
							<div class="card"><div class="card-body text-secondary">No services yet. Add your first one.</div></div>
						</div>
					<?php } ?>
					<?php foreach ($services as $service) { ?>
						<div class="col-md-4">
							<div class="card h-100">
								<div class="card-body">
									<div class="d-flex justify-content-between align-items-start">
										<h5 class="card-title mb-1"><?php echo h($service['name']); ?></h5>
										<?php if ((int) $service['status'] === 1) { ?>
											<span class="badge bg-success">Active</span>
										<?php } else { ?>
											<span class="badge bg-secondary">Inactive</span>
										<?php } ?>
									</div>
									<p class="text-secondary small mb-2">/<?php echo h($service['slug']); ?> &middot; <?php echo (int) $service['duration_minutes']; ?> min</p>
									<p class="card-text small"><?php echo nl2br(h($service['description'])); ?></p>
								</div>
								<div class="card-footer bg-white d-flex flex-wrap gap-2">
									<button class="btn btn-sm btn-outline-primary" onclick='openEditModal(<?php echo json_encode($service); ?>);'>
										<i class="bi bi-pencil"></i> Edit
									</button>
									<a class="btn btn-sm btn-outline-secondary" href="fields.php?service_id=<?php echo (int) $service['id']; ?>">
										<i class="bi bi-ui-checks-grid"></i> Fields
									</a>
									<a class="btn btn-sm btn-outline-secondary" href="schedule.php?service_id=<?php echo (int) $service['id']; ?>">
										<i class="bi bi-calendar-week"></i> Schedule
									</a>
									<a class="btn btn-sm btn-outline-secondary" href="week-schedule.php?service_id=<?php echo (int) $service['id']; ?>">
										<i class="bi bi-calendar3-range"></i> Weeks
									</a>
									<a class="btn btn-sm btn-outline-secondary" href="blackouts.php?service_id=<?php echo (int) $service['id']; ?>">
										<i class="bi bi-calendar-x"></i> Blackouts
									</a>
									<?php if ((int) $service['status'] === 1) { ?>
										<button class="btn btn-sm btn-outline-warning" onclick="setStatus(<?php echo (int) $service['id']; ?>, 0);">Deactivate</button>
									<?php } else { ?>
										<button class="btn btn-sm btn-outline-success" onclick="setStatus(<?php echo (int) $service['id']; ?>, 1);">Activate</button>
									<?php } ?>
									<button class="btn btn-sm btn-outline-danger" onclick="archiveService(<?php echo (int) $service['id']; ?>);">Archive</button>
								</div>
							</div>
						</div>
					<?php } ?>
				</div>
			</div>
			<?php include __DIR__ . '/../../common/footer_main.php'; ?>
		</div>
	</div>

	<!-- Add/Edit modal -->
	<div class="modal fade" id="serviceModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<form id="serviceForm">
					<div class="modal-header">
						<h5 class="modal-title" id="serviceModalTitle">Add Service</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div id="serviceFormError" class="alert alert-danger py-2 d-none"></div>
						<input type="hidden" name="id" id="service_id">
						<div class="mb-3">
							<label class="form-label">Name</label>
							<input type="text" name="name" id="service_name" class="form-control" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Description</label>
							<textarea name="description" id="service_description" class="form-control" rows="3"></textarea>
						</div>
						<div class="row">
							<div class="col-6 mb-3">
								<label class="form-label">Duration (minutes)</label>
								<input type="number" name="duration_minutes" id="service_duration_minutes" class="form-control" value="30" min="1" required>
							</div>
							<div class="col-6 mb-3">
								<label class="form-label">Image URL</label>
								<input type="text" name="image_url" id="service_image_url" class="form-control">
							</div>
						</div>
						<div class="mb-3">
							<label class="form-label">Notification Email <span class="text-secondary">(optional override)</span></label>
							<input type="email" name="notification_email" id="service_notification_email" class="form-control">
						</div>
						<div class="row">
							<div class="col-6 mb-3">
								<label class="form-label">Booking Window (days) <span class="text-secondary">(optional)</span></label>
								<input type="number" name="booking_window_days" id="service_booking_window_days" class="form-control" min="1">
							</div>
							<div class="col-6 mb-3">
								<label class="form-label">Lead Time (hours) <span class="text-secondary">(optional)</span></label>
								<input type="number" name="lead_time_hours" id="service_lead_time_hours" class="form-control" min="0">
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-primary">Save</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<?php include __DIR__ . '/../../common/footer.php'; ?>
	<script>
		const serviceModalEl = document.getElementById('serviceModal');
		const serviceModal = new bootstrap.Modal(serviceModalEl);

		function resetForm(){
			$('#serviceForm')[0].reset();
			$('#service_id').val('');
			$('#serviceFormError').addClass('d-none').text('');
		}

		function openCreateModal(){
			resetForm();
			$('#serviceModalTitle').text('Add Service');
			serviceModal.show();
		}

		function openEditModal(service){
			resetForm();
			$('#serviceModalTitle').text('Edit Service');
			$('#service_id').val(service.id);
			$('#service_name').val(service.name);
			$('#service_description').val(service.description);
			$('#service_duration_minutes').val(service.duration_minutes);
			$('#service_image_url').val(service.image_url);
			$('#service_notification_email').val(service.notification_email);
			$('#service_booking_window_days').val(service.booking_window_days);
			$('#service_lead_time_hours').val(service.lead_time_hours);
			serviceModal.show();
		}

		$('#serviceForm').on('submit', function(e){
			e.preventDefault();
			const id = $('#service_id').val();
			const operation = id ? 'update-service' : 'create-service';
			const data = $(this).serialize() + '&operation=' + operation;
			$.ajax({
				type: 'POST',
				url: 'index.php',
				data: data,
				dataType: 'json',
				success: function(result){
					if(result.success){
						location.reload();
					}else{
						$('#serviceFormError').removeClass('d-none').text(result.message || 'Something went wrong.');
					}
				},
				error: function(){
					$('#serviceFormError').removeClass('d-none').text('Service temporarily unavailable, please try again.');
				}
			});
		});

		function setStatus(id, status){
			$.ajax({
				type: 'POST',
				url: 'index.php',
				data: { operation: 'set-status', id: id, status: status },
				dataType: 'json',
				success: function(result){
					if(result.success){ location.reload(); }
				}
			});
		}

		function archiveService(id){
			if(!confirm('Archive this service? It will no longer be usable for new links, but past bookings/links stay intact.')) return;
			$.ajax({
				type: 'POST',
				url: 'index.php',
				data: { operation: 'archive-service', id: id },
				dataType: 'json',
				success: function(result){
					if(result.success){ location.reload(); }
				}
			});
		}
	</script>
</body>
</html>
