<?php
require_once __DIR__ . '/Sql.php';

class ServiceBlackout extends Sql {

	public function getForService($serviceId) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT * FROM service_blackout_date WHERE service_id = :service_id ORDER BY blackout_date ASC");
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getDatesInRange($serviceId, $fromDate, $toDate) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare(
			"SELECT blackout_date FROM service_blackout_date WHERE service_id = :service_id AND blackout_date BETWEEN :from_date AND :to_date"
		);
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->bindParam(':from_date', $fromDate, PDO::PARAM_STR);
		$stmt->bindParam(':to_date', $toDate, PDO::PARAM_STR);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_COLUMN);
	}

	public function isBlackedOut($serviceId, $date) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT 1 FROM service_blackout_date WHERE service_id = :service_id AND blackout_date = :date LIMIT 1");
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->bindParam(':date', $date, PDO::PARAM_STR);
		$stmt->execute();
		return (bool) $stmt->fetchColumn();
	}

	public function add($serviceId, $date, $reason) {
		$conn = $this->getConnection();
		try {
			$stmt = $conn->prepare("INSERT INTO service_blackout_date (service_id, blackout_date, reason) VALUES (:service_id, :date, :reason)");
			$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
			$stmt->bindParam(':date', $date, PDO::PARAM_STR);
			$stmt->bindParam(':reason', $reason, PDO::PARAM_STR);
			$stmt->execute();
			return ['success' => true, 'id' => $conn->lastInsertId()];
		} catch (PDOException $e) {
			if ($e->getCode() == 23000) {
				return ['success' => false, 'message' => 'That date is already blacked out.'];
			}
			throw $e;
		}
	}

	public function delete($id) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("DELETE FROM service_blackout_date WHERE id = :id");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return ['success' => true];
	}
}
