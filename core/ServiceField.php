<?php
require_once __DIR__ . '/Sql.php';

class ServiceField extends Sql {

	const TYPES = ['text', 'textarea', 'number', 'date', 'time', 'date_dropdown', 'time_dropdown', 'email', 'phone', 'select', 'radio', 'checkbox'];
	const TYPES_WITH_OPTIONS = ['select', 'radio', 'checkbox'];

	// Human-friendly labels for the admin field-type picker (falls back to ucfirst() if a type is missing here).
	const TYPE_LABELS = [
		'text' => 'Text',
		'textarea' => 'Textarea',
		'number' => 'Number',
		'date' => 'Date (native picker)',
		'time' => 'Time (native picker)',
		'date_dropdown' => 'Date (Year/Month/Day dropdowns)',
		'time_dropdown' => 'Time (Hour/Minute dropdowns)',
		'email' => 'Email',
		'phone' => 'Phone',
		'select' => 'Select (dropdown)',
		'radio' => 'Radio (choose one)',
		'checkbox' => 'Checkbox (choose multiple)',
	];

	// Which extra validation rules make sense per field type - options/select-type
	// fields aren't listed since they're already constrained to their option list.
	const RULE_TYPES_BY_FIELD_TYPE = [
		'text' => ['length', 'custom'],
		'textarea' => ['length', 'custom'],
		'number' => ['range', 'custom'],
		'date' => ['min_age', 'max_age', 'custom'],
		'date_dropdown' => ['min_age', 'max_age', 'custom'],
		'time' => ['custom'],
		'time_dropdown' => ['custom'],
		'email' => ['custom'],
		'phone' => ['custom'],
	];

	const RULE_TYPE_LABELS = [
		'none' => 'None',
		'length' => 'Length (min/max characters)',
		'range' => 'Value range (min/max number)',
		'min_age' => 'Minimum age (years, relative to today)',
		'max_age' => 'Maximum age (years, relative to today)',
		'custom' => 'Custom pattern (regex)',
	];

	public function getForService($serviceId, $activeOnly = false) {
		$conn = $this->getConnection();
		$sql = "SELECT * FROM service_field WHERE service_id = :service_id";
		if ($activeOnly) {
			$sql .= " AND status = 1";
		}
		$sql .= " ORDER BY sort_order ASC, id ASC";
		$stmt = $conn->prepare($sql);
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function find($id) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT * FROM service_field WHERE id = :id");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
	}

	public function slugifyKey($label) {
		$key = strtolower(trim($label));
		$key = preg_replace('/[^a-z0-9]+/', '_', $key);
		$key = trim($key, '_');
		return $key !== '' ? substr($key, 0, 64) : 'field';
	}

	private function uniqueKey($conn, $serviceId, $baseKey, $excludeId = null) {
		$key = $baseKey;
		$i = 2;
		while (true) {
			$sql = "SELECT id FROM service_field WHERE service_id = :service_id AND field_key = :key";
			if ($excludeId) {
				$sql .= " AND id != :exclude_id";
			}
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
			$stmt->bindParam(':key', $key, PDO::PARAM_STR);
			if ($excludeId) {
				$stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
			}
			$stmt->execute();
			if (!$stmt->fetch()) {
				return $key;
			}
			$key = $baseKey . '_' . $i;
			$i++;
		}
	}

	/**
	 * Builds the validation_json value from posted rule fields, scoped to
	 * what's allowed for $type. Returns ['success'=>true,'json'=>string|null]
	 * or ['success'=>false,'message'=>string] (e.g. an invalid regex).
	 */
	private function buildValidationJson($type, array $data) {
		$ruleType = $data['rule_type'] ?? 'none';
		$allowedRules = self::RULE_TYPES_BY_FIELD_TYPE[$type] ?? [];
		if ($ruleType === 'none' || !in_array($ruleType, $allowedRules, true)) {
			return ['success' => true, 'json' => null];
		}

		$message = trim($data['rule_message'] ?? '') ?: null;
		$rule = ['type' => $ruleType];

		switch ($ruleType) {
			case 'length':
				$min = $data['rule_length_min'] ?? '';
				$max = $data['rule_length_max'] ?? '';
				if ($min === '' && $max === '') return ['success' => true, 'json' => null];
				if ($min !== '') $rule['min'] = max(0, (int) $min);
				if ($max !== '') $rule['max'] = max(0, (int) $max);
				break;
			case 'range':
				$min = $data['rule_range_min'] ?? '';
				$max = $data['rule_range_max'] ?? '';
				if ($min === '' && $max === '') return ['success' => true, 'json' => null];
				if ($min !== '') $rule['min'] = (float) $min;
				if ($max !== '') $rule['max'] = (float) $max;
				break;
			case 'min_age':
			case 'max_age':
				$years = $data['rule_age_years'] ?? '';
				if ($years === '') return ['success' => true, 'json' => null];
				$rule['years'] = max(0, (int) $years);
				break;
			case 'custom':
				$pattern = trim($data['rule_pattern'] ?? '');
				if ($pattern === '') return ['success' => true, 'json' => null];
				if (@preg_match($pattern, '') === false) {
					return ['success' => false, 'message' => 'That custom pattern is not a valid regular expression.'];
				}
				$rule['pattern'] = $pattern;
				break;
			default:
				return ['success' => true, 'json' => null];
		}

		if ($message) $rule['message'] = $message;
		return ['success' => true, 'json' => json_encode($rule)];
	}

