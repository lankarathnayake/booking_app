<?php
require_once __DIR__ . '/../config.php';

class Sql {

	public function getConnection() {
		try {
			$conn = new PDO(
				"mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
				DB_USER,
				DB_PASS
			);
			$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			return $conn;
		} catch (PDOException $ex) {
			http_response_code(500);
			die('Database connection failed.');
		}
	}
}
