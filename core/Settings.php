<?php
require_once __DIR__ . '/Sql.php';

class Settings extends Sql {

	public function getAll() {
		$conn = $this->getConnection();
		$stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$out = [];
		foreach ($rows as $row) {
			$out[$row['setting_key']] = $row['setting_value'];
		}
		return $out;
	}

	public function get($key, $default = null) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = :key");
		$stmt->bindParam(':key', $key, PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? $row['setting_value'] : $default;
	}

	public function set($key, $value) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare(
			"INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
			 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
		);
		$stmt->bindParam(':key', $key, PDO::PARAM_STR);
		$stmt->bindParam(':value', $value, PDO::PARAM_STR);
		return $stmt->execute();
	}

	public function setMany(array $pairs) {
		foreach ($pairs as $key => $value) {
			$this->set($key, $value);
		}
	}

	/**
	 * Replaces {{token}} placeholders in $template with values from $tokens.
	 * Unknown tokens are left as-is rather than silently deleted, so a typo
	 * in the admin's template is visible instead of vanishing.
	 */
	public static function renderTemplate($template, array $tokens) {
		$replacements = [];
		foreach ($tokens as $key => $value) {
			$replacements['{{' . $key . '}}'] = $value;
		}
		return strtr((string) $template, $replacements);
	}
}
