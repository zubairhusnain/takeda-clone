<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

function tk_contact_mail_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $defaults = require __DIR__ . '/contact-mail-config.php';
    $local = __DIR__ . '/contact-mail.local.php';
    if (is_file($local)) {
        $overrides = require $local;
        if (is_array($overrides)) {
            $defaults = array_replace_recursive($defaults, $overrides);
        }
    }

    $config = $defaults;
    return $config;
}

function tk_contact_send_mail(string $username, string $email, string $subject, string $message): array
{
    $config = tk_contact_mail_config();
    $to = (string)($config['recipient'] ?? 'zubairhusnain58@gmail.com');
    $fromEmail = (string)($config['from_email'] ?? '');
    $fromName = (string)($config['from_name'] ?? 'Takeda Contact Form');

    $safeName = trim($username);
    $safeEmail = trim($email);
    $safeSubject = str_replace(["\r", "\n"], '', trim($subject));
    $safeMessage = trim($message);

    $mailSubject = 'Contact: ' . $safeSubject;
    $body = "Contact form submission\n\n"
        . "Name: {$safeName}\n"
        . "Email: {$safeEmail}\n"
        . "Subject: {$safeSubject}\n\n"
        . "Message:\n{$safeMessage}\n";

    if (tk_contact_smtp_ready($config)) {
        $smtpResult = tk_contact_send_smtp($config, $to, $fromEmail, $fromName, $mailSubject, $body, $safeName, $safeEmail);
        if ($smtpResult['ok']) {
            return $smtpResult;
        }
        if (empty($config['fallback_mail'])) {
            return $smtpResult;
        }
    }

    return tk_contact_send_php_mail($to, $fromEmail, $fromName, $mailSubject, $body, $safeName, $safeEmail);
}

function tk_contact_smtp_ready(array $config): bool
{
    $smtp = $config['smtp'] ?? [];
    if (empty($smtp['enabled'])) {
        return false;
    }

    $user = trim((string)($smtp['username'] ?? ''));
    $pass = (string)($smtp['password'] ?? '');

    return $user !== '' && $pass !== '';
}

/** True when SMTP is on and real credentials (not placeholders) are set. */
function tk_contact_form_enabled(): bool
{
    $config = tk_contact_mail_config();
    if (!tk_contact_smtp_ready($config)) {
        return false;
    }

    $from = trim((string)($config['from_email'] ?? ''));
    $user = trim((string)($config['smtp']['username'] ?? ''));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($user, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $placeholders = '/your\.email|your-gmail|example\.com|changeme|xxx/i';
    if (preg_match($placeholders, $from) || preg_match($placeholders, $user)) {
        return false;
    }

    return true;
}

function tk_contact_send_smtp(
    array $config,
    string $to,
    string $fromEmail,
    string $fromName,
    string $subject,
    string $body,
    string $replyName,
    string $replyEmail
): array {
    $smtp = $config['smtp'] ?? [];
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return ['ok' => false, 'message' => 'PHPMailer is not installed. Run: composer install in takeda_offline/'];
    }

    require_once $autoload;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = (string)($smtp['host'] ?? 'smtp.gmail.com');
        $mail->Port = (int)($smtp['port'] ?? 587);
        $mail->SMTPAuth = true;
        $mail->Username = (string)$smtp['username'];
        $mail->Password = (string)$smtp['password'];
        $mail->CharSet = PHPMailer::CHARSET_UTF8;

        $encryption = strtolower((string)($smtp['encryption'] ?? 'tls'));
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->addReplyTo($replyEmail, $replyName);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);

        $mail->send();

        return ['ok' => true, 'message' => 'Thank you. Your message has been sent.'];
    } catch (MailerException $e) {
        return [
            'ok' => false,
            'message' => 'Could not send email (SMTP). Check contact-mail.local.php — ' . $mail->ErrorInfo,
        ];
    }
}

function tk_contact_send_php_mail(
    string $to,
    string $fromEmail,
    string $fromName,
    string $subject,
    string $body,
    string $replyName,
    string $replyEmail
): array {
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = 'noreply@localhost';
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . tk_contact_encode_address($fromName, $fromEmail),
        'Reply-To: ' . tk_contact_encode_address($replyName, $replyEmail),
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $headerString = implode("\r\n", $headers);
    $body = str_replace(["\r\n", "\r"], "\n", $body);

    $envelopeFrom = filter_var($fromEmail, FILTER_VALIDATE_EMAIL);
    $params = is_string($envelopeFrom) ? ('-f' . $envelopeFrom) : '';

    $ok = $params !== ''
        ? mail($to, $subject, $body, $headerString, $params)
        : mail($to, $subject, $body, $headerString);

    if ($ok) {
        return ['ok' => true, 'message' => 'Thank you. Your message has been sent.'];
    }

    return [
        'ok' => false,
        'message' => 'Could not send email. Configure SMTP in includes/contact-mail.local.php or enable mail() in php.ini.',
    ];
}

function tk_contact_encode_address(string $name, string $email): string
{
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!is_string($email) || $email === '') {
        return 'noreply@localhost';
    }
    $name = str_replace(['"', "\r", "\n"], '', $name);
    if ($name === '') {
        return $email;
    }
    return '"' . $name . '" <' . $email . '>';
}
