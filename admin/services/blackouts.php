<?php
require_once __DIR__ . '/../../common/bootstrap.php';
require_once __DIR__ . '/../../core/Service.php';
require_once __DIR__ . '/../../core/ServiceBlackout.php';

$serviceModel = new Service();
$blackoutModel = new ServiceBlackout();

$serviceId = (int) ($_GET['service_id'] ?? $_POST['service_id'] ?? 0);
$service = $serviceModel->find($serviceId);
if (!$service) {
	http_response_code(404);
	die('Service not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['operation'])) {
	header('Content-Type: application/json');
	$operation = $_POST['operation'];

	if ($operation === 'add-blackout') {
		echo json_encode($blackoutModel->add($serviceId, $_POST['blackout_date'] ?? '', trim($_POST['reason'] ?? '') ?: null));
		exit;
	}
	if ($operation === 'delete-blackout') {
		echo json_encode($blackoutModel->delete((int) ($_POST['id'] ?? 0)));
		exit;
	}

	echo json_encode(['success' => false, 'message' => 'Unknown operation.']);
	exit;
}

$blackouts = $blackoutModel->getForService($serviceId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Blackout Dates - <?php echo h($service['name']); ?></title>
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
					<h4 class="mb-0">Blackout Dates - <?php echo h($service['name']); ?></h4>
					<button class="btn btn-primary" onclick="openAddModal();"><i class="bi bi-plus-lg"></i> Add Blackout Date</button>
				</div>

				<div class="card">
					<div class="table-responsive">
						<table class="table mb-0 align-middle">
							<thead><tr><th>Date</th><th>Reason</th><th class="text-end">Actions</th></tr></thead>
							<tbody>
								<?php if (!$blackouts) { ?>
									<tr><td colspan="3" class="text-secondary">No blackout dates.</td></tr>
								<?php } ?>
								<?php foreach ($blackouts as $b) { ?>
									<tr>
										<td><?php echo h(date('l, j F Y', strtotime($b['blackout_date']))); ?></td>
										<td><?php echo h($b['reason']); ?></td>
										<td class="text-end">
											<button class="btn btn-sm btn-outline-danger" onclick="deleteBlackout(<?php echo (int) $b['id']; ?>);"><i class="bi bi-trash"></i></button>
										</td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<?php include __DIR__ . '/../../common/footer_main.php'; ?>
		</div>
	</div>

	<div class="modal fade" id="blackoutModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<form id="blackoutForm">
					<div class="modal-header">
						<h5 class="modal-title">Add Blackout Date</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div id="blackoutFormError" class="alert alert-danger py-2 d-none"></div>
						<div class="mb-3">
							<label class="form-label">Date</label>
							<input type="date" name="blackout_date" class="form-control" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Reason <span class="text-secondary">(optional)</span></label>
							<input type="text" name="reason" class="form-control">
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
		const blackoutModal = new bootstrap.Modal(document.getElementById('blackoutModal'));
		function openAddModal(){
			$('#blackoutForm')[0].reset();
			$('#blackoutFormError').addClass('d-none').text('');
			blackoutModal.show();
		}
		$('#blackoutForm').on('submit', function(e){
			e.preventDefault();
			const data = $(this).serialize() + '&operation=add-blackout&service_id=<?php echo $serviceId; ?>';
			$.ajax({ type: 'POST', url: 'blackouts.php', data: data, dataType: 'json',
				success: function(result){
					if(result.success){ location.reload(); }
					else { $('#blackoutFormError').removeClass('d-none').text(result.message || 'Something went wrong.'); }
				} });
		});
		function deleteBlackout(id){
			if(!confirm('Remove this blackout date?')) return;
			$.ajax({ type: 'POST', url: 'blackouts.php', dataType: 'json',
				data: { operation: 'delete-blackout', id: id, service_id: <?php echo $serviceId; ?> },
				success: function(result){ if(result.success) location.reload(); } });
		}
	</script>
</body>
</html>
