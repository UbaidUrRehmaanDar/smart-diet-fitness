<?php
/**
 * Simple SMTP mailer (no external dependencies)
 * Supports: SSL (465) and STARTTLS (587)
 */

require_once __DIR__ . '/config.php';

function send_password_reset_email(string $to_email, string $reset_link): bool
{
    $subject = 'Reset your password';
    $text = "You requested a password reset for " . APP_NAME . ".\r\n\r\n" .
        "Reset your password using this link:\r\n" .
        $reset_link . "\r\n\r\n" .
        "If you did not request this, you can ignore this email.";

    $html = "<p>You requested a password reset for <strong>" . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . "</strong>.</p>" .
        "<p><a href=\"" . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . "\">Reset your password</a></p>" .
        "<p>If you did not request this, you can ignore this email.</p>";

    return smtp_send_mail($to_email, $subject, $html, $text);
}

function smtp_send_mail(string $to_email, string $subject, string $html_body, string $text_body = ''): bool
{
    if (!SMTP_HOST) {
        error_log('SMTP not configured: missing SMTP_HOST');
        return false;
    }

    $socket = smtp_open();
    if (!$socket) {
        return false;
    }

    $hello_ok = smtp_send_command($socket, 'EHLO ' . gethostname(), 250);
    if (!$hello_ok) {
        $hello_ok = smtp_send_command($socket, 'HELO ' . gethostname(), 250);
        if (!$hello_ok) {
            smtp_close($socket);
            return false;
        }
    }

    if (SMTP_SECURE === 'tls') {
        if (!smtp_send_command($socket, 'STARTTLS', 220)) {
            smtp_close($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log('SMTP STARTTLS negotiation failed');
            smtp_close($socket);
            return false;
        }
        if (!smtp_send_command($socket, 'EHLO ' . gethostname(), 250)) {
            smtp_close($socket);
            return false;
        }
    }

    if (SMTP_USER !== '') {
        if (!smtp_send_command($socket, 'AUTH LOGIN', 334)) {
            smtp_close($socket);
            return false;
        }
        if (!smtp_send_command($socket, base64_encode(SMTP_USER), 334)) {
            smtp_close($socket);
            return false;
        }
        if (!smtp_send_command($socket, base64_encode(SMTP_PASS), 235)) {
            smtp_close($socket);
            return false;
        }
    }

    $from_email = SMTP_FROM_EMAIL;
    $from_name = SMTP_FROM_NAME;

    if (!smtp_send_command($socket, 'MAIL FROM:<' . $from_email . '>', 250)) {
        smtp_close($socket);
        return false;
    }
    if (!smtp_send_command($socket, 'RCPT TO:<' . $to_email . '>', 250)) {
        smtp_close($socket);
        return false;
    }
    if (!smtp_send_command($socket, 'DATA', 354)) {
        smtp_close($socket);
        return false;
    }

    $boundary = 'b1_' . bin2hex(random_bytes(8));
    $subject_header = encode_header($subject);
    $from_header = encode_header($from_name) . ' <' . $from_email . '>';

    $headers = [
        'From: ' . $from_header,
        'To: <' . $to_email . '>',
        'Subject: ' . $subject_header,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    $text_body = $text_body !== '' ? $text_body : strip_tags($html_body);
    $text_body = quoted_printable_encode($text_body);
    $html_body = quoted_printable_encode($html_body);

    $message = implode("\r\n", $headers) . "\r\n\r\n";
    $message .= "--" . $boundary . "\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $message .= $text_body . "\r\n\r\n";
    $message .= "--" . $boundary . "\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $message .= $html_body . "\r\n\r\n";
    $message .= "--" . $boundary . "--\r\n";

    $message = str_replace("\r\n.", "\r\n..", $message);
    fwrite($socket, $message . "\r\n.\r\n");

    $final = smtp_read_response($socket);
    if (!smtp_response_ok($final, 250)) {
        error_log('SMTP message rejected: ' . trim($final));
        smtp_close($socket);
        return false;
    }

    smtp_send_command($socket, 'QUIT', 221);
    smtp_close($socket);
    return true;
}

function smtp_open()
{
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $timeout = SMTP_TIMEOUT;

    $transport = $host;
    if (SMTP_SECURE === 'ssl') {
        $transport = 'ssl://' . $host;
    }

    $socket = @fsockopen($transport, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        error_log('SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
        return false;
    }

    stream_set_timeout($socket, $timeout);
    $greeting = smtp_read_response($socket);
    if (!smtp_response_ok($greeting, 220)) {
        error_log('SMTP greeting failed: ' . trim($greeting));
        fclose($socket);
        return false;
    }

    return $socket;
}

function smtp_send_command($socket, string $command, int $expect_code): bool
{
    fwrite($socket, $command . "\r\n");
    $response = smtp_read_response($socket);
    if (!smtp_response_ok($response, $expect_code)) {
        error_log('SMTP command failed: ' . $command . ' | ' . trim($response));
        return false;
    }
    return true;
}

function smtp_read_response($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (preg_match('/^\d{3} /', $line)) {
            break;
        }
    }
    return $response;
}

function smtp_response_ok(string $response, int $expect_code): bool
{
    return strpos($response, (string)$expect_code) === 0;
}

function smtp_close($socket): void
{
    if (is_resource($socket)) {
        fclose($socket);
    }
}

function encode_header(string $value): string
{
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B');
    }
    return $value;
}
