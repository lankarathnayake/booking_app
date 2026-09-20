<?php
require_once __DIR__ . '/Sql.php';
require_once __DIR__ . '/ServiceSchedule.php';

/**
 * Per-week schedule overrides. A week is identified by the date its
 * day-chunk starts on within the month (1, 8, 15, 22, 29). If no
 * service_week_override row exists for a (service, week_start_date), that
 * week inherits service_weekly_slot (the recurring default) as-is. Once a
 * week_override row exists, that week is fully self-contained - its slots
 * (possibly zero, i.e. deliberately blank) are authoritative and the
 * default template is ignored for every date in that week.
 */
class ServiceWeekOverride extends Sql {

	/**
	 * The date (Y-m-d) that starts the 7-(or-fewer)-day chunk containing $date.
	 */
	public static function weekStartFor($date) {
		$d = DateTime::createFromFormat('Y-m-d', $date);
		$day = (int) $d->format('j');
		$chunkStartDay = intdiv($day - 1, 7) * 7 + 1;
		return $d->format('Y-m') . '-' . str_pad($chunkStartDay, 2, '0', STR_PAD_LEFT);
	}

	/**
	 * @return array of ['start' => 'Y-m-d', 'end' => 'Y-m-d', 'label' => '...'] for the given Y-m month.
	 */
	public static function weeksForMonth($yearMonth) {
		$monthStart = DateTime::createFromFormat('Y-m-d', $yearMonth . '-01');
		$monthEnd = (clone $monthStart)->modify('last day of this month');
		$lastDay = (int) $monthEnd->format('j');

		$weeks = [];
		for ($chunkStartDay = 1; $chunkStartDay <= $lastDay; $chunkStartDay += 7) {
			$chunkEndDay = min($chunkStartDay + 6, $lastDay);
			$start = $yearMonth . '-' . str_pad($chunkStartDay, 2, '0', STR_PAD_LEFT);
			$end = $yearMonth . '-' . str_pad($chunkEndDay, 2, '0', STR_PAD_LEFT);
			$weeks[] = [
				'start' => $start,
				'end' => $end,
				'label' => date('j M', strtotime($start)) . ' - ' . date('j M', strtotime($end)),
			];
		}
		return $weeks;
	}

	public function getOverride($serviceId, $weekStartDate) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT * FROM service_week_override WHERE service_id = :service_id AND week_start_date = :week_start_date");
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->bindParam(':week_start_date', $weekStartDate, PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
	}

	public function isCustomized($serviceId, $weekStartDate) {
		return $this->getOverride($serviceId, $weekStartDate) !== null;
	}

