<?php
require_once __DIR__ . '/Sql.php';

class Service extends Sql {

	public function getAll($includeInactive = true) {
		$conn = $this->getConnection();
		$sql = "SELECT * FROM services WHERE deleted_at IS NULL";
		if (!$includeInactive) {
			$sql .= " AND status = 1";
		}
		$sql .= " ORDER BY sort_order ASC, name ASC";
		$stmt = $conn->query($sql);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function find($id) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT * FROM services WHERE id = :id AND deleted_at IS NULL");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
	}

	public function findBySlug($slug) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT * FROM services WHERE slug = :slug AND deleted_at IS NULL");
		$stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
	}

	public function slugify($name) {
		$slug = strtolower(trim($name));
		$slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
		$slug = trim($slug, '-');
		return $slug !== '' ? $slug : 'service';
	}

	private function uniqueSlug($conn, $baseSlug, $excludeId = null) {
		$slug = $baseSlug;
		$i = 2;
		while (true) {
			$sql = "SELECT id FROM services WHERE slug = :slug";
			if ($excludeId) {
				$sql .= " AND id != :exclude_id";
			}
			$stmt = $conn->prepare($sql);
			$stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
			if ($excludeId) {
				$stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
			}
			$stmt->execute();
			if (!$stmt->fetch()) {
				return $slug;
			}
			$slug = $baseSlug . '-' . $i;
			$i++;
		}
	}

	public function create(array $data) {
		$name = trim($data['name'] ?? '');
		if ($name === '') {
			return ['success' => false, 'message' => 'Name is required.'];
		}

		$conn = $this->getConnection();
		$slug = $this->uniqueSlug($conn, $this->slugify($name));

		$duration = max(1, (int) ($data['duration_minutes'] ?? 30));
		$description = trim($data['description'] ?? '') ?: null;
		$imageUrl = trim($data['image_url'] ?? '') ?: null;
		$notificationEmail = trim($data['notification_email'] ?? '') ?: null;
		$bookingWindowDays = ($data['booking_window_days'] ?? '') !== '' ? (int) $data['booking_window_days'] : null;
		$leadTimeHours = ($data['lead_time_hours'] ?? '') !== '' ? (int) $data['lead_time_hours'] : null;

		$stmt = $conn->prepare(
			"INSERT INTO services (name, slug, description, duration_minutes, image_url, notification_email, booking_window_days, lead_time_hours, status)
			 VALUES (:name, :slug, :description, :duration, :image_url, :notification_email, :booking_window_days, :lead_time_hours, 1)"
		);
		$stmt->bindParam(':name', $name, PDO::PARAM_STR);
		$stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
		$stmt->bindParam(':description', $description, PDO::PARAM_STR);
		$stmt->bindParam(':duration', $duration, PDO::PARAM_INT);
		$stmt->bindParam(':image_url', $imageUrl, PDO::PARAM_STR);
		$stmt->bindParam(':notification_email', $notificationEmail, PDO::PARAM_STR);
		$stmt->bindParam(':booking_window_days', $bookingWindowDays, PDO::PARAM_INT);
		$stmt->bindParam(':lead_time_hours', $leadTimeHours, PDO::PARAM_INT);
		$stmt->execute();

		return ['success' => true, 'id' => $conn->lastInsertId()];
	}

	public function update($id, array $data) {
		$name = trim($data['name'] ?? '');
		if ($name === '') {
			return ['success' => false, 'message' => 'Name is required.'];
		}

		$existing = $this->find($id);
		if (!$existing) {
			return ['success' => false, 'message' => 'Service not found.'];
		}

		$conn = $this->getConnection();

		$duration = max(1, (int) ($data['duration_minutes'] ?? 30));
		$description = trim($data['description'] ?? '') ?: null;
		$imageUrl = trim($data['image_url'] ?? '') ?: null;
		$notificationEmail = trim($data['notification_email'] ?? '') ?: null;
		$bookingWindowDays = ($data['booking_window_days'] ?? '') !== '' ? (int) $data['booking_window_days'] : null;
		$leadTimeHours = ($data['lead_time_hours'] ?? '') !== '' ? (int) $data['lead_time_hours'] : null;

		$stmt = $conn->prepare(
			"UPDATE services SET name = :name, description = :description, duration_minutes = :duration,
			 image_url = :image_url, notification_email = :notification_email,
			 booking_window_days = :booking_window_days, lead_time_hours = :lead_time_hours
			 WHERE id = :id"
		);
		$stmt->bindParam(':name', $name, PDO::PARAM_STR);
		$stmt->bindParam(':description', $description, PDO::PARAM_STR);
		$stmt->bindParam(':duration', $duration, PDO::PARAM_INT);
		$stmt->bindParam(':image_url', $imageUrl, PDO::PARAM_STR);
		$stmt->bindParam(':notification_email', $notificationEmail, PDO::PARAM_STR);
		$stmt->bindParam(':booking_window_days', $bookingWindowDays, PDO::PARAM_INT);
		$stmt->bindParam(':lead_time_hours', $leadTimeHours, PDO::PARAM_INT);
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();

		return ['success' => true];
	}

	public function setStatus($id, $status) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("UPDATE services SET status = :status WHERE id = :id");
		$status = (int) $status;
		$stmt->bindParam(':status', $status, PDO::PARAM_INT);
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return ['success' => true];
	}

	public function archive($id) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("UPDATE services SET deleted_at = NOW(), status = 0 WHERE id = :id");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return ['success' => true];
	}

	public function setClientEmailFieldKey($id, $fieldKey) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("UPDATE services SET client_email_field_key = :field_key WHERE id = :id");
		$stmt->bindParam(':field_key', $fieldKey, PDO::PARAM_STR);
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return ['success' => true];
	}

	public function count($includeInactive = true) {
		$conn = $this->getConnection();
		$sql = "SELECT COUNT(*) FROM services WHERE deleted_at IS NULL";
		if (!$includeInactive) {
			$sql .= " AND status = 1";
		}
		return (int) $conn->query($sql)->fetchColumn();
	}
}
