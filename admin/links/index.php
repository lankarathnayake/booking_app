<?php
require_once __DIR__ . '/../../common/bootstrap.php';
require_once __DIR__ . '/../../core/Service.php';
require_once __DIR__ . '/../../core/OneTimeLink.php';

$serviceModel = new Service();
$linkModel = new OneTimeLink();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['operation'])) {
	header('Content-Type: application/json');
	$operation = $_POST['operation'];

	if ($operation === 'generate-links') {
		$serviceId = (int) ($_POST['service_id'] ?? 0);
		if (!$serviceModel->find($serviceId)) {
			echo json_encode(['success' => false, 'message' => 'Choose a valid service.']);
			exit;
		}
		echo json_encode($linkModel->generate($serviceId, (int) ($_POST['count'] ?? 1), trim($_POST['note'] ?? '') ?: null, current_admin_id()));
		exit;
	}
	if ($operation === 'disable-link') {
		echo json_encode($linkModel->disable((int) ($_POST['id'] ?? 0)));
		exit;
	}

	echo json_encode(['success' => false, 'message' => 'Unknown operation.']);
	exit;
}

$services = $serviceModel->getAll(false);
$filterServiceId = (int) ($_GET['service_id'] ?? 0) ?: null;
$filterStatus = $_GET['status'] ?? null;
$links = $linkModel->getAll($filterServiceId, $filterStatus ?: null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>One-Time Links - Booking App</title>
	<?php include __DIR__ . '/../../common/header.php'; ?>
</head>
<body>
	<div class="d-flex">
		<?php include __DIR__ . '/../../common/nav.php'; ?>
		<div class="main-content">
			<?php include __DIR__ . '/../../common/top_nav_bar.php'; ?>
			<div class="container-fluid p-4">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h4 class="mb-0">One-Time Links</h4>
					<button class="btn btn-primary" onclick="openGenerateModal();" <?php echo $services ? '' : 'disabled'; ?>>
						<i class="bi bi-plus-lg"></i> Generate Links
					</button>
				</div>
				<?php if (!$services) { ?>
					<div class="alert alert-warning">Add an active service first before generating links.</div>
				<?php } ?>

				<form class="row g-2 mb-3" method="get">
					<div class="col-auto">
						<select name="service_id" class="form-select" onchange="this.form.submit()">
							<option value="">All services</option>
							<?php foreach ($services as $s) { ?>
								<option value="<?php echo (int) $s['id']; ?>" <?php echo $filterServiceId == $s['id'] ? 'selected' : ''; ?>><?php echo h($s['name']); ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="col-auto">
						<select name="status" class="form-select" onchange="this.form.submit()">
							<option value="">All statuses</option>
							<?php foreach (['unused', 'used', 'disabled'] as $st) { ?>
								<option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
							<?php } ?>
						</select>
					</div>
				</form>

				<div class="card">
					<div class="table-responsive">
						<table class="table mb-0 align-middle">
							<thead>
								<tr><th>Service</th><th>URL</th><th>Status</th><th>Note</th><th>Created</th><th>Used</th><th class="text-end">Actions</th></tr>
							</thead>
							<tbody>
								<?php if (!$links) { ?>
									<tr><td colspan="7" class="text-secondary">No links yet.</td></tr>
								<?php } ?>
								<?php foreach ($links as $link) {
									$url = rtrim(APP_URL, '/') . '/book/index.php?link=' . h($link['code']);
									$badge = ['unused' => 'success', 'used' => 'secondary', 'disabled' => 'danger'][$link['status']];
								?>
									<tr>
										<td><?php echo h($link['service_name']); ?></td>
										<td>
											<input type="text" class="form-control form-control-sm" style="width:280px;" readonly value="<?php echo $url; ?>" onclick="this.select();">
										</td>
										<td><span class="badge bg-<?php echo $badge; ?>"><?php echo h($link['status']); ?></span></td>
										<td><?php echo h($link['note']); ?></td>
										<td class="small"><?php echo h($link['created_at']); ?></td>
										<td class="small"><?php echo h($link['used_at']); ?></td>
										<td class="text-end">
											<button class="btn btn-sm btn-outline-secondary" onclick="copyLink(this);" data-url="<?php echo $url; ?>">Copy</button>
											<?php if ($link['status'] === 'unused') { ?>
												<button class="btn btn-sm btn-outline-danger" onclick="disableLink(<?php echo (int) $link['id']; ?>);">Disable</button>
											<?php } ?>
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

	<div class="modal fade" id="generateModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<form id="generateForm">
					<div class="modal-header">
						<h5 class="modal-title">Generate Links</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div id="generateFormError" class="alert alert-danger py-2 d-none"></div>
						<div class="mb-3">
							<label class="form-label">Service</label>
							<select name="service_id" class="form-select" required>
								<option value="">Choose a service...</option>
								<?php foreach ($services as $s) { ?>
									<option value="<?php echo (int) $s['id']; ?>"><?php echo h($s['name']); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="mb-3">
							<label class="form-label">How many links?</label>
							<input type="number" name="count" class="form-control" value="1" min="1" max="100" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Note <span class="text-secondary">(optional, e.g. recipient name)</span></label>
							<input type="text" name="note" class="form-control">
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-primary">Generate</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<?php include __DIR__ . '/../../common/footer.php'; ?>
	<script>
		const generateModal = new bootstrap.Modal(document.getElementById('generateModal'));
		function openGenerateModal(){
			$('#generateForm')[0].reset();
			$('#generateFormError').addClass('d-none').text('');
			generateModal.show();
		}
		$('#generateForm').on('submit', function(e){
			e.preventDefault();
			const data = $(this).serialize() + '&operation=generate-links';
			$.ajax({ type: 'POST', url: 'index.php', data: data, dataType: 'json',
				success: function(result){
					if(result.success){ location.reload(); }
					else { $('#generateFormError').removeClass('d-none').text(result.message || 'Something went wrong.'); }
				} });
		});
		function disableLink(id){
			if(!confirm('Disable this link? It can no longer be used to make a booking.')) return;
			$.ajax({ type: 'POST', url: 'index.php', dataType: 'json',
				data: { operation: 'disable-link', id: id },
				success: function(result){ if(result.success) location.reload(); } });
		}
		function copyLink(btn){
			navigator.clipboard.writeText($(btn).data('url')).then(function(){
				$(btn).text('Copied!');
				setTimeout(function(){ $(btn).text('Copy'); }, 1500);
			});
		}
	</script>
</body>
</html>
