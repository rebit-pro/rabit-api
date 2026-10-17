<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Files\Application\Delivery\UseCase\DispatchOrderEmailsUseCase;
use Rebit\Notification\Application\Delivery\UseCase\DeliverEmailUseCase;

// J2/J5: the stand has no cron, no mail worker and no SMTP. On a request of the browser it acts for them:
// - dispatch: runs app:files:order-emails once and returns the order key from the latest queued letter to the recipient;
// - deliver: sends the queued letters of the recipient through the real H1 transport and Bitrix mail; custom_mail() catches
//   each MIME message instead of msmtp, and the answer lists every letter with the sha256 of its decoded attachments;
// - age: moves the letters of the recipient eleven minutes back, so the buyer may ask for the letters again (J2-DEC-07).
if ('test' !== getenv('APP_ENV') || !is_file('/runtime/j2-request.json')) {
    throw new RuntimeException('The order email fixture requires the disposable stand and a request of the browser.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
foreach (['rebit.notification', 'morefoto.files'] as $module) {
    if (!Loader::includeModule($module)) {
        throw new RuntimeException('Cannot load isolated module ' . $module);
    }
}
$request = json_decode((string)file_get_contents('/runtime/j2-request.json'), true, flags: JSON_THROW_ON_ERROR);
$connection = Application::getConnection();
$recipient = $connection->getSqlHelper()->forSql((string)$request['recipient']);
$answer = match ($request['action'] ?? 'dispatch') {
    'dispatch' => j5_dispatch($connection, $recipient),
    'deliver' => j5_deliver($connection, $recipient),
    'age' => j5_age($connection, $recipient),
};
file_put_contents('/runtime/j2-answer.json', json_encode($answer, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
echo "J2 order email fixture: answered\n";

/** @return array{created: int, queued: int, key: null|string} */
function j5_dispatch(Bitrix\Main\DB\Connection $connection, string $recipient): array
{
    $result = ServiceLocator::getInstance()->get(DispatchOrderEmailsUseCase::class)->execute(100);
    $letter = $connection->query("SELECT BODY FROM b_rebit_notification_operation WHERE CONSUMER_KEY='files-order-email' AND RECIPIENT='{$recipient}'
        ORDER BY CREATED_AT DESC,ID DESC LIMIT 1")->fetchRaw();
    $key = is_array($letter) && 1 === preg_match('~/orders/access/([a-f0-9]{64})~', (string)$letter['BODY'], $match) ? $match[1] : null;

    return ['created' => $result->created, 'queued' => $result->queued, 'key' => $key];
}

/** @return array{delivered: int, letters: list<array<string, mixed>>} */
function j5_deliver(Bitrix\Main\DB\Connection $connection, string $recipient): array
{
    $mailbox = '/runtime/j5-mail';
    is_dir($mailbox) || mkdir($mailbox, 0777, true);
    array_map('unlink', glob($mailbox . '/*.eml') ?: []);
    $operations = $connection->query("SELECT ID FROM b_rebit_notification_operation WHERE CONSUMER_KEY='files-order-email' AND RECIPIENT='{$recipient}'
        AND STATUS IN ('pending','retryWait') ORDER BY CREATED_AT,DEDUP_KEY");
    $delivered = 0;
    while (false !== ($row = $operations->fetchRaw())) {
        ServiceLocator::getInstance()->get(DeliverEmailUseCase::class)->execute((string)$row['ID']);
        ++$delivered;
    }
    $letters = [];
    foreach (glob($mailbox . '/*.eml') ?: [] as $file) {
        $letters[] = j5_letter((string)file_get_contents($file));
    }

    return ['delivered' => $delivered, 'letters' => $letters];
}

/** @return array{aged: int} */
function j5_age(Bitrix\Main\DB\Connection $connection, string $recipient): array
{
    $connection->queryExecute("UPDATE mf_file_delivery SET CREATED_AT=CREATED_AT - INTERVAL 11 MINUTE WHERE RECIPIENT='{$recipient}'");

    return ['aged' => $connection->getAffectedRowsCount()];
}

/** Bitrix calls it instead of mail(): the stand keeps the exact MIME message that msmtp would get. */
function custom_mail(string $to, string $subject, string $message, string $headers = '', string $parameters = '', mixed $context = null): bool
{
    static $sequence = 0;

    return false !== file_put_contents(sprintf('/runtime/j5-mail/%03d.eml', ++$sequence), 'To: ' . $to . "\n" . 'Subject: ' . $subject . "\n" . $headers . "\n\n" . $message);
}

/**
 * @return array{to: string, subject: string, key: null|string, attachments: list<array{name: string, contentType: string, bytes: int, sha256: string}>}
 */
function j5_letter(string $raw): array
{
    [$headers, $body] = preg_split("/\r?\n\r?\n/", $raw, 2) + [1 => ''];
    $leaves = j5_parts($headers, $body);
    $text = '';
    $attachments = [];
    foreach ($leaves as [$partHeaders, $partBody]) {
        $encoding = preg_match('/^Content-Transfer-Encoding:\s*([\w-]+)/im', $partHeaders, $match) ? strtolower($match[1]) : '8bit';
        $content = match ($encoding) {
            'base64' => (string)base64_decode(preg_replace('/\s+/', '', $partBody) ?? '', true),
            'quoted-printable' => quoted_printable_decode($partBody),
            default => $partBody,
        };
        if (1 === preg_match('/^Content-Disposition:\s*attachment;\s*filename="([^"]+)"/im', $partHeaders, $match)) {
            preg_match('/^Content-Type:\s*([^\r\n]+)/im', $partHeaders, $type);
            $attachments[] = [
                'name' => iconv_mime_decode($match[1], 0, 'UTF-8') ?: $match[1],
                'contentType' => trim($type[1] ?? ''),
                'bytes' => strlen($content),
                'sha256' => hash('sha256', $content),
            ];
        } else {
            $text .= $content;
        }
    }
    preg_match('/^To:\s*([^\r\n]+)/m', $headers, $to);
    preg_match('/^Subject:\s*([^\r\n]+(?:\r?\n[ \t][^\r\n]+)*)/m', $headers, $subject);

    return [
        'to' => trim($to[1] ?? ''),
        'subject' => iconv_mime_decode(preg_replace('/\r?\n[ \t]/', ' ', $subject[1] ?? '') ?? '', 0, 'UTF-8') ?: '',
        'key' => 1 === preg_match('~/orders/access/([a-f0-9]{64})~', $text, $match) ? $match[1] : null,
        'attachments' => $attachments,
    ];
}

/** @return list<array{0: string, 1: string}> leaf parts of a MIME entity as [headers, body] */
function j5_parts(string $headers, string $body): array
{
    if (1 !== preg_match('~^Content-Type:\s*multipart/~im', $headers) || 1 !== preg_match('/boundary="?([^";\r\n]+)"?/i', $headers, $match)) {
        return [[$headers, $body]];
    }
    $leaves = [];
    foreach (array_slice(explode('--' . $match[1], $body), 1) as $part) {
        if (str_starts_with($part, '--')) {
            break;
        }
        [$partHeaders, $partBody] = preg_split("/\r?\n\r?\n/", ltrim($part, "\r\n"), 2) + [1 => ''];
        array_push($leaves, ...j5_parts($partHeaders, rtrim($partBody, "\r\n")));
    }

    return $leaves;
}
