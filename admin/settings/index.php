<?php
require_once __DIR__ . '/../../common/bootstrap.php';
require_once __DIR__ . '/../../core/Settings.php';

$settingsModel = new Settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['operation'])) {
	header('Content-Type: application/json');
	if ($_POST['operation'] === 'save-settings') {
		$settingsModel->setMany([
			'business_name' => trim($_POST['business_name'] ?? ''),
			'business_phone' => trim($_POST['business_phone'] ?? ''),
			'business_email' => trim($_POST['business_email'] ?? ''),
			'business_logo_url' => trim($_POST['business_logo_url'] ?? ''),
			'default_booking_window_days' => (int) ($_POST['default_booking_window_days'] ?? 30),
			'default_lead_time_hours' => (int) ($_POST['default_lead_time_hours'] ?? 24),
			'email_subject_template' => trim($_POST['email_subject_template'] ?? ''),
			'email_body_template' => $_POST['email_body_template'] ?? '',
		]);
		echo json_encode(['success' => true]);
		exit;
	}
	echo json_encode(['success' => false, 'message' => 'Unknown operation.']);
	exit;
}

$settings = $settingsModel->getAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Settings - Booking App</title>
	<?php include __DIR__ . '/../../common/header.php'; ?>
</head>
<body>
	<div class="d-flex">
		<?php include __DIR__ . '/../../common/nav.php'; ?>
		<div class="main-content">
			<?php include __DIR__ . '/../../common/top_nav_bar.php'; ?>
			<div class="container-fluid p-4">
				<h4 class="mb-3">Settings</h4>

				<div id="settingsAlert" class="alert alert-success py-2 d-none">Saved.</div>
				<form id="settingsForm">
					<div class="card mb-3">
						<div class="card-header">Business Info</div>
						<div class="card-body">
							<div class="mb-3">
								<label class="form-label">Business Name</label>
								<input type="text" name="business_name" class="form-control" value="<?php echo h($settings['business_name'] ?? ''); ?>">
							</div>
							<div class="mb-3">
								<label class="form-label">Business Logo URL <span class="text-secondary">(shown at the top of every email; falls back to Business Name as text if blank)</span></label>
								<input type="text" name="business_logo_url" class="form-control" placeholder="https://..." value="<?php echo h($settings['business_logo_url'] ?? ''); ?>">
								<?php if (!empty($settings['business_logo_url'])) { ?>
									<img src="<?php echo h($settings['business_logo_url']); ?>" alt="Logo preview" class="mt-2" style="max-height:60px;">
								<?php } ?>
							</div>
							<div class="row">
								<div class="col-6 mb-3">
									<label class="form-label">Business Phone</label>
									<input type="text" name="business_phone" class="form-control" value="<?php echo h($settings['business_phone'] ?? ''); ?>">
								</div>
								<div class="col-6 mb-3">
									<label class="form-label">Business Email <span class="text-secondary">(default booking notifications)</span></label>
									<input type="email" name="business_email" class="form-control" value="<?php echo h($settings['business_email'] ?? ''); ?>">
								</div>
							</div>
							<div class="mb-3">
								<label class="form-label">Timezone <span class="text-secondary small">(read-only, set via config.local.php on this server)</span></label>
								<input type="text" class="form-control" value="<?php echo h(APP_TIMEZONE); ?>" disabled>
							</div>
							<div class="row">
								<div class="col-6 mb-3">
									<label class="form-label">Default Booking Window (days)</label>
									<input type="number" name="default_booking_window_days" class="form-control" min="1" value="<?php echo h($settings['default_booking_window_days'] ?? 30); ?>">
								</div>
								<div class="col-6 mb-3">
									<label class="form-label">Default Lead Time (hours)</label>
									<input type="number" name="default_lead_time_hours" class="form-control" min="0" value="<?php echo h($settings['default_lead_time_hours'] ?? 24); ?>">
								</div>
							</div>
						</div>
					</div>

					<div class="card mb-3">
						<div class="card-header">Booking Confirmation Email</div>
						<div class="card-body">
							<p class="text-secondary small">
								This one template is used for <strong>both</strong> the staff notification and the client's confirmation email - same subject, same body, sent to each recipient.
							</p>
							<div class="mb-3">
								<label class="form-label">Subject</label>
								<input type="text" name="email_subject_template" class="form-control" value="<?php echo h($settings['email_subject_template'] ?? ''); ?>">
							</div>
							<div class="mb-3">
								<label class="form-label">Body</label>
								<textarea name="email_body_template" id="email_body_template" class="form-control" rows="8"><?php echo h($settings['email_body_template'] ?? ''); ?></textarea>
							</div>
							<div class="small text-secondary">
								Available placeholders - used in either Subject or Body:
								<code>{{service_name}}</code>
								<code>{{appointment_date}}</code>
								<code>{{appointment_time}}</code>
								<code>{{booking_reference}}</code>
								<code>{{business_name}}</code>
								<code>{{submitted_fields}}</code> (auto-lists whatever the customer submitted for that service)
							</div>
						</div>
					</div>

					<button type="submit" class="btn btn-primary">Save</button>
				</form>

				<div class="card mt-3">
					<div class="card-header">SMTP <span class="text-secondary small">(read-only, edit via config.local.php on this server)</span></div>
					<div class="card-body">
						<p class="mb-1"><strong>Host:</strong> <?php echo h(SMTP_HOST ?: '(not configured)'); ?></p>
						<p class="mb-1"><strong>From:</strong> <?php echo h(SMTP_FROM_NAME); ?> &lt;<?php echo h(SMTP_FROM_EMAIL); ?>&gt;</p>
					</div>
				</div>
			</div>
			<?php include __DIR__ . '/../../common/footer_main.php'; ?>
		</div>
	</div>
	<?php include __DIR__ . '/../../common/footer.php'; ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
	<script>
		tinymce.init({
			selector: '#email_body_template',
			license_key: 'gpl',
			height: 450,
			menubar: true,
			// Every plugin in TinyMCE's free/open-source (GPL) bundle - no premium
			// (Tiny Cloud subscription) plugins. 'save' is deliberately excluded:
			// its default button submits the surrounding <form> directly, bypassing
			// our AJAX save handler below.
			plugins: 'advlist autolink autosave lists link image charmap preview anchor ' +
				'searchreplace visualblocks code fullscreen insertdatetime media table help ' +
				'wordcount emoticons codesample directionality nonbreaking pagebreak quickbars ' +
				'template visualchars importcss',
			toolbar: 'undo redo | blocks fontfamily fontsize | ' +
				'bold italic underline strikethrough forecolor backcolor | ' +
				'alignleft aligncenter alignright alignjustify ltr rtl | ' +
				'bullist numlist outdent indent | ' +
				'link anchor image media table charmap emoticons codesample insertdatetime | ' +
				'visualblocks visualchars nonbreaking pagebreak template | ' +
				'removeformat | code preview fullscreen | help',
			toolbar_mode: 'wrap',
			branding: false
		});

		$('#settingsForm').on('submit', function(e){
			e.preventDefault();
			tinymce.triggerSave(); // sync the WYSIWYG content back into the underlying textarea before serializing
			const data = $(this).serialize() + '&operation=save-settings';
			$.ajax({ type: 'POST', url: 'index.php', data: data, dataType: 'json',
				success: function(result){
					if(result.success){ $('#settingsAlert').removeClass('d-none'); setTimeout(() => $('#settingsAlert').addClass('d-none'), 2000); }
				} });
		});
	</script>
</body>
</html>
