<?php
declare(strict_types=1);

function send_mail(string $to, string $subject, string $body): bool
{
    $fromEmail = (string) config('mail_from', '');
    if ($fromEmail === '') {
        $fromEmail = 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $fromName = (string) config('mail_from_name', 'Publicaties');
    $replyTo  = (string) config('admin_email', '');
    if ($replyTo === '') {
        $replyTo = $fromEmail;
    }

    $headers = [
        'From: ' . mb_encode_mimeheader($fromName, 'UTF-8', 'B') . ' <' . $fromEmail . '>',
        'Reply-To: ' . $replyTo,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    $ok = @mail(
        $to,
        mb_encode_mimeheader($subject, 'UTF-8', 'B'),
        $body,
        implode("\r\n", $headers)
    );
    if (!$ok) {
        log_msg('E-mail versturen mislukt naar ' . $to . ' (onderwerp: ' . $subject . ')');
    }
    return $ok;
}
