<?php
require_once __DIR__ . '/Sql.php';

class Booking extends Sql {

	private function generateReference() {
		return 'BK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
	}

	/**
	 * Inserts the booking row. The occupancy_key unique index is the atomic
	 * double-booking guard - on a duplicate-key error this returns a
	 * friendly "slot just taken" failure instead of a raw DB error.
	 */
	public function create(PDO $conn, array $service, $linkId, $date, $time, $clientIp) {
		$reference = $this->generateReference();
		try {
			$stmt = $conn->prepare(
				"INSERT INTO booking (booking_reference, service_id, link_id, service_name_snapshot, service_duration_snapshot, appointment_date, appointment_time, client_ip)
				 VALUES (:reference, :service_id, :link_id, :service_name, :duration, :date, :time, :client_ip)"
			);
			$stmt->bindValue(':reference', $reference, PDO::PARAM_STR);
			$stmt->bindValue(':service_id', $service['id'], PDO::PARAM_INT);
			$stmt->bindValue(':link_id', $linkId, PDO::PARAM_INT);
			$stmt->bindValue(':service_name', $service['name'], PDO::PARAM_STR);
			$stmt->bindValue(':duration', $service['duration_minutes'], PDO::PARAM_INT);
			$stmt->bindValue(':date', $date, PDO::PARAM_STR);
			$stmt->bindValue(':time', $time, PDO::PARAM_STR);
			$stmt->bindValue(':client_ip', $clientIp, PDO::PARAM_STR);
			$stmt->execute();
			return ['success' => true, 'id' => $conn->lastInsertId(), 'reference' => $reference];
		} catch (PDOException $e) {
			if ($e->getCode() == 23000) {
				return ['success' => false, 'message' => 'That time slot was just taken. Please choose another.'];
			}
			throw $e;
		}
	}

	public function find($id) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT b.*, s.name AS current_service_name FROM booking b LEFT JOIN services s ON s.id = b.service_id WHERE b.id = :id AND b.deleted_at IS NULL");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
	}

	public function findByReference($reference) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT * FROM booking WHERE booking_reference = :reference AND deleted_at IS NULL");
		$stmt->bindParam(':reference', $reference, PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
	}

	public function search(array $filters, $page = 1, $perPage = 20) {
		$conn = $this->getConnection();
		$where = ["b.deleted_at IS NULL"];
		$params = [];

		if (!empty($filters['service_id'])) {
			$where[] = "b.service_id = :service_id";
			$params[':service_id'] = $filters['service_id'];
		}
		if (!empty($filters['status'])) {
			$where[] = "b.status = :status";
			$params[':status'] = $filters['status'];
		}
		if (!empty($filters['date_from'])) {
			$where[] = "b.appointment_date >= :date_from";
			$params[':date_from'] = $filters['date_from'];
		}
		if (!empty($filters['date_to'])) {
			$where[] = "b.appointment_date <= :date_to";
			$params[':date_to'] = $filters['date_to'];
		}

		$join = "";
		if (!empty($filters['field_query'])) {
			$join = "JOIN booking_field_value fv ON fv.booking_id = b.id";
			$where[] = "fv.value_text LIKE :field_query";
			$params[':field_query'] = '%' . $filters['field_query'] . '%';
			if (!empty($filters['field_key'])) {
				$where[] = "fv.field_key = :field_key";
				$params[':field_key'] = $filters['field_key'];
			}
		}

		$whereSql = implode(' AND ', $where);
		$offset = max(0, ($page - 1) * $perPage);

		$sql = "SELECT DISTINCT b.* FROM booking b $join WHERE $whereSql ORDER BY b.appointment_date DESC, b.appointment_time DESC LIMIT :limit OFFSET :offset";
		$stmt = $conn->prepare($sql);
		foreach ($params as $k => $v) {
			$stmt->bindValue($k, $v, PDO::PARAM_STR);
		}
		$stmt->bindValue(':limit', (int) $perPage, PDO::PARAM_INT);
		$stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$countSql = "SELECT COUNT(DISTINCT b.id) FROM booking b $join WHERE $whereSql";
		$countStmt = $conn->prepare($countSql);
		foreach ($params as $k => $v) {
			$countStmt->bindValue($k, $v, PDO::PARAM_STR);
		}
		$countStmt->execute();
		$total = (int) $countStmt->fetchColumn();

		return ['rows' => $rows, 'total' => $total];
	}

	public function cancel($id) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("UPDATE booking SET status = 'cancelled', cancelled_at = NOW() WHERE id = :id AND status != 'cancelled'");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return ['success' => $stmt->rowCount() === 1];
	}

	public function countUpcoming() {
		$conn = $this->getConnection();
		$stmt = $conn->query("SELECT COUNT(*) FROM booking WHERE status = 'confirmed' AND appointment_date >= CURDATE() AND deleted_at IS NULL");
		return (int) $stmt->fetchColumn();
	}

	public function countTotal() {
		$conn = $this->getConnection();
		$stmt = $conn->query("SELECT COUNT(*) FROM booking WHERE deleted_at IS NULL");
		return (int) $stmt->fetchColumn();
	}
}
