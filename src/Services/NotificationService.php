<?php
require_once __DIR__ . '/../../config/env.php';

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

class NotificationService {
    private $mail;
    private $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return;
        }
        $this->mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $this->mail->isSMTP();
        $this->mail->Host       = getEnvVar('SMTP_HOST', 'smtp.gmail.com');
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = getEnvVar('SMTP_USER');
        $this->mail->Password   = getEnvVar('SMTP_PASS');
        $this->mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
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
        if (!$this->mail) {
            return $this->logSimulator((string) $to, (string) $body);
        }
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
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("Mail send failed: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    public function sendSms(string $recipient, string $message): bool
    {
        return $this->logSimulator($recipient, $message);
    }

    private function logSimulator(string $recipient, string $message): bool
    {
        if (!$this->pdo) {
            error_log("FFMS notification simulator: {$recipient} {$message}");
            return true;
        }
        $stmt = $this->pdo->prepare("INSERT INTO sms_simulator_logs (recipient, message) VALUES (?, ?)");
        return $stmt->execute([$recipient, $message]);
    }
}
?>