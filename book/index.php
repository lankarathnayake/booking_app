<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/OneTimeLink.php';
require_once __DIR__ . '/../core/Service.php';
require_once __DIR__ . '/../core/ServiceField.php';
require_once __DIR__ . '/../core/Availability.php';
require_once __DIR__ . '/../core/Booking.php';
require_once __DIR__ . '/../core/BookingFieldValue.php';
require_once __DIR__ . '/../core/EmailService.php';
require_once __DIR__ . '/../core/Settings.php';

function h($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function render_select($name, $required, $options, $selected = '') {
	$html = '<select class="form-select" name="' . h($name) . '" ' . ($required ? 'required' : '') . '><option value="">--</option>';
	foreach ($options as $value => $label) {
		$html .= '<option value="' . h($value) . '"' . ((string) $selected === (string) $value ? ' selected' : '') . '>' . h($label) . '</option>';
	}
	return $html . '</select>';
}

$linkModel = new OneTimeLink();
$serviceModel = new Service();
$fieldModel = new ServiceField();
$availability = new Availability();

// ---------------------------------------------------------------------
// AJAX operations
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['operation'])) {
	header('Content-Type: application/json');
	$operation = $_POST['operation'];
	$code = trim($_POST['link'] ?? '');

	$link = $code !== '' ? $linkModel->getByCode($code) : null;
	if (!$link || $link['status'] !== 'unused') {
		echo json_encode(['success' => false, 'message' => 'This link is invalid or has already been used.']);
		exit;
	}
	$service = $serviceModel->find($link['service_id']);
	if (!$service || (int) $service['status'] !== 1) {
		echo json_encode(['success' => false, 'message' => 'This service is currently unavailable.']);
		exit;
	}

	if ($operation === 'get-available-dates') {
		$month = preg_match('/^\d{4}-\d{2}$/', $_POST['month'] ?? '') ? $_POST['month'] : date('Y-m');
		$dates = $availability->getAvailableDatesForMonth($service, $month);
		echo json_encode(['success' => true, 'dates' => $dates]);
		exit;
	}

	if ($operation === 'get-time-slots') {
		$date = $_POST['date'] ?? '';
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			echo json_encode(['success' => false, 'message' => 'Invalid date.']);
			exit;
		}
		$slots = $availability->getAvailableSlots($service, $date);
		if (!$slots) {
			echo json_encode(['success' => true, 'slots' => [], 'message' => 'No time slots available on this date. Please choose another date.']);
			exit;
		}
		echo json_encode(['success' => true, 'slots' => $slots]);
		exit;
	}

	if ($operation === 'submit-booking') {
		$date = $_POST['appointment_date'] ?? '';
		$time = $_POST['appointment_time'] ?? '';
		$postedFields = $_POST['fields'] ?? [];

		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
			echo json_encode(['success' => false, 'message' => 'Please choose a valid date and time.']);
			exit;
		}
		if (strlen($time) === 5) $time .= ':00';

		// Re-validate the chosen slot is genuinely open (never trust client state).
		$openSlots = $availability->getAvailableSlots($service, $date);
		$slotOpen = false;
		foreach ($openSlots as $slot) {
			if ($slot['start_time'] === $time) { $slotOpen = true; break; }
		}
		if (!$slotOpen) {
			echo json_encode(['success' => false, 'message' => 'That time slot is no longer available. Please choose another.']);
			exit;
		}

		// Re-validate every dynamic field server-side.
		$fieldDefs = $fieldModel->getForService($service['id'], true);
		$errors = [];
		$valueRows = [];
		$clientEmail = null;

		foreach ($fieldDefs as $def) {
			$key = $def['field_key'];
			$raw = $postedFields[$key] ?? null;
			$options = $def['options_json'] ? json_decode($def['options_json'], true) : [];
			$allowedValues = array_column($options, 'value');

			if ($def['field_type'] === 'checkbox') {
				$values = is_array($raw) ? array_map('strval', $raw) : [];
				if ($def['is_required'] && !$values) {
					$errors[$key] = $def['label'] . ' is required.';
					continue;
				}
				foreach ($values as $v) {
					if (!in_array($v, $allowedValues, true)) {
						$errors[$key] = 'Invalid value for ' . $def['label'] . '.';
						break;
					}
				}
				if (isset($errors[$key])) continue;
				$valueText = implode(', ', $values);
			} elseif ($def['field_type'] === 'date_dropdown') {
				$day = isset($raw['day']) && $raw['day'] !== '' ? (int) $raw['day'] : null;
				$month = isset($raw['month']) && $raw['month'] !== '' ? (int) $raw['month'] : null;
				$year = isset($raw['year']) && $raw['year'] !== '' ? (int) $raw['year'] : null;
				$anyFilled = $day || $month || $year;
				$allFilled = $day && $month && $year;

				if (!$anyFilled) {
					if ($def['is_required']) {
						$errors[$key] = $def['label'] . ' is required.';
						continue;
					}
					$valueText = '';
				} elseif (!$allFilled) {
					$errors[$key] = 'Please select a complete date for ' . $def['label'] . '.';
					continue;
				} elseif (!checkdate($month, $day, $year)) {
					$errors[$key] = 'Please select a valid date for ' . $def['label'] . '.';
					continue;
				} else {
					$valueText = sprintf('%04d-%02d-%02d', $year, $month, $day);
				}
			} elseif ($def['field_type'] === 'time_dropdown') {
				$hour = isset($raw['hour']) && $raw['hour'] !== '' ? (int) $raw['hour'] : null;
				$minute = isset($raw['minute']) && $raw['minute'] !== '' ? (int) $raw['minute'] : null;
				$anyFilled = $hour !== null || $minute !== null;
				$bothFilled = $hour !== null && $minute !== null;

				if (!$anyFilled) {
					if ($def['is_required']) {
						$errors[$key] = $def['label'] . ' is required.';
						continue;
					}
					$valueText = '';
				} elseif (!$bothFilled) {
					$errors[$key] = 'Please select a complete time for ' . $def['label'] . '.';
					continue;
				} elseif ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
					$errors[$key] = 'Please select a valid time for ' . $def['label'] . '.';
					continue;
				} else {
					$valueText = sprintf('%02d:%02d', $hour, $minute);
				}
			} else {
				$value = is_string($raw) ? trim($raw) : '';
				if ($def['is_required'] && $value === '') {
					$errors[$key] = $def['label'] . ' is required.';
					continue;
				}
				if ($value !== '') {
					switch ($def['field_type']) {
						case 'email':
							if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
								$errors[$key] = 'Please enter a valid email for ' . $def['label'] . '.';
							}
							break;
						case 'phone':
							if (!preg_match('/^\+?[0-9\s-]{7,15}$/', $value)) {
								$errors[$key] = 'Please enter a valid phone number for ' . $def['label'] . '.';
							}
							break;
						case 'number':
							if (!is_numeric($value)) {
								$errors[$key] = $def['label'] . ' must be a number.';
							}
							break;
						case 'date':
							if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
								$errors[$key] = $def['label'] . ' must be a valid date.';
							}
							break;
						case 'time':
							if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
								$errors[$key] = $def['label'] . ' must be a valid time.';
							}
							break;
						case 'select':
						case 'radio':
							if (!in_array($value, $allowedValues, true)) {
								$errors[$key] = 'Invalid value for ' . $def['label'] . '.';
							}
							break;
					}
				}
				if (isset($errors[$key])) continue;
				$valueText = $value;
			}

			if ($valueText !== '') {
				$ruleError = ServiceField::checkValidationRule($def, $valueText);
				if ($ruleError) {
					$errors[$key] = $ruleError;
					continue;
				}
			}

			$valueRows[] = [
				'field_key' => $key,
				'label' => $def['label'],
				'type' => $def['field_type'],
				'value' => $valueText,
				'sort_order' => $def['sort_order'],
			];

			if ($service['client_email_field_key'] === $key && $valueText !== '') {
				$clientEmail = $valueText;
			}
		}

		if (!$clientEmail) {
			foreach ($valueRows as $row) {
				if ($row['type'] === 'email' && $row['value'] !== '') { $clientEmail = $row['value']; break; }
			}
		}

		if ($errors) {
			echo json_encode(['success' => false, 'message' => 'Please fix the errors below.', 'errors' => $errors]);
			exit;
		}

		$bookingModel = new Booking();
		$fieldValueModel = new BookingFieldValue();
		$conn = (new Sql())->getConnection();

		$conn->beginTransaction();
		try {
			$result = $bookingModel->create($conn, $service, $link['id'], $date, $time, $_SERVER['REMOTE_ADDR'] ?? null);
			if (!$result['success']) {
				$conn->rollBack();
				echo json_encode($result);
				exit;
			}

			$linkOk = $linkModel->markUsed($conn, $link['id'], $result['id']);
			if (!$linkOk) {
				$conn->rollBack();
				echo json_encode(['success' => false, 'message' => 'This link was already used.']);
				exit;
			}

			$fieldValueModel->insertMany($conn, $result['id'], $valueRows);
			$conn->commit();
		} catch (Exception $e) {
			$conn->rollBack();
			error_log('Booking submit failed: ' . $e->getMessage());
			echo json_encode(['success' => false, 'message' => 'Something went wrong, please try again.']);
			exit;
		}

		// Emails are best-effort - a failure here must never undo the booking above.
		try {
			$emailService = new EmailService();
			$settings = new Settings();
			$dateLabel = date('l, j F Y', strtotime($date));
			$timeLabel = date('g:i A', strtotime($time));

			$fieldsHtml = '';
			foreach ($valueRows as $row) {
				$fieldsHtml .= '<p style="margin:0 0 8px 0;"><strong>' . h($row['label']) . ':</strong> ' . nl2br(h($row['value'])) . '</p>';
			}

			// Same subject/body template for both the staff notification and the
			// client confirmation - editable from Settings, not hardcoded here.
			$tokens = [
				'service_name' => h($service['name']),
				'appointment_date' => h($dateLabel),
				'appointment_time' => h($timeLabel),
				'booking_reference' => h($result['reference']),
				'business_name' => h($settings->get('business_name', '')),
				'submitted_fields' => $fieldsHtml,
			];
			$subjectTemplate = $settings->get('email_subject_template', 'Booking Confirmed: {{service_name}}');
			$bodyTemplate = $settings->get('email_body_template', '{{submitted_fields}}');
			$renderedSubject = Settings::renderTemplate($subjectTemplate, $tokens);
			$renderedBody = Settings::renderTemplate($bodyTemplate, $tokens);

			$adminRecipient = $service['notification_email'] ?: $settings->get('business_email');
			if ($adminRecipient) {
				$emailService->send($adminRecipient, 'Admin', $renderedSubject, $renderedBody);
			}

			if ($clientEmail) {
				$emailService->send($clientEmail, '', $renderedSubject, $renderedBody);
			}
		} catch (Exception $e) {
			error_log('Booking confirmation email failed: ' . $e->getMessage());
		}

		echo json_encode(['success' => true, 'reference' => $result['reference']]);
		exit;
	}

	echo json_encode(['success' => false, 'message' => 'Unknown operation.']);
	exit;
}

