<?php
require_once __DIR__ . '/../../config/env.php';

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

class NotificationService {
    private $pdo;
    private $mail;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $this->mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            } catch (\Throwable $e) {
                $this->mail = null;
            }
        }
    }

    /**
     * Retrieve a setting from app_settings table first, falling back to environment variable.
     */
    public function getSetting(string $key, $default = null) {
        if ($this->pdo) {
            try {
                $st = $this->pdo->prepare("SELECT value FROM app_settings WHERE key = ?");
                $st->execute([$key]);
                $val = $st->fetchColumn();
                if ($val !== false && $val !== null && $val !== '') {
                    return $val;
                }
            } catch (\Throwable $e) {
                // Table might not exist yet before migration
            }
        }
        return getEnvVar($key, $default);
    }

    /**
     * Save settings to the app_settings table.
     */
    public function saveSettings(array $settings): bool {
        if (!$this->pdo) {
            return false;
        }
        try {
            $st = $this->pdo->prepare("INSERT INTO app_settings (key, value, updated_at)
                VALUES (?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = CURRENT_TIMESTAMP");
            foreach ($settings as $k => $v) {
                $st->execute([$k, (string) $v]);
            }
            return true;
        } catch (\Throwable $e) {
            error_log("Failed to save SMTP settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve the resolved SMTP configuration.
     */
    public function getSmtpConfig(): array {
        $enabled = $this->getSetting('SMTP_ENABLED');
        $driver  = $this->getSetting('NOTIFICATION_DRIVER', 'smtp');

        // Enabled if explicitly set to '1' or if driver is 'smtp' and host is configured
        $host = $this->getSetting('SMTP_HOST', 'smtp.gmail.com');
        $isExplicitlyDisabled = ($enabled === '0' || $driver === 'log');
        $isEnabled = !$isExplicitlyDisabled && ($enabled === '1' || !empty($host));

        return [
            'enabled'    => $isEnabled,
            'host'       => (string) $host,
            'port'       => (int) $this->getSetting('SMTP_PORT', 587),
            'secure'     => strtolower((string) $this->getSetting('SMTP_SECURE', 'tls')),
            'user'       => (string) $this->getSetting('SMTP_USER', ''),
            'pass'       => (string) $this->getSetting('SMTP_PASS', ''),
            'from'       => (string) ($this->getSetting('SMTP_FROM', '') ?: $this->getSetting('SMTP_USER', '')),
            'from_name'  => (string) $this->getSetting('SMTP_FROM_NAME', 'FFMS Alerts'),
            'recipient'  => (string) ($this->getSetting('SMTP_RECIPIENT', '') ?: $this->getSetting('ALERT_EMAIL', '')),
        ];
    }

    /**
     * Send an email using either PHPMailer or the built-in native socket SMTP client.
     */
    public function sendEmail($to, string $subject, string $htmlBody, ?string $altBody = null): array {
        $cfg = $this->getSmtpConfig();
        $targetEmail = is_array($to) ? (key($to) ?: reset($to)) : (string) $to;
        $targetEmail = trim($targetEmail);

        if ($targetEmail === '' || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => "Invalid recipient email address: '$targetEmail'"];
        }

        // 1. Try PHPMailer if loaded
        if ($this->mail) {
            try {
                $mail = $this->mail;
                $mail->clearAllRecipients();
                $mail->clearAttachments();
                $mail->clearCustomHeaders();

                $mail->isSMTP();
                $mail->Host       = $cfg['host'];
                $mail->SMTPAuth   = ($cfg['user'] !== '' && $cfg['pass'] !== '');
                $mail->Username   = $cfg['user'];
                $mail->Password   = $cfg['pass'];
                $mail->Port       = $cfg['port'];

                if ($cfg['secure'] === 'ssl' || $cfg['port'] === 465) {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }

                $mail->setFrom($cfg['from'] ?: $cfg['user'], $cfg['from_name']);
                if (is_array($to)) {
                    foreach ($to as $e => $n) {
                        $mail->addAddress($e, is_string($n) ? $n : '');
                    }
                } else {
                    $mail->addAddress($targetEmail);
                }

                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = $subject;
                $mail->Body    = $htmlBody;
                $mail->AltBody = $altBody ?: strip_tags($htmlBody);

                $sent = $mail->send();
                if ($sent) {
                    return ['success' => true, 'error' => null];
                }
            } catch (\Throwable $e) {
                error_log("PHPMailer error, attempting native socket fallback: " . $e->getMessage());
            }
        }

        // 2. Native socket SMTP transport (zero dependencies, works on all PHP installs)
        $transcript = [];
        $res = self::sendViaSocket($cfg, $targetEmail, $subject, $htmlBody, $altBody, $transcript);
        if (!$res['success']) {
            error_log("Native SMTP send error: " . ($res['error'] ?? 'Unknown'));
        }
        return $res;
    }

    /**
     * Backward-compatible send() method.
     */
    public function send($to, $subject, $body) {
        $res = $this->sendEmail($to, (string) $subject, (string) $body);
        return $res['success'];
    }

    /**
     * Send an automated operational notification.
     */
    public function notify(string $subject, string $message, ?string $recipient = null, array $meta = []): bool {
        $cfg = $this->getSmtpConfig();
        if (!$cfg['enabled']) {
            error_log("FFMS notification (SMTP disabled): {$subject} - {$message}");
            return true;
        }

        $to = $recipient ?: $cfg['recipient'];
        if (!$to) {
            // Find active admin/manager emails in the database
            if ($this->pdo) {
                try {
                    $st = $this->pdo->query("SELECT email FROM users WHERE role IN ('admin', 'manager', 'farm-owner') AND email IS NOT NULL AND email != '' ORDER BY id ASC LIMIT 1");
                    $to = $st->fetchColumn() ?: null;
                } catch (\Throwable $e) { }
            }
        }

        if (!$to) {
            error_log("FFMS notification: No recipient email found for alert: {$subject}");
            return false;
        }

        $appUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $timeStr = date('Y-m-d H:i:s T');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeMsg = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$safeSubject}</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px; color: #1e293b; }
  .box { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
  .top { background: linear-gradient(135deg, #15803d 0%, #059669 100%); padding: 24px 28px; color: #ffffff; }
  .top h1 { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; }
  .top p { margin: 4px 0 0; font-size: 13px; opacity: 0.9; }
  .body { padding: 28px; }
  .tag { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: #ecfdf5; color: #065f46; margin-bottom: 16px; }
  .msg { background: #f8fafc; border-left: 4px solid #10b981; padding: 18px; border-radius: 8px; margin: 16px 0; font-size: 15px; line-height: 1.6; color: #334155; }
  .btn { display: inline-block; background: #15803d; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 10px; font-weight: 600; font-size: 14px; margin-top: 12px; }
  .meta { font-size: 12px; color: #94a3b8; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 16px; }
  .foot { background: #f8fafc; padding: 16px 28px; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; text-align: center; }
</style>
</head>
<body>
  <div class="box">
    <div class="top">
      <h1>🌱 FFMS — Farm Management System</h1>
      <p>Operational Farm Notification</p>
    </div>
    <div class="body">
      <span class="tag">Operational Alert</span>
      <h2 style="margin: 0 0 10px; font-size: 18px; color: #0f172a;">{$safeSubject}</h2>
      <div class="msg">{$safeMsg}</div>
      <a href="{$appUrl}/index.php?module=Notifications" class="btn">View Notifications Dashboard &rarr;</a>
      <div class="meta">Timestamp: {$timeStr}</div>
    </div>
    <div class="foot">
      Automated operational alert sent via SMTP from your Farm Management System.
    </div>
  </div>
</body>
</html>
HTML;

        $res = $this->sendEmail($to, $subject, $html, $message);
        return $res['success'];
    }

    /**
     * Test the SMTP connection and send a verification email.
     */
    public function testConnection(string $testEmail): array {
        $cfg = $this->getSmtpConfig();
        $testEmail = trim($testEmail);

        if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please provide a valid test recipient email address.', 'transcript' => []];
        }

        $now = date('Y-m-d H:i:s T');
        $subject = "FFMS SMTP Test: Verification Successful";
        $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; background: #f8fafc; padding: 20px;">
  <div style="max-width: 550px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 24px; border: 1px solid #e2e8f0;">
    <h2 style="color: #15803d; margin-top: 0;">✓ SMTP Verification Successful</h2>
    <p style="color: #334155; font-size: 14px; line-height: 1.6;">
      This email confirms that your SMTP server configuration for the <strong>Farm Management System (FFMS)</strong> is working correctly.
    </p>
    <ul style="color: #64748b; font-size: 13px;">
      <li><strong>SMTP Host:</strong> {$cfg['host']}:{$cfg['port']}</li>
      <li><strong>Encryption:</strong> {$cfg['secure']}</li>
      <li><strong>Sender:</strong> {$cfg['from']}</li>
      <li><strong>Recipient:</strong> {$testEmail}</li>
      <li><strong>Tested At:</strong> {$now}</li>
    </ul>
    <p style="color: #15803d; font-weight: bold; font-size: 14px;">Operational notifications and alert rules are ready for delivery.</p>
  </div>
</body>
</html>
HTML;

        $transcript = [];
        $res = self::sendViaSocket($cfg, $testEmail, $subject, $body, "FFMS SMTP Verification Successful at $now", $transcript);
        return $res;
    }

    /**
     * Native RFC 5321 / RFC 4954 socket SMTP client.
     * Zero external dependencies — runs directly in standard PHP.
     */
    public static function sendViaSocket(array $config, string $to, string $subject, string $htmlBody, ?string $altBody = null, &$transcript = []): array {
        $host = trim($config['host'] ?? 'localhost');
        $port = (int) ($config['port'] ?? 587);
        $secure = strtolower((string) ($config['secure'] ?? 'tls'));
        $user = trim((string) ($config['user'] ?? ''));
        $pass = (string) ($config['pass'] ?? '');
        $from = trim((string) ($config['from'] ?? ''));
        $fromName = trim((string) ($config['from_name'] ?? 'FFMS Alerts'));

        if ($from === '') {
            $from = $user !== '' ? $user : 'no-reply@' . (gethostname() ?: 'ffms.local');
        }

        $transcript = [];
        $transcript[] = "Connecting to {$host}:{$port} (mode: {$secure})...";

        $socketPrefix = ($secure === 'ssl' || $port === 465) ? 'ssl://' : 'tcp://';
        $timeout = 15;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($socketPrefix . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$socket) {
            $err = "Connection failed to {$host}:{$port} - [{$errno}] {$errstr}";
            $transcript[] = "ERROR: " . $err;
            return ['success' => false, 'error' => $err, 'transcript' => $transcript];
        }

        stream_set_timeout($socket, $timeout);

        $readResponse = function() use ($socket, &$transcript) {
            $response = '';
            $code = 0;
            while (!feof($socket)) {
                $line = fgets($socket, 1024);
                if ($line === false) break;
                $trimmed = trim($line);
                $transcript[] = "S: " . $trimmed;
                $response .= $line;
                if (preg_match('/^(\d{3})[ ](.*)$/', $trimmed, $m)) {
                    $code = (int) $m[1];
                    break;
                }
            }
            return ['code' => $code, 'text' => $response];
        };

        $sendCommand = function(string $cmd, bool $hide = false) use ($socket, &$transcript, $readResponse) {
            $transcript[] = "C: " . ($hide ? '***' : $cmd);
            fwrite($socket, $cmd . "\r\n");
            return $readResponse();
        };

        try {
            // 1. Initial banner
            $res = $readResponse();
            if ($res['code'] !== 220) {
                throw new \Exception("Initial banner rejected: " . $res['text']);
            }

            // 2. EHLO
            $clientHost = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
            $res = $sendCommand("EHLO {$clientHost}");
            if ($res['code'] !== 250) {
                $res = $sendCommand("HELO {$clientHost}");
                if ($res['code'] !== 250) {
                    throw new \Exception("EHLO/HELO failed: " . $res['text']);
                }
            }

            // 3. STARTTLS if configured
            if ($secure === 'tls' || ($port === 587 && $socketPrefix !== 'ssl://')) {
                $res = $sendCommand("STARTTLS");
                if ($res['code'] === 220) {
                    $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                        $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                    }
                    if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                        $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                    }

                    $cryptoOk = stream_socket_enable_crypto($socket, true, $cryptoMethod);
                    if (!$cryptoOk) {
                        throw new \Exception("TLS handshake negotiation failed with server.");
                    }
                    $transcript[] = "TLS encryption established successfully.";

                    // EHLO again after TLS
                    $res = $sendCommand("EHLO {$clientHost}");
                    if ($res['code'] !== 250) {
                        throw new \Exception("EHLO after STARTTLS failed: " . $res['text']);
                    }
                } else {
                    $transcript[] = "STARTTLS not accepted by server (code: {$res['code']}), continuing...";
                }
            }

            // 4. AUTH LOGIN
            if ($user !== '' && $pass !== '') {
                $res = $sendCommand("AUTH LOGIN");
                if ($res['code'] !== 334) {
                    throw new \Exception("AUTH LOGIN command rejected: " . $res['text']);
                }
                $res = $sendCommand(base64_encode($user));
                if ($res['code'] !== 334) {
                    throw new \Exception("SMTP username rejected: " . $res['text']);
                }
                $res = $sendCommand(base64_encode($pass), true);
                if ($res['code'] !== 235) {
                    throw new \Exception("SMTP Authentication failed: " . $res['text']);
                }
                $transcript[] = "SMTP Authentication successful.";
            }

            // 5. MAIL FROM
            $res = $sendCommand("MAIL FROM:<{$from}>");
            if ($res['code'] !== 250) {
                throw new \Exception("Sender rejected (<{$from}>): " . $res['text']);
            }

            // 6. RCPT TO
            $res = $sendCommand("RCPT TO:<{$to}>");
            if ($res['code'] !== 250 && $res['code'] !== 251) {
                throw new \Exception("Recipient rejected (<{$to}>): " . $res['text']);
            }

            // 7. DATA
            $res = $sendCommand("DATA");
            if ($res['code'] !== 354) {
                throw new \Exception("DATA initiation rejected: " . $res['text']);
            }

            // 8. Headers & Body
            $boundary = "=_ffms_" . md5(microtime(true) . mt_rand());
            $date = date('r');
            $msgId = "<" . bin2hex(random_bytes(12)) . "@" . ($host ?: 'ffms.local') . ">";

            $headers = [];
            $headers[] = "Date: {$date}";
            $headers[] = "From: " . ($fromName ? "=?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>" : "<{$from}>");
            $headers[] = "To: <{$to}>";
            $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
            $headers[] = "Message-ID: {$msgId}";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

            $plainText = $altBody ?: strip_tags($htmlBody);

            $payload = implode("\r\n", $headers) . "\r\n\r\n";
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $payload .= chunk_split(base64_encode($plainText)) . "\r\n";
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Type: text/html; charset=UTF-8\r\n";
            $payload .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $payload .= chunk_split(base64_encode($htmlBody)) . "\r\n";
            $payload .= "--{$boundary}--\r\n";

            $transcript[] = "C: [Transmitting email data (" . strlen($payload) . " bytes)]";
            fwrite($socket, $payload . "\r\n.\r\n");
            $res = $readResponse();
            if ($res['code'] !== 250) {
                throw new \Exception("Server rejected message body: " . $res['text']);
            }

            $transcript[] = "Server accepted message for delivery.";

            // 9. QUIT
            $sendCommand("QUIT");
            fclose($socket);

            return ['success' => true, 'transcript' => $transcript];
        } catch (\Throwable $e) {
            @fclose($socket);
            $transcript[] = "ERROR: " . $e->getMessage();
            return ['success' => false, 'error' => $e->getMessage(), 'transcript' => $transcript];
        }
    }

    public function sendSms(string $recipient, string $message): bool {
        return $this->logSimulator($recipient, $message);
    }

    private function logSimulator(string $recipient, string $message): bool {
        if (!$this->pdo) {
            error_log("FFMS notification simulator: {$recipient} {$message}");
            return true;
        }
        try {
            $stmt = $this->pdo->prepare("INSERT INTO sms_simulator_logs (recipient, message) VALUES (?, ?)");
            return $stmt->execute([$recipient, $message]);
        } catch (\Throwable $e) {
            error_log("FFMS notification simulator error: " . $e->getMessage());
            return true;
        }
    }
}
