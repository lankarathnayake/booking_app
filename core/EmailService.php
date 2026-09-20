<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/Settings.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {

	/**
	 * Sends one HTML email. Returns true/false and never throws - callers
	 * (in particular the public booking flow) must not have a mail failure
	 * abort or roll back the operation that triggered it.
	 */
	public function send($recipientEmail, $recipientName, $subject, $bodyHtml) {
		if (empty(SMTP_HOST) || empty($recipientEmail)) {
			error_log("EmailService: skipped send to '$recipientEmail' (SMTP not configured or empty recipient)");
			return false;
		}

		$mail = new PHPMailer(true);
		try {
			$mail->CharSet = PHPMailer::CHARSET_UTF8;
			$mail->isSMTP();
			$mail->Host       = SMTP_HOST;
			$mail->Port       = SMTP_PORT;
			$mail->SMTPAuth   = true;
			$mail->Username   = SMTP_USERNAME;
			$mail->Password   = SMTP_PASSWORD;
			$mail->SMTPSecure = (SMTP_SECURE === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

			$mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
			$mail->addAddress($recipientEmail, $recipientName ?: '');

			$mail->isHTML(true);
			$mail->Subject = $subject;
			$mail->Body    = $this->wrapTemplate($subject, $bodyHtml);
			$mail->AltBody = strip_tags($bodyHtml);

			$mail->send();
			return true;
		} catch (Exception $e) {
			error_log("EmailService send failed to '$recipientEmail': " . $e->getMessage());
			return false;
		}
	}

	private function wrapTemplate($subject, $innerHtml) {
		$settings = new Settings();
		$brandName = htmlspecialchars($settings->get('business_name') ?: SMTP_FROM_NAME, ENT_QUOTES, 'UTF-8');
		$logoUrl = trim((string) $settings->get('business_logo_url', ''));

		if ($logoUrl !== '') {
			$logoUrlSafe = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
			$headerHtml = "<img src='{$logoUrlSafe}' alt='{$brandName}' style='max-width:240px;max-height:80px;height:auto;display:block;'>";
		} else {
			$headerHtml = "<h1 style='font-size:20px;margin:0;color:#153643;'>{$brandName}</h1>";
		}

		return "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<title></title>
<style>table, td, div, h1, h2, p {font-family: Arial, sans-serif;}</style>
</head>
<body style='margin:0;padding:0;background:#f4f4f4;'>
<table role='presentation' style='width:100%;border-collapse:collapse;background:#f4f4f4;'>
<tr><td align='center' style='padding:24px 0;'>
<table role='presentation' style='width:602px;max-width:94%;border-collapse:collapse;background:#ffffff;border:1px solid #dddddd;text-align:left;'>
<tr><td style='padding:24px 30px;border-bottom:1px solid #eeeeee;'>
{$headerHtml}
</td></tr>
<tr><td style='padding:24px 30px;color:#153643;font-size:15px;line-height:22px;'>
{$innerHtml}
</td></tr>
<tr><td style='padding:16px 30px;background:#2c7ea4;font-size:12px;color:#ffffff;'>
&reg; {$brandName} " . date('Y') . "
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>";
	}
}
