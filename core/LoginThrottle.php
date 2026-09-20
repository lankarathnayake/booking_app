<?php
require_once __DIR__ . '/Sql.php';

/**
 * Brute-force protection for the admin sign-in. Failures are stored in the
 * DB (not the session) so dropping cookies doesn't reset the counter.
 *  - per IP:    MAX_PER_IP failures in the window blocks that IP
 *  - per email: MAX_PER_EMAIL failures in the window blocks that account
 *               from all IPs (catches distributed guessing), at a higher
 *               threshold so a single attacker can't trivially lock the
 *               real admin out.
 * IP is REMOTE_ADDR - behind a reverse proxy, every visitor shares the
 * proxy's address, so configure the proxy to pass the real IP through
 * (mod_remoteip) before relying on the per-IP limit.
 */
class LoginThrottle extends Sql {

	const WINDOW_MINUTES = 15;
	const MAX_PER_IP = 5;
	const MAX_PER_EMAIL = 10;

	private function normalizeEmail($email) {
		return substr(strtolower(trim((string) $email)), 0, 190);
	}

	public function isBlocked($ip, $email) {
		$conn = $this->getConnection();
		$email = $this->normalizeEmail($email);

		$stmt = $conn->prepare(
			"SELECT
				(SELECT COUNT(*) FROM login_attempt WHERE ip = :ip AND attempted_at > DATE_SUB(NOW(), INTERVAL " . (int) self::WINDOW_MINUTES . " MINUTE)) AS ip_count,
				(SELECT COUNT(*) FROM login_attempt WHERE email = :email AND attempted_at > DATE_SUB(NOW(), INTERVAL " . (int) self::WINDOW_MINUTES . " MINUTE)) AS email_count"
		);
		$stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
		$stmt->bindValue(':email', $email, PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return (int) $row['ip_count'] >= self::MAX_PER_IP || (int) $row['email_count'] >= self::MAX_PER_EMAIL;
	}

	public function recordFailure($ip, $email) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("INSERT INTO login_attempt (ip, email) VALUES (:ip, :email)");
		$stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
		$stmt->bindValue(':email', $this->normalizeEmail($email), PDO::PARAM_STR);
		$stmt->execute();

		// Housekeeping: nothing older than a day is ever consulted.
		$conn->exec("DELETE FROM login_attempt WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
	}

	public function clearForIp($ip) {
		$conn = $this->getConnection();
		$stmt = $conn->prepare("DELETE FROM login_attempt WHERE ip = :ip");
		$stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
		$stmt->execute();
	}
}