	public function getOverrideSlots($serviceId, $weekStartDate) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare(
			"SELECT s.* FROM service_week_override_slot s
			 JOIN service_week_override o ON o.id = s.week_override_id
			 WHERE o.service_id = :service_id AND o.week_start_date = :week_start_date
			 ORDER BY s.override_date ASC, s.start_time ASC"
		);
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->bindParam(':week_start_date', $weekStartDate, PDO::PARAM_STR);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getOverrideSlotsForDate($serviceId, $weekStartDate, $date) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare(
			"SELECT s.* FROM service_week_override_slot s
			 JOIN service_week_override o ON o.id = s.week_override_id
			 WHERE o.service_id = :service_id AND o.week_start_date = :week_start_date AND s.override_date = :date
			 ORDER BY s.start_time ASC"
		);
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->bindParam(':week_start_date', $weekStartDate, PDO::PARAM_STR);
		$stmt->bindParam(':date', $date, PDO::PARAM_STR);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Get-or-create the marker row for a week. On first creation, seeds it
	 * by copying the default weekly template into concrete dated slots for
	 * every day in that week - so editing "forks" a copy rather than
	 * starting from a blank slate.
	 */
	private function ensureOverride(PDO $conn, $serviceId, $weekStartDate) {
		$stmt = $conn->prepare("SELECT id FROM service_week_override WHERE service_id = :service_id AND week_start_date = :week_start_date");
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->bindParam(':week_start_date', $weekStartDate, PDO::PARAM_STR);
		$stmt->execute();
		$existing = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($existing) {
			return $existing['id'];
		}

		$insert = $conn->prepare("INSERT INTO service_week_override (service_id, week_start_date) VALUES (:service_id, :week_start_date)");
		$insert->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$insert->bindParam(':week_start_date', $weekStartDate, PDO::PARAM_STR);
		$insert->execute();
		$overrideId = $conn->lastInsertId();

		// Seed from the default weekly template.
		$schedule = new ServiceSchedule();
		$start = DateTime::createFromFormat('Y-m-d', $weekStartDate);
		$monthEnd = (clone $start)->modify('last day of this month');
		$lastChunkDay = min((int) $start->format('j') + 6, (int) $monthEnd->format('j'));

		$insertSlot = $conn->prepare(
			"INSERT IGNORE INTO service_week_override_slot (week_override_id, override_date, start_time, end_time) VALUES (:override_id, :date, :start_time, :end_time)"
		);
		$cursor = clone $start;
		while ((int) $cursor->format('j') <= $lastChunkDay) {
			$weekday = (int) $cursor->format('w');
			$templateSlots = $schedule->getActiveForServiceAndWeekday($serviceId, $weekday);
			$dateStr = $cursor->format('Y-m-d');
			foreach ($templateSlots as $slot) {
				$insertSlot->bindValue(':override_id', $overrideId, PDO::PARAM_INT);
				$insertSlot->bindValue(':date', $dateStr, PDO::PARAM_STR);
				$insertSlot->bindValue(':start_time', $slot['start_time'], PDO::PARAM_STR);
				$insertSlot->bindValue(':end_time', $slot['end_time'], PDO::PARAM_STR);
				$insertSlot->execute();
			}
			$cursor->modify('+1 day');
		}

		return $overrideId;
	}

	public function addSlot($serviceId, $weekStartDate, $date, $startTime, $endTime) {
		if ($startTime >= $endTime) {
			return ['success' => false, 'message' => 'Start time must be before end time.'];
		}
		$conn = $this->getConnection();
		$conn->beginTransaction();
		try {
			$overrideId = $this->ensureOverride($conn, $serviceId, $weekStartDate);
			$stmt = $conn->prepare(
				"INSERT INTO service_week_override_slot (week_override_id, override_date, start_time, end_time) VALUES (:override_id, :date, :start_time, :end_time)"
			);
			$stmt->bindParam(':override_id', $overrideId, PDO::PARAM_INT);
			$stmt->bindParam(':date', $date, PDO::PARAM_STR);
			$stmt->bindParam(':start_time', $startTime, PDO::PARAM_STR);
			$stmt->bindParam(':end_time', $endTime, PDO::PARAM_STR);
			$stmt->execute();
			$newId = $conn->lastInsertId(); // must read before commit() - COMMIT itself clears the tracked insert id
			$conn->commit();
			return ['success' => true, 'id' => $newId];
		} catch (PDOException $e) {
			$conn->rollBack();
			if ($e->getCode() == 23000) {
				return ['success' => false, 'message' => 'That exact time slot already exists on this date.'];
			}
			throw $e;
		}
	}

	public function deleteSlot($id) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("DELETE FROM service_week_override_slot WHERE id = :id");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		return ['success' => true];
	}

	/**
	 * Marks the week customized (forking from default if not already) and
	 * removes every slot, leaving it deliberately blank.
	 */
	public function clearWeek($serviceId, $weekStartDate) {
		$conn = $this->getConnection();
		$conn->beginTransaction();
		try {
			$overrideId = $this->ensureOverride($conn, $serviceId, $weekStartDate);
			$stmt = $conn->prepare("DELETE FROM service_week_override_slot WHERE week_override_id = :override_id");
			$stmt->bindParam(':override_id', $overrideId, PDO::PARAM_INT);
			$stmt->execute();
			$conn->commit();
			return ['success' => true];
		} catch (Exception $e) {
			$conn->rollBack();
			throw $e;
		}
	}

	public function restoreDefault($serviceId, $weekStartDate) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("DELETE FROM service_week_override WHERE service_id = :service_id AND week_start_date = :week_start_date");
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->bindParam(':week_start_date', $weekStartDate, PDO::PARAM_STR);
		$stmt->execute();
		return ['success' => true];
	}
}
