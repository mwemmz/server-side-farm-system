<?php
require_once __DIR__ . '/../../config/env.php';

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NotificationService {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->mail->isSMTP();
        $this->mail->Host       = getEnvVar('SMTP_HOST', 'smtp.gmail.com');
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = getEnvVar('SMTP_USER');
        $this->mail->Password   = getEnvVar('SMTP_PASS');
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = (int) getEnvVar('SMTP_PORT', 587);
        $this->mail->setFrom(
            getEnvVar('SMTP_FROM', getEnvVar('SMTP_USER')),
            getEnvVar('SMTP_FROM_NAME', 'FFMS')
        );
        $this->mail->isHTML(true);
    }

    /**
     * Send an HTML email notification.
     * @param string|array $to Recipient email or [email => name]
     * @param string $subject
     * @param string $body HTML body
     * @return bool
     */
    public function send($to, $subject, $body) {
        try {
            $this->mail->clearAddresses();
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    $this->mail->addAddress($email, $name);
                }
            } else {
                $this->mail->addAddress($to);
            }
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Mail send failed: " . $this->mail->ErrorInfo);
            return false;
        }
    }
}
?>