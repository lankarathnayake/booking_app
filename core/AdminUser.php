<?php
require_once __DIR__ . '/Sql.php';

class AdminUser extends Sql {

	/**
	 * Returns the user row (without password_hash) on success, or null.
	 */
	public function authenticate($email, $password) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT * FROM admin_users WHERE email = :email AND status = 1");
		$stmt->bindParam(':email', $email, PDO::PARAM_STR);
		$stmt->execute();
		$user = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$user || !password_verify($password, $user['password_hash'])) {
			return null;
		}

		$update = $conn->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = :id");
		$update->bindParam(':id', $user['id'], PDO::PARAM_INT);
		$update->execute();

		unset($user['password_hash']);
		return $user;
	}

	public function findById($id) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("SELECT id, name, email, role, status FROM admin_users WHERE id = :id");
		$stmt->bindParam(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		$user = $stmt->fetch(PDO::FETCH_ASSOC);
		return $user ?: null;
	}

	public function create($name, $email, $password, $role = 'admin') {
		$conn = $this->getConnection();
		$hash = password_hash($password, PASSWORD_DEFAULT);
		$stmt = $conn->prepare("INSERT INTO admin_users (name, email, password_hash, role) VALUES (:name, :email, :hash, :role)");
		$stmt->bindParam(':name', $name, PDO::PARAM_STR);
		$stmt->bindParam(':email', $email, PDO::PARAM_STR);
		$stmt->bindParam(':hash', $hash, PDO::PARAM_STR);
		$stmt->bindParam(':role', $role, PDO::PARAM_STR);
		$stmt->execute();
		return $conn->lastInsertId();
	}
}
