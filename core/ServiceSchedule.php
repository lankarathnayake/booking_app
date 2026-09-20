<?php
require_once __DIR__ . '/Sql.php';

class ServiceSchedule extends Sql {

	const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

	public function getForService($serviceId) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT * FROM service_weekly_slot WHERE service_id = :service_id ORDER BY weekday ASC, start_time ASC");
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getActiveForServiceAndWeekday($serviceId, $weekday) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT * FROM service_weekly_slot WHERE service_id = :service_id AND weekday = :weekday AND is_active = 1 ORDER BY start_time ASC");
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->bindParam(':weekday', $weekday, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function hasActiveSlotOnWeekday($serviceId, $weekday) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT 1 FROM service_weekly_slot WHERE service_id = :service_id AND weekday = :weekday AND is_active = 1 LIMIT 1");
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->bindParam(':weekday', $weekday, PDO::PARAM_INT);
		$stmt->execute();
		return (bool) $stmt->fetchColumn();
	}

	public function addSlot($serviceId, $weekday, $startTime, $endTime) {
		if ($startTime >= $endTime) {
			return ['success' => false, 'message' => 'Start time must be before end time.'];
		}
		$conn = $this->getConnection();
		try {
			$stmt = $conn->prepare(
				"INSERT INTO service_weekly_slot (service_id, weekday, start_time, end_time) VALUES (:service_id, :weekday, :start_time, :end_time)"
			);
			$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
			$stmt->bindParam(':weekday', $weekday, PDO::PARAM_INT);
			$stmt->bindParam(':start_time', $startTime, PDO::PARAM_STR);
			$stmt->bindParam(':end_time', $endTime, PDO::PARAM_STR);
			$stmt->execute();
			return ['success' => true, 'id' => $conn->lastInsertId()];
		} catch (PDOException $e) {
			if ($e->getCode() == 23000) {
				return ['success' => false, 'message' => 'That exact time slot already exists for this day.'];
			}
			throw $e;
		}
	}

	public function toggleActive($id, $isActive) {
		$conn = $this->getConnection();
		$isActive = (int) $isActive;
		$stmt = $conn->prepare("UPDATE service_weekly_slot SET is_active = :is_active WHERE id = :id");
		$stmt->bindParam(':is_active', $isActive, PDO::PARAM_INT);
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return ['success' => true];
	}

	public function delete($id) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("DELETE FROM service_weekly_slot WHERE id = :id");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return ['success' => true];
	}
}
