<?php
require_once __DIR__ . '/Sql.php';
require_once __DIR__ . '/ServiceSchedule.php';
require_once __DIR__ . '/ServiceBlackout.php';
require_once __DIR__ . '/ServiceWeekOverride.php';
require_once __DIR__ . '/Settings.php';

/**
 * Single source of truth for a service's bookable dates/times: weekly
 * schedule template, minus blackout dates, minus already-occupied slots
 * (a real `booking` row for that service/date/time).
 */
class Availability extends Sql {

	private $schedule;
	private $blackout;
	private $weekOverride;
	private $settings;

	public function __construct() {
		$this->schedule = new ServiceSchedule();
		$this->blackout = new ServiceBlackout();
		$this->weekOverride = new ServiceWeekOverride();
		$this->settings = new Settings();
	}

	public function getBookingBounds(array $service) {
		$leadHours = $service['lead_time_hours'] !== null
			? (int) $service['lead_time_hours']
			: (int) $this->settings->get('default_lead_time_hours', 24);
		$windowDays = $service['booking_window_days'] !== null
			? (int) $service['booking_window_days']
			: (int) $this->settings->get('default_booking_window_days', 30);

		$min = new DateTime();
		$min->modify("+{$leadHours} hours");
		$max = new DateTime();
		$max->modify("+{$windowDays} days");

		return ['min' => $min, 'max' => $max];
	}

	private function getOccupiedTimes($serviceId, $date) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare(
			"SELECT appointment_time FROM booking WHERE service_id = :service_id AND appointment_date = :date AND status != 'cancelled' AND deleted_at IS NULL"
		);
		$stmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
		$stmt->bindParam(':date', $date, PDO::PARAM_STR);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_COLUMN);
	}

	/**
	 * @return array of ['start_time' => 'HH:MM:SS', 'end_time' => 'HH:MM:SS']
	 */
	public function getAvailableSlots(array $service, $date) {
		$serviceId = $service['id'];
		$dateObj = DateTime::createFromFormat('Y-m-d', $date);
		if (!$dateObj) {
			return [];
		}

		$bounds = $this->getBookingBounds($service);
		$dayStart = clone $dateObj;
		$dayStart->setTime(0, 0, 0);
		if ($dayStart > $bounds['max'] || $dayStart < (clone $bounds['min'])->setTime(0, 0, 0)) {
			return [];
		}

		if ($this->blackout->isBlackedOut($serviceId, $date)) {
			return [];
		}

		$weekStartDate = ServiceWeekOverride::weekStartFor($date);
		if ($this->weekOverride->isCustomized($serviceId, $weekStartDate)) {
			// This week has been forked from the default - use exactly what's
			// there for this date, even if that's nothing (deliberately blank).
			$templateSlots = $this->weekOverride->getOverrideSlotsForDate($serviceId, $weekStartDate, $date);
		} else {
			$weekday = (int) $dateObj->format('w');
			$templateSlots = $this->schedule->getActiveForServiceAndWeekday($serviceId, $weekday);
		}
		if (!$templateSlots) {
			return [];
		}

		$occupied = array_flip($this->getOccupiedTimes($serviceId, $date));

		$isToday = $dateObj->format('Y-m-d') === (new DateTime())->format('Y-m-d');
		$available = [];
		foreach ($templateSlots as $slot) {
			if (isset($occupied[$slot['start_time']])) {
				continue;
			}
			if ($isToday) {
				$slotDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $slot['start_time']);
				if ($slotDateTime < $bounds['min']) {
					continue;
				}
			}
			$available[] = ['start_time' => $slot['start_time'], 'end_time' => $slot['end_time']];
		}

		return $available;
	}

	/**
	 * @return array of 'Y-m-d' date strings within $yearMonth that have >=1 open slot.
	 */
	public function getAvailableDatesForMonth(array $service, $yearMonth) {
		$monthStart = DateTime::createFromFormat('Y-m-d', $yearMonth . '-01');
		if (!$monthStart) {
			return [];
		}
		$monthEnd = (clone $monthStart)->modify('last day of this month');

		$bounds = $this->getBookingBounds($service);
		$rangeStart = max($monthStart, (clone $bounds['min'])->setTime(0, 0, 0));
		$rangeEnd = min($monthEnd, $bounds['max']);

		if ($rangeStart > $rangeEnd) {
			return [];
		}

		$available = [];
		$cursor = clone $rangeStart;
		while ($cursor <= $rangeEnd) {
			$dateStr = $cursor->format('Y-m-d');
			if ($this->getAvailableSlots($service, $dateStr)) {
				$available[] = $dateStr;
			}
			$cursor->modify('+1 day');
		}

		return $available;
	}
}
