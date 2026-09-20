<?php
require_once __DIR__ . '/Sql.php';

class BookingFieldValue extends Sql {

	public function insertMany(PDO $conn, $bookingId, array $rows) {
		$stmt = $conn->prepare(
			"INSERT INTO booking_field_value (booking_id, field_key, field_label_snapshot, field_type_snapshot, value_text, sort_order)
			 VALUES (:booking_id, :field_key, :label, :type, :value, :sort_order)"
		);
		foreach ($rows as $row) {
			$stmt->bindValue(':booking_id', $bookingId, PDO::PARAM_INT);
			$stmt->bindValue(':field_key', $row['field_key'], PDO::PARAM_STR);
			$stmt->bindValue(':label', $row['label'], PDO::PARAM_STR);
			$stmt->bindValue(':type', $row['type'], PDO::PARAM_STR);
			$stmt->bindValue(':value', $row['value'], PDO::PARAM_STR);
			$stmt->bindValue(':sort_order', $row['sort_order'], PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	public function getForBooking($bookingId) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT * FROM booking_field_value WHERE booking_id = :booking_id ORDER BY sort_order ASC, id ASC");
		$stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getValueForKey($bookingId, $fieldKey) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT value_text FROM booking_field_value WHERE booking_id = :booking_id AND field_key = :field_key LIMIT 1");
		$stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
		$stmt->bindParam(':field_key', $fieldKey, PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? $row['value_text'] : null;
	}
}