// ---------------------------------------------------------------------
// GET: render the page
// ---------------------------------------------------------------------
$code = trim($_GET['link'] ?? '');
$link = $code !== '' ? $linkModel->getByCode($code) : null;
$service = null;
$fields = [];
$pageError = null;

if (!$link) {
	$pageError = 'This link is invalid. Please check the URL or contact us for a new one.';
} elseif ($link['status'] === 'used') {
	$pageError = 'This link has already been used to make a booking.';
} elseif ($link['status'] === 'disabled') {
	$pageError = 'This link is no longer active. Please contact us for a new one.';
} else {
	$service = $serviceModel->find($link['service_id']);
	if (!$service || (int) $service['status'] !== 1) {
		$pageError = 'This service is currently unavailable.';
	} else {
		$fields = $fieldModel->getForService($service['id'], true);
	}
}

$bounds = $service ? $availability->getBookingBounds($service) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo $service ? h($service['name']) : 'Book an Appointment'; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<style>
		body { background: #f4f6f9; }
		.booking-card { max-width: 640px; margin: 40px auto; }
		.date-chip { cursor: pointer; }
		.date-chip.selected { background: #2c7ea4 !important; color: #fff !important; }
		.slot-btn.selected { background: #2c7ea4 !important; color: #fff !important; border-color: #2c7ea4 !important; }
	</style>
</head>
<body>
	<div class="container">
		<div class="card booking-card shadow-sm">
			<div class="card-body p-4">
				<?php if ($pageError) { ?>
					<div class="alert alert-warning"><?php echo h($pageError); ?></div>
				<?php } else { ?>
					<h3 class="mb-1"><?php echo h($service['name']); ?></h3>
					<p class="text-secondary"><?php echo nl2br(h($service['description'])); ?></p>
					<p class="text-secondary small"><i class="bi bi-clock"></i> <?php echo (int) $service['duration_minutes']; ?> minutes</p>

					<div id="bookingAlert" class="alert alert-danger d-none"></div>
					<div id="successPanel" class="d-none">
						<div class="alert alert-success">
							<h5><i class="bi bi-check-circle"></i> Booking Confirmed</h5>
							<p class="mb-0">Your reference: <strong id="successReference"></strong></p>
						</div>
					</div>

					<form id="bookingForm" novalidate>
						<input type="hidden" name="link" value="<?php echo h($code); ?>">
						<input type="hidden" name="appointment_time" id="appointment_time" value="">

						<div class="mb-3">
							<label class="form-label fw-bold">1. Choose a date</label>
							<input type="date" id="appointment_date" name="appointment_date" class="form-control"
								min="<?php echo $bounds['min']->format('Y-m-d'); ?>"
								max="<?php echo $bounds['max']->format('Y-m-d'); ?>" required>
							<div id="dateHint" class="form-text"></div>
						</div>

						<div class="mb-3" id="timeSlotSection" style="display:none;">
							<label class="form-label fw-bold">2. Choose a time</label>
							<div id="timeSlots" class="d-flex flex-wrap gap-2"></div>
						</div>

						<hr>
						<label class="form-label fw-bold">3. Your details</label>
						<?php foreach ($fields as $field) {
							$options = $field['options_json'] ? json_decode($field['options_json'], true) : [];
							$required = (int) $field['is_required'] === 1;
							echo '<div class="mb-3" data-field-wrap="' . h($field['field_key']) . '">';
							echo '<label class="form-label">' . h($field['label']) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>';
							$namePrefix = "fields[" . h($field['field_key']) . "]";
							switch ($field['field_type']) {
								case 'textarea':
									echo '<textarea class="form-control" name="' . $namePrefix . '" placeholder="' . h($field['placeholder']) . '" ' . ($required ? 'required' : '') . '></textarea>';
									break;
								case 'select':
									echo '<select class="form-select" name="' . $namePrefix . '" ' . ($required ? 'required' : '') . '><option value="">Choose...</option>';
									foreach ($options as $opt) {
										echo '<option value="' . h($opt['value']) . '">' . h($opt['label']) . '</option>';
									}
									echo '</select>';
									break;
								case 'radio':
									foreach ($options as $i => $opt) {
										$id = h($field['field_key']) . '_' . $i;
										echo '<div class="form-check"><input class="form-check-input" type="radio" name="' . $namePrefix . '" id="' . $id . '" value="' . h($opt['value']) . '" ' . ($required ? 'required' : '') . '>';
										echo '<label class="form-check-label" for="' . $id . '">' . h($opt['label']) . '</label></div>';
									}
									break;
								case 'checkbox':
									foreach ($options as $i => $opt) {
										$id = h($field['field_key']) . '_' . $i;
										echo '<div class="form-check"><input class="form-check-input" type="checkbox" name="' . $namePrefix . '[]" id="' . $id . '" value="' . h($opt['value']) . '">';
										echo '<label class="form-check-label" for="' . $id . '">' . h($opt['label']) . '</label></div>';
									}
									break;
								case 'number':
								case 'date':
								case 'time':
								case 'email':
									$type = $field['field_type'];
									echo '<input type="' . $type . '" class="form-control" name="' . $namePrefix . '" placeholder="' . h($field['placeholder']) . '" ' . ($required ? 'required' : '') . '>';
									break;
								case 'phone':
									echo '<input type="tel" class="form-control" name="' . $namePrefix . '" placeholder="' . h($field['placeholder']) . '" ' . ($required ? 'required' : '') . '>';
									break;
								case 'date_dropdown':
									$currentYear = (int) date('Y');
									$years = [];
									for ($y = $currentYear; $y >= $currentYear - 120; $y--) { $years[$y] = $y; }
									$months = [
										1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
										7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
									];
									$days = [];
									for ($d = 1; $d <= 31; $d++) { $days[$d] = $d; }
									echo '<div class="row g-2">';
									echo '<div class="col-4">' . render_select($namePrefix . '[day]', $required, $days) . '</div>';
									echo '<div class="col-4">' . render_select($namePrefix . '[month]', $required, $months) . '</div>';
									echo '<div class="col-4">' . render_select($namePrefix . '[year]', $required, $years) . '</div>';
									echo '</div>';
									break;
								case 'time_dropdown':
									$hours = [];
									for ($hh = 0; $hh <= 23; $hh++) { $hours[$hh] = str_pad($hh, 2, '0', STR_PAD_LEFT); }
									$minutes = [];
									for ($mm = 0; $mm <= 59; $mm++) { $minutes[$mm] = str_pad($mm, 2, '0', STR_PAD_LEFT); }
									echo '<div class="row g-2">';
									echo '<div class="col-6">' . render_select($namePrefix . '[hour]', $required, $hours) . '</div>';
									echo '<div class="col-6">' . render_select($namePrefix . '[minute]', $required, $minutes) . '</div>';
									echo '</div>';
									break;
								default:
									echo '<input type="text" class="form-control" name="' . $namePrefix . '" placeholder="' . h($field['placeholder']) . '" ' . ($required ? 'required' : '') . '>';
							}
							if ($field['help_text']) {
								echo '<div class="form-text">' . h($field['help_text']) . '</div>';
							}
							echo '<div class="text-danger small mt-1 d-none" data-field-error="' . h($field['field_key']) . '"></div>';
							echo '</div>';
						} ?>

						<button type="submit" class="btn btn-primary w-100" id="submitBtn" disabled>Confirm Booking</button>
					</form>
				<?php } ?>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<?php if (!$pageError) { ?>
	<script>
		const linkCode = <?php echo json_encode($code); ?>;

		$('#appointment_date').on('change', function(){
			const date = $(this).val();
			$('#appointment_time').val('');
			$('#submitBtn').prop('disabled', true);
			$('#timeSlotSection').hide();
			$('#dateHint').text('Loading available times...');

			$.ajax({
				type: 'POST', url: 'index.php', dataType: 'json',
				data: { operation: 'get-time-slots', link: linkCode, date: date },
				success: function(result){
					$('#dateHint').text('');
					if(!result.success){
						$('#dateHint').text(result.message || 'Unable to load times.');
						return;
					}
					if(!result.slots.length){
						$('#dateHint').text(result.message || 'No times available on this date.');
						return;
					}
					const container = $('#timeSlots').empty();
					result.slots.forEach(function(slot){
						const label = formatTime(slot.start_time) + ' - ' + formatTime(slot.end_time);
						const btn = $('<button type="button" class="btn btn-outline-primary btn-sm slot-btn"></button>').text(label).data('start', slot.start_time);
						btn.on('click', function(){
							$('.slot-btn').removeClass('selected');
							$(this).addClass('selected');
							$('#appointment_time').val($(this).data('start'));
							$('#submitBtn').prop('disabled', false);
						});
						container.append(btn);
					});
					$('#timeSlotSection').show();
				},
				error: function(){
					$('#dateHint').text('Service temporarily unavailable, please try again.');
				}
			});
		});

		function formatTime(t){
			const parts = t.split(':');
			let h = parseInt(parts[0], 10);
			const m = parts[1];
			const ampm = h >= 12 ? 'PM' : 'AM';
			h = h % 12; if(h === 0) h = 12;
			return h + ':' + m + ' ' + ampm;
		}

		function clearFieldErrors(){
			$('[data-field-error]').addClass('d-none').text('');
			$('.is-invalid').removeClass('is-invalid');
		}

		function showFieldErrors(errors){
			$.each(errors, function(key, message){
				$('[data-field-error="' + key + '"]').removeClass('d-none').text(message);
				$('[data-field-wrap="' + key + '"] .form-control, [data-field-wrap="' + key + '"] .form-select, [data-field-wrap="' + key + '"] .form-check-input').addClass('is-invalid');
			});
			const firstKey = Object.keys(errors)[0];
			if(firstKey){
				const wrap = $('[data-field-wrap="' + firstKey + '"]');
				if(wrap.length){
					$('html,body').animate({ scrollTop: wrap.offset().top - 20 }, 200);
				}
			}
		}

		$('#bookingForm').on('submit', function(e){
			e.preventDefault();
			$('#bookingAlert').addClass('d-none').text('');
			clearFieldErrors();

			const data = $(this).serialize() + '&operation=submit-booking';
			$('#submitBtn').prop('disabled', true).text('Submitting...');

			$.ajax({
				type: 'POST', url: 'index.php', dataType: 'json', data: data,
				success: function(result){
					if(result.success){
						$('#bookingForm').addClass('d-none');
						$('#successReference').text(result.reference);
						$('#successPanel').removeClass('d-none');
					}else{
						$('#bookingAlert').removeClass('d-none').text(result.message || 'Please check the form and try again.');
						if(result.errors){
							showFieldErrors(result.errors);
						}
						$('#submitBtn').prop('disabled', false).text('Confirm Booking');
					}
				},
				error: function(){
					$('#bookingAlert').removeClass('d-none').text('Service temporarily unavailable, please try again.');
					$('#submitBtn').prop('disabled', false).text('Confirm Booking');
				}
			});
		});
	</script>
	<?php } ?>
</body>
</html>
