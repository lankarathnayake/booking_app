<?php
require_once __DIR__ . '/../../common/bootstrap.php';
require_once __DIR__ . '/../../core/Service.php';
require_once __DIR__ . '/../../core/ServiceField.php';

$serviceModel = new Service();
$fieldModel = new ServiceField();

$serviceId = (int) ($_GET['service_id'] ?? $_POST['service_id'] ?? 0);
$service = $serviceModel->find($serviceId);
if (!$service) {
	http_response_code(404);
	die('Service not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['operation'])) {
	header('Content-Type: application/json');
	$operation = $_POST['operation'];

	if ($operation === 'create-field') {
		echo json_encode($fieldModel->create($serviceId, $_POST));
		exit;
	}
	if ($operation === 'update-field') {
		echo json_encode($fieldModel->update((int) ($_POST['id'] ?? 0), $_POST));
		exit;
	}
	if ($operation === 'delete-field') {
		echo json_encode($fieldModel->delete((int) ($_POST['id'] ?? 0)));
		exit;
	}
	if ($operation === 'move-field') {
		echo json_encode($fieldModel->move((int) ($_POST['id'] ?? 0), $_POST['direction'] ?? 'up'));
		exit;
	}
	if ($operation === 'set-client-email-field') {
		echo json_encode($serviceModel->setClientEmailFieldKey($serviceId, $_POST['field_key'] ?? null));
		exit;
	}

	echo json_encode(['success' => false, 'message' => 'Unknown operation.']);
	exit;
}

$fields = $fieldModel->getForService($serviceId);
$typesWithOptions = ServiceField::TYPES_WITH_OPTIONS;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>Fields - <?php echo h($service['name']); ?></title>
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
					<h4 class="mb-0">Booking Fields - <?php echo h($service['name']); ?></h4>
					<button class="btn btn-primary" onclick="openCreateModal();"><i class="bi bi-plus-lg"></i> Add Field</button>
				</div>

				<div class="card">
					<div class="table-responsive">
						<table class="table mb-0 align-middle">
							<thead>
								<tr><th>#</th><th>Label</th><th>Key</th><th>Type</th><th>Required</th><th>Email?</th><th class="text-end">Actions</th></tr>
							</thead>
							<tbody>
								<?php if (!$fields) { ?>
									<tr><td colspan="7" class="text-secondary">No fields yet. Add the data you want to collect for this service.</td></tr>
								<?php } ?>
								<?php foreach ($fields as $i => $field) { ?>
									<tr>
										<td><?php echo $i + 1; ?></td>
										<td><?php echo h($field['label']); ?></td>
										<td><code><?php echo h($field['field_key']); ?></code></td>
										<td><span class="badge bg-secondary"><?php echo h($field['field_type']); ?></span></td>
										<td><?php echo (int) $field['is_required'] ? '<i class="bi bi-check-lg text-success"></i>' : ''; ?></td>
										<td>
											<?php if ($field['field_type'] === 'email') { ?>
												<?php if ($service['client_email_field_key'] === $field['field_key']) { ?>
													<span class="badge bg-info">Confirmation email</span>
												<?php } else { ?>
													<button class="btn btn-sm btn-link p-0" onclick="setClientEmailField('<?php echo h($field['field_key']); ?>');">Use for email</button>
												<?php } ?>
											<?php } ?>
										</td>
										<td class="text-end">
											<button class="btn btn-sm btn-outline-secondary" onclick="moveField(<?php echo (int) $field['id']; ?>, 'up');"><i class="bi bi-arrow-up"></i></button>
											<button class="btn btn-sm btn-outline-secondary" onclick="moveField(<?php echo (int) $field['id']; ?>, 'down');"><i class="bi bi-arrow-down"></i></button>
											<button class="btn btn-sm btn-outline-primary" onclick='openEditModal(<?php echo json_encode($field); ?>);'><i class="bi bi-pencil"></i></button>
											<button class="btn btn-sm btn-outline-danger" onclick="deleteField(<?php echo (int) $field['id']; ?>);"><i class="bi bi-trash"></i></button>
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

	<div class="modal fade" id="fieldModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<form id="fieldForm">
					<div class="modal-header">
						<h5 class="modal-title" id="fieldModalTitle">Add Field</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div id="fieldFormError" class="alert alert-danger py-2 d-none"></div>
						<input type="hidden" name="id" id="field_id">
						<div class="mb-3">
							<label class="form-label">Label</label>
							<input type="text" name="label" id="field_label" class="form-control" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Type</label>
							<select name="field_type" id="field_type" class="form-select" required>
								<?php foreach (ServiceField::TYPES as $type) { ?>
									<option value="<?php echo h($type); ?>"><?php echo h(ServiceField::TYPE_LABELS[$type] ?? ucfirst($type)); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="mb-3" id="optionsGroup">
							<label class="form-label">Options <span class="text-secondary">(one per line, for select/radio/checkbox)</span></label>
							<textarea name="options" id="field_options" class="form-control" rows="3"></textarea>
						</div>
						<div class="mb-3">
							<label class="form-label">Placeholder</label>
							<input type="text" name="placeholder" id="field_placeholder" class="form-control">
						</div>
						<div class="mb-3">
							<label class="form-label">Help Text</label>
							<input type="text" name="help_text" id="field_help_text" class="form-control">
						</div>
						<div class="form-check mb-3">
							<input type="checkbox" name="is_required" id="field_is_required" class="form-check-input" value="1">
							<label class="form-check-label" for="field_is_required">Required</label>
						</div>

						<hr>
						<div class="mb-3" id="ruleTypeGroup">
							<label class="form-label">Validation Rule <span class="text-secondary">(optional, on top of the field type's built-in checks)</span></label>
							<select name="rule_type" id="field_rule_type" class="form-select"></select>
						</div>
						<div class="mb-3 row" id="ruleLengthGroup" style="display:none;">
							<div class="col-6">
								<label class="form-label">Min length</label>
								<input type="number" name="rule_length_min" id="rule_length_min" class="form-control" min="0">
							</div>
							<div class="col-6">
								<label class="form-label">Max length</label>
								<input type="number" name="rule_length_max" id="rule_length_max" class="form-control" min="0">
							</div>
						</div>
						<div class="mb-3 row" id="ruleRangeGroup" style="display:none;">
							<div class="col-6">
								<label class="form-label">Min value</label>
								<input type="number" name="rule_range_min" id="rule_range_min" class="form-control" step="any">
							</div>
							<div class="col-6">
								<label class="form-label">Max value</label>
								<input type="number" name="rule_range_max" id="rule_range_max" class="form-control" step="any">
							</div>
						</div>
						<div class="mb-3" id="ruleAgeGroup" style="display:none;">
							<label class="form-label">Years</label>
							<input type="number" name="rule_age_years" id="rule_age_years" class="form-control" min="0">
						</div>
						<div class="mb-3" id="ruleCustomGroup" style="display:none;">
							<label class="form-label">Regex pattern</label>
							<input type="text" name="rule_pattern" id="rule_pattern" class="form-control" placeholder="/^[0-9]{10}$/">
							<div class="input-group mt-2">
								<input type="text" id="rule_pattern_test_value" class="form-control" placeholder="Try a sample value...">
								<span class="input-group-text" id="rule_pattern_test_result">-</span>
							</div>
							<div class="form-text">Client-side preview only (JavaScript regex) - the real check runs in PHP on submit and may differ slightly for advanced patterns.</div>
						</div>
						<div class="mb-3" id="ruleMessageGroup" style="display:none;">
							<label class="form-label">Custom error message <span class="text-secondary">(optional, shown instead of the default)</span></label>
							<input type="text" name="rule_message" id="rule_message" class="form-control">
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
		const typesWithOptions = <?php echo json_encode($typesWithOptions); ?>;
		const ruleTypesByFieldType = <?php echo json_encode(ServiceField::RULE_TYPES_BY_FIELD_TYPE); ?>;
		const ruleTypeLabels = <?php echo json_encode(ServiceField::RULE_TYPE_LABELS); ?>;
		const fieldModalEl = document.getElementById('fieldModal');
		const fieldModal = new bootstrap.Modal(fieldModalEl);

		function toggleOptionsVisibility(){
			const type = $('#field_type').val();
			$('#optionsGroup').toggle(typesWithOptions.includes(type));
		}

		function rebuildRuleTypeOptions(selected){
			const type = $('#field_type').val();
			const allowed = ruleTypesByFieldType[type] || [];
			const select = $('#field_rule_type').empty();
			select.append($('<option>').val('none').text(ruleTypeLabels['none']));
			allowed.forEach(function(rt){
				select.append($('<option>').val(rt).text(ruleTypeLabels[rt]));
			});
			$('#ruleTypeGroup').toggle(allowed.length > 0);
			select.val(allowed.includes(selected) ? selected : 'none');
			toggleRuleSubGroups();
		}

		function toggleRuleSubGroups(){
			const ruleType = $('#field_rule_type').val();
			$('#ruleLengthGroup').toggle(ruleType === 'length');
			$('#ruleRangeGroup').toggle(ruleType === 'range');
			$('#ruleAgeGroup').toggle(ruleType === 'min_age' || ruleType === 'max_age');
			$('#ruleCustomGroup').toggle(ruleType === 'custom');
			$('#ruleMessageGroup').toggle(!!ruleType && ruleType !== 'none');
		}

		$('#field_type').on('change', function(){
			toggleOptionsVisibility();
			rebuildRuleTypeOptions('none');
		});
		$('#field_rule_type').on('change', toggleRuleSubGroups);

		$('#rule_pattern, #rule_pattern_test_value').on('input', function(){
			const pattern = $('#rule_pattern').val();
			const value = $('#rule_pattern_test_value').val();
			const result = $('#rule_pattern_test_result');
			if(!pattern){ result.text('-').removeClass('text-bg-success text-bg-danger'); return; }
			try{
				const match = new RegExp(pattern.replace(/^\/|\/[a-z]*$/g, '')).test(value);
				result.text(match ? 'Match' : 'No match').toggleClass('text-bg-success', match).toggleClass('text-bg-danger', !match);
			}catch(e){
				result.text('Invalid pattern').removeClass('text-bg-success').addClass('text-bg-danger');
			}
		});

		function resetForm(){
			$('#fieldForm')[0].reset();
			$('#field_id').val('');
			$('#field_type').prop('disabled', false);
			$('#fieldFormError').addClass('d-none').text('');
			toggleOptionsVisibility();
			rebuildRuleTypeOptions('none');
		}

		function openCreateModal(){
			resetForm();
			$('#fieldModalTitle').text('Add Field');
			fieldModal.show();
		}

		function openEditModal(field){
			resetForm();
			$('#fieldModalTitle').text('Edit Field');
			$('#field_id').val(field.id);
			$('#field_label').val(field.label);
			$('#field_type').val(field.field_type).prop('disabled', true);
			$('#field_placeholder').val(field.placeholder);
			$('#field_help_text').val(field.help_text);
			$('#field_is_required').prop('checked', field.is_required == 1);
			if(field.options_json){
				const opts = JSON.parse(field.options_json);
				$('#field_options').val(opts.map(o => o.label).join('\n'));
			}
			toggleOptionsVisibility();

			let rule = null;
			if(field.validation_json){
				try{ rule = JSON.parse(field.validation_json); }catch(e){ rule = null; }
			}
			rebuildRuleTypeOptions(rule ? rule.type : 'none');
			if(rule){
				$('#rule_length_min').val(rule.min ?? '');
				$('#rule_length_max').val(rule.max ?? '');
				$('#rule_range_min').val(rule.min ?? '');
				$('#rule_range_max').val(rule.max ?? '');
				$('#rule_age_years').val(rule.years ?? '');
				$('#rule_pattern').val(rule.pattern ?? '');
				$('#rule_message').val(rule.message ?? '');
			}
			fieldModal.show();
		}

		$('#fieldForm').on('submit', function(e){
			e.preventDefault();
			const id = $('#field_id').val();
			const operation = id ? 'update-field' : 'create-field';
			const data = $(this).serialize() + '&operation=' + operation + '&service_id=<?php echo $serviceId; ?>';
			$.ajax({
				type: 'POST', url: 'fields.php', data: data, dataType: 'json',
				success: function(result){
					if(result.success){ location.reload(); }
					else { $('#fieldFormError').removeClass('d-none').text(result.message || 'Something went wrong.'); }
				},
				error: function(){ $('#fieldFormError').removeClass('d-none').text('Service temporarily unavailable.'); }
			});
		});

		function deleteField(id){
			if(!confirm('Delete this field? This does not affect past bookings, only new ones.')) return;
			$.ajax({ type: 'POST', url: 'fields.php', dataType: 'json',
				data: { operation: 'delete-field', id: id, service_id: <?php echo $serviceId; ?> },
				success: function(result){ if(result.success) location.reload(); } });
		}

		function moveField(id, direction){
			$.ajax({ type: 'POST', url: 'fields.php', dataType: 'json',
				data: { operation: 'move-field', id: id, direction: direction, service_id: <?php echo $serviceId; ?> },
				success: function(result){ if(result.success) location.reload(); } });
		}

		function setClientEmailField(fieldKey){
			$.ajax({ type: 'POST', url: 'fields.php', dataType: 'json',
				data: { operation: 'set-client-email-field', field_key: fieldKey, service_id: <?php echo $serviceId; ?> },
				success: function(result){ if(result.success) location.reload(); } });
		}
	</script>
</body>
</html>