	/**
	 * Checks $value against a field's stored validation_json rule (on top of
	 * the baseline per-type checks the caller already ran). Returns null if
	 * valid, or an error message string if not. $value is expected to already
	 * be in the field's normalized form (e.g. 'YYYY-MM-DD' for date types).
	 */
	public static function checkValidationRule(array $fieldDef, $value) {
		$json = $fieldDef['validation_json'] ?? null;
		if (!$json) return null;
		$rule = json_decode($json, true);
		if (!is_array($rule) || empty($rule['type'])) return null;

		$label = $fieldDef['label'];
		$customMessage = $rule['message'] ?? null;

		switch ($rule['type']) {
			case 'length':
				$len = strlen((string) $value);
				if (isset($rule['min']) && $len < $rule['min']) {
					return $customMessage ?: "$label must be at least {$rule['min']} characters.";
				}
				if (isset($rule['max']) && $len > $rule['max']) {
					return $customMessage ?: "$label must be at most {$rule['max']} characters.";
				}
				return null;

			case 'range':
				if (!is_numeric($value)) return null; // baseline type check already covers this
				$num = (float) $value;
				if (isset($rule['min']) && $num < $rule['min']) {
					return $customMessage ?: "$label must be at least {$rule['min']}.";
				}
				if (isset($rule['max']) && $num > $rule['max']) {
					return $customMessage ?: "$label must be at most {$rule['max']}.";
				}
				return null;

			case 'min_age':
			case 'max_age':
				$date = DateTime::createFromFormat('Y-m-d', $value);
				if (!$date) return null; // baseline date check already covers this
				$age = (new DateTime())->diff($date)->y;
				if ($rule['type'] === 'min_age' && $age < $rule['years']) {
					return $customMessage ?: "$label must indicate an age of at least {$rule['years']} year(s).";
				}
				if ($rule['type'] === 'max_age' && $age > $rule['years']) {
					return $customMessage ?: "$label must indicate an age of at most {$rule['years']} year(s).";
				}
				return null;

			case 'custom':
				if (empty($rule['pattern'])) return null;
				if (@preg_match($rule['pattern'], (string) $value) !== 1) {
					return $customMessage ?: "$label is not in the correct format.";
				}
				return null;
		}

		return null;
	}

	private function parseOptions($optionsRaw) {
		$options = [];
		foreach (preg_split('/\r\n|\r|\n/', trim((string) $optionsRaw)) as $line) {
			$line = trim($line);
			if ($line === '') continue;
			$options[] = ['label' => $line, 'value' => $line];
		}
		return $options;
	}

