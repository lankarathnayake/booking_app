<?php
require_once __DIR__ . '/Sql.php';

class OneTimeLink extends Sql {

	public function getByCode($code) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT * FROM one_time_link WHERE code = :code");
		$stmt->bindParam(':code', $code, PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
	}

	public function getAll($serviceId = null, $status = null) {
		$conn = $this->getConnection();
		$sql = "SELECT l.*, s.name AS service_name FROM one_time_link l JOIN services s ON s.id = l.service_id WHERE 1=1";
		$params = [];
		if ($serviceId) {
			$sql .= " AND l.service_id = :service_id";
			$params[':service_id'] = $serviceId;
		}
		if ($status) {
			$sql .= " AND l.status = :status";
			$params[':status'] = $status;
		}
		$sql .= " ORDER BY l.created_at DESC LIMIT 200";
		$stmt = $conn->prepare($sql);
		foreach ($params as $k => $v) {
			$stmt->bindValue($k, $v, PDO::PARAM_STR);
		}
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	private function generateCode() {
		return bin2hex(random_bytes(8)); // 16 hex chars
	}

	public function generate($serviceId, $count, $note, $createdBy) {
		$count = max(1, min(100, (int) $count));
		$conn = $this->getConnection();
		$codes = [];

		for ($i = 0; $i < $count; $i++) {
			$inserted = false;
			$attempts = 0;
			while (!$inserted && $attempts < 5) {
				$attempts++;
				$code = $this->generateCode();
				try {
					$stmt = $conn->prepare(
						"INSERT INTO one_time_link (service_id, code, note, created_by) VALUES (:service_id, :code, :note, :created_by)"
					);
					$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
					$stmt->bindParam(':code', $code, PDO::PARAM_STR);
					$stmt->bindParam(':note', $note, PDO::PARAM_STR);
					$stmt->bindParam(':created_by', $createdBy, PDO::PARAM_INT);
					$stmt->execute();
					$codes[] = $code;
					$inserted = true;
				} catch (PDOException $e) {
					if ($e->getCode() != 23000) {
						throw $e;
					}
					// duplicate code, retry
				}
			}
		}

		return ['success' => true, 'codes' => $codes];
	}

	public function disable($id) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("UPDATE one_time_link SET status = 'disabled' WHERE id = :id AND status = 'unused'");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return ['success' => $stmt->rowCount() === 1];
	}

	/**
	 * Atomically flips an unused link to used, tying it to the given booking.
	 * Returns true only if this call is the one that made the transition
	 * (guards against a race between two simultaneous submissions).
	 */
	public function markUsed(PDO $conn, $linkId, $bookingId) {
		$stmt = $conn->prepare("UPDATE one_time_link SET status = 'used', used_at = NOW(), booking_id = :booking_id WHERE id = :id AND status = 'unused'");
		$stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
		$stmt->bindParam(':id', $linkId, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->rowCount() === 1;
	}

	public function countByStatus($status) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT COUNT(*) FROM one_time_link WHERE status = :status");
		$stmt->bindParam(':status', $status, PDO::PARAM_STR);
		$stmt->execute();
		return (int) $stmt->fetchColumn();
	}
}