	public function create($serviceId, array $data) {
		$label = trim($data['label'] ?? '');
		$type = $data['field_type'] ?? '';
		if ($label === '') {
			return ['success' => false, 'message' => 'Label is required.'];
		}
		if (!in_array($type, self::TYPES, true)) {
			return ['success' => false, 'message' => 'Invalid field type.'];
		}

		$validation = $this->buildValidationJson($type, $data);
		if (!$validation['success']) {
			return $validation;
		}

		$conn = $this->getConnection();
		$key = $this->uniqueKey($conn, $serviceId, $this->slugifyKey($label));

		$isRequired = !empty($data['is_required']) ? 1 : 0;
		$placeholder = trim($data['placeholder'] ?? '') ?: null;
		$helpText = trim($data['help_text'] ?? '') ?: null;
		$optionsJson = in_array($type, self::TYPES_WITH_OPTIONS, true)
			? json_encode($this->parseOptions($data['options'] ?? ''))
			: null;
		$validationJson = $validation['json'];

		$maxSort = (int) $conn->query("SELECT COALESCE(MAX(sort_order), 0) FROM service_field WHERE service_id = " . (int) $serviceId)->fetchColumn();

		$stmt = $conn->prepare(
			"INSERT INTO service_field (service_id, field_key, label, field_type, is_required, placeholder, help_text, options_json, validation_json, sort_order)
			 VALUES (:service_id, :key, :label, :type, :is_required, :placeholder, :help_text, :options_json, :validation_json, :sort_order)"
		);
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->bindParam(':key', $key, PDO::PARAM_STR);
		$stmt->bindParam(':label', $label, PDO::PARAM_STR);
		$stmt->bindParam(':type', $type, PDO::PARAM_STR);
		$stmt->bindParam(':is_required', $isRequired, PDO::PARAM_INT);
		$stmt->bindParam(':placeholder', $placeholder, PDO::PARAM_STR);
		$stmt->bindParam(':help_text', $helpText, PDO::PARAM_STR);
		$stmt->bindParam(':options_json', $optionsJson, PDO::PARAM_STR);
		$stmt->bindParam(':validation_json', $validationJson, PDO::PARAM_STR);
		$sortOrder = $maxSort + 1;
		$stmt->bindParam(':sort_order', $sortOrder, PDO::PARAM_INT);
		$stmt->execute();

		return ['success' => true, 'id' => $conn->lastInsertId()];
	}

	public function update($id, array $data) {
		$existing = $this->find($id);
		if (!$existing) {
			return ['success' => false, 'message' => 'Field not found.'];
		}
		$label = trim($data['label'] ?? '');
		if ($label === '') {
			return ['success' => false, 'message' => 'Label is required.'];
		}

		$validation = $this->buildValidationJson($existing['field_type'], $data);
		if (!$validation['success']) {
			return $validation;
		}

		$conn = $this->getConnection();
		$isRequired = !empty($data['is_required']) ? 1 : 0;
		$placeholder = trim($data['placeholder'] ?? '') ?: null;
		$helpText = trim($data['help_text'] ?? '') ?: null;
		$optionsJson = in_array($existing['field_type'], self::TYPES_WITH_OPTIONS, true)
			? json_encode($this->parseOptions($data['options'] ?? ''))
			: null;
		$validationJson = $validation['json'];

		$stmt = $conn->prepare(
			"UPDATE service_field SET label = :label, is_required = :is_required, placeholder = :placeholder,
			 help_text = :help_text, options_json = :options_json, validation_json = :validation_json WHERE id = :id"
		);
		$stmt->bindParam(':label', $label, PDO::PARAM_STR);
		$stmt->bindParam(':is_required', $isRequired, PDO::PARAM_INT);
		$stmt->bindParam(':placeholder', $placeholder, PDO::PARAM_STR);
		$stmt->bindParam(':help_text', $helpText, PDO::PARAM_STR);
		$stmt->bindParam(':options_json', $optionsJson, PDO::PARAM_STR);
		$stmt->bindParam(':validation_json', $validationJson, PDO::PARAM_STR);
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();

		return ['success' => true];
	}

	public function delete($id) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("DELETE FROM service_field WHERE id = :id");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return ['success' => true];
	}

	public function move($id, $direction) {
		$field = $this->find($id);
		if (!$field) return ['success' => false, 'message' => 'Field not found.'];

		$conn = $this->getConnection();
		$operator = $direction === 'up' ? '<' : '>';
		$order = $direction === 'up' ? 'DESC' : 'ASC';

		$stmt = $conn->prepare(
			"SELECT * FROM service_field WHERE service_id = :service_id AND sort_order $operator :sort_order ORDER BY sort_order $order LIMIT 1"
		);
		$stmt->bindParam(':service_id', $field['service_id'], PDO::PARAM_INT);
		$stmt->bindParam(':sort_order', $field['sort_order'], PDO::PARAM_INT);
		$stmt->execute();
		$neighbor = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$neighbor) {
			return ['success' => true]; // already at the edge, no-op
		}

		$swap = $conn->prepare("UPDATE service_field SET sort_order = :sort_order WHERE id = :id");
		$swap->bindParam(':sort_order', $neighbor['sort_order'], PDO::PARAM_INT);
		$swap->bindParam(':id', $field['id'], PDO::PARAM_INT);
		$swap->execute();

		$swap2 = $conn->prepare("UPDATE service_field SET sort_order = :sort_order WHERE id = :id");
		$swap2->bindParam(':sort_order', $field['sort_order'], PDO::PARAM_INT);
		$swap2->bindParam(':id', $neighbor['id'], PDO::PARAM_INT);
		$swap2->execute();

		return ['success' => true];
	}
}
