<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Commerce\Domain\Order\Service\OrderCalendarPolicy;
use Morefoto\Files\Application\Delivery\UseCase\DispatchOrderEmailsUseCase;
use Morefoto\Files\Application\Delivery\Contract\OrderEmailComposerInterface;
use Morefoto\Files\Application\Delivery\Service\OrderEmailSender;
use Morefoto\Files\Application\Files\Service\OrderFileAccess;
use Morefoto\Files\Domain\Delivery\Enum\DeliveryStatusEnum;
use Morefoto\Files\Domain\Delivery\Repository\DeliveryRepositoryInterface;
use Morefoto\Files\Domain\Delivery\Service\AttachmentPlanner;
use Morefoto\Files\Domain\Delivery\ValueObject\DeliveryPart;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Rebit\Notification\Application\Delivery\UseCase\DeliverEmailUseCase;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;
use Rebit\Share\Application\Contract\Notification\Dto\NotificationOperationOutputDto;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;
use Rebit\Share\Contracts\Commerce\OrderEmailLinkInterface;

// J2/J5: MySQL trail of the letters of the paid order — one automatic delivery per order confirmed after the release, its
// letters planned once and each queued once in H1 under its own deduplication key; H1 keeps only references to the originals
// and clears texts and references after the letter is accepted; the email key is a separate active key stored only as
// SHA-256, it lasts the files month and leaves the buyer key alone; a repeated dispatcher run adds nothing.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix') || !is_file('/runtime/j2-email.json')) {
    throw new RuntimeException('Order email verification requires the disposable test runtime and browser results.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
if (!Loader::includeModule('morefoto.files')) {
    throw new RuntimeException('Cannot load isolated MoreFoto Files module.');
}
$connection = Application::getConnection();
$sql = $connection->getSqlHelper();
$record = json_decode((string)file_get_contents('/runtime/j2-email.json'), true, flags: JSON_THROW_ON_ERROR);
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('J2 verification failed: ' . $message);
    }
};
$scalar = static fn(string $query): int => (int)current((array)$connection->query($query)->fetchRaw());
$order = $connection->query("SELECT ID,DATE_FORMAT(PAID_AT,'%Y-%m-%d %H:%i:%s') AS PAID_AT,PAID_RECORDED_AT,BUYER_EMAIL FROM mf_order WHERE PUBLIC_ID='"
    . $sql->forSql((string)$record['orderId']) . "'")->fetchRaw();
$check(is_array($order) && null !== $order['PAID_RECORDED_AT'], 'the paid order has its confirmation moment');
$orderId = (int)$order['ID'];

// The cron of the stand is this call: orders paid after the browser's own run get their letters first.
ServiceLocator::getInstance()->get(DispatchOrderEmailsUseCase::class)->execute(100);

// 1. One automatic letter for every paid order confirmed after the release, none for anything else.
$check(0 === $scalar("SELECT COUNT(*) FROM mf_order o LEFT JOIN mf_file_delivery d ON d.ORDER_ID=o.ID AND d.KIND='auto'
    WHERE o.PAYMENT_STATUS='paid' AND o.LATE_PAYMENT=0 AND o.PAID_RECORDED_AT IS NOT NULL AND d.ID IS NULL"), 'a paid order has no letter');
$check(0 === $scalar("SELECT COUNT(*) FROM mf_file_delivery d JOIN mf_order o ON o.ID=d.ORDER_ID WHERE d.KIND='auto'
    AND (o.PAYMENT_STATUS<>'paid' OR o.LATE_PAYMENT=1 OR o.PAID_RECORDED_AT IS NULL)"), 'a letter for an order that is not paid in time');
$check(0 === $scalar("SELECT COUNT(*) FROM (SELECT ORDER_ID FROM mf_file_delivery WHERE KIND='auto' GROUP BY ORDER_ID HAVING COUNT(*)>1) d"), 'two automatic letters');

// 2. The letter of the order is queued once in H1 under its own deduplication key and goes to the order email.
$letter = $connection->query("SELECT d.PUBLIC_ID,d.RECIPIENT,d.OPERATION_ID,n.CONSUMER_KEY,n.DEDUP_KEY,n.RECIPIENT AS TO_ADDRESS,n.MAX_ATTEMPTS
    FROM mf_file_delivery d JOIN b_rebit_notification_operation n ON n.ID=d.OPERATION_ID WHERE d.ORDER_ID={$orderId} AND d.KIND='auto' AND d.STATUS='queued'")->fetchRaw();
$check(is_array($letter) && 'files-order-email' === $letter['CONSUMER_KEY'] && 'order-email:' . $letter['PUBLIC_ID'] . ':1' === $letter['DEDUP_KEY'], 'the queued letter');
$check($order['BUYER_EMAIL'] === $letter['RECIPIENT'] && $letter['RECIPIENT'] === $letter['TO_ADDRESS'] && 10 === (int)$letter['MAX_ATTEMPTS'], 'the letter goes to the order email');

// 3. The email key is a separate active key, stored only as SHA-256 and lasting the files month; the buyer key is intact.
$key = (string)$record['emailKey'];
$until = new OrderCalendarPolicy()->filesAvailableUntil(new DateTimeImmutable((string)$order['PAID_AT'], new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$check(1 === $scalar("SELECT COUNT(*) FROM mf_order_access_key WHERE ORDER_ID={$orderId} AND KIND='email' AND REVOKED_AT IS NULL AND KEY_HASH='"
    . hash('sha256', $key) . "' AND EXPIRES_AT='{$until}'"), 'the email key and its files month');
$check(1 === $scalar("SELECT COUNT(*) FROM mf_order_access_key WHERE ORDER_ID={$orderId} AND KIND='buyer' AND REVOKED_AT IS NULL AND KEY_HASH='"
    . hash('sha256', (string)$record['buyerKey']) . "'"), 'the buyer key stays active');
$dump = '';
foreach (['mf_file_delivery', 'mf_file_delivery_part', 'mf_order_access_key', 'mf_order'] as $table) {
    $result = $connection->query('SELECT * FROM ' . $table);
    while (false !== ($row = $result->fetchRaw())) {
        $dump .= json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
$check(!str_contains($dump, $key), 'a raw email key is stored');

// 4. J5: the automatic delivery and the repeat each planned one letter per frame (the stand limit), every letter queued once
// under `order-email:<delivery>:<number>`, the first letter is the delivery operation; the same frames went both times.
$photos = [];
foreach ((array)$record['deliveries'] as $publicId) {
    $delivery = $connection->query("SELECT ID,LETTERS,OPERATION_ID,STATUS,LINK_ONLY FROM mf_file_delivery WHERE ORDER_ID={$orderId} AND PUBLIC_ID='"
        . $sql->forSql((string)$publicId) . "'")->fetchRaw();
    $check(is_array($delivery) && 'queued' === $delivery['STATUS'] && (int)$record['letters'] === (int)$delivery['LETTERS'], 'the planned letters of ' . $publicId);
    $check([] === json_decode((string)$delivery['LINK_ONLY'], true), 'a fixture frame is above the letter limit');
    $parts = $connection->query('SELECT p.NUMBER,p.FILES,p.BYTES,p.OPERATION_ID,n.DEDUP_KEY,n.STATUS,n.BODY,n.ATTACHMENTS FROM mf_file_delivery_part p
        JOIN b_rebit_notification_operation n ON n.ID=p.OPERATION_ID WHERE p.DELIVERY_ID=' . (int)$delivery['ID'] . ' ORDER BY p.NUMBER');
    $ids = [];
    while (false !== ($part = $parts->fetchRaw())) {
        $files = json_decode((string)$part['FILES'], true, flags: JSON_THROW_ON_ERROR);
        $check(1 === count($files) && (int)$files[0]['bytes'] === (int)$part['BYTES'] && str_starts_with((string)$files[0]['path'], '/'), 'a letter holds one frame by reference');
        $check('order-email:' . $publicId . ':' . $part['NUMBER'] === $part['DEDUP_KEY'], 'the deduplication key of a letter');
        // The letter is accepted: H1 no longer keeps its text, its link and the references to the originals.
        $check('accepted' === $part['STATUS'] && '' === $part['BODY'] && null === $part['ATTACHMENTS'], 'an accepted letter keeps its text or attachments');
        $ids[] = (string)$files[0]['photoId'];
        if (1 === (int)$part['NUMBER']) {
            $check($part['OPERATION_ID'] === $delivery['OPERATION_ID'], 'the delivery points to its first letter');
        }
    }
    $check((int)$record['letters'] === count($ids) && count($ids) === count(array_unique($ids)), 'every frame is in one letter');
    sort($ids);
    $photos[] = $ids;
}
$check(2 === count($photos) && $photos[0] === $photos[1], 'the repeat sends the same frames (J5-DEC-03)');
// Neither H1 nor the letters keep originals: only short references are stored.
$check(0 === $scalar("SELECT COUNT(*) FROM b_rebit_notification_operation WHERE CONSUMER_KEY='files-order-email' AND (LENGTH(BODY)>65535 OR LENGTH(ATTACHMENTS)>8192)"), 'H1 stores file bytes');
$check(0 === $scalar('SELECT COUNT(*) FROM mf_file_delivery_part WHERE LENGTH(FILES)>8192'), 'a letter stores file bytes');

// 5. A repeated run adds no delivery and no letter and keeps one H1 operation per letter.
$before = $scalar('SELECT COUNT(*) FROM mf_file_delivery') . ':' . $scalar('SELECT COUNT(*) FROM mf_file_delivery_part');
ServiceLocator::getInstance()->get(DispatchOrderEmailsUseCase::class)->execute(100);
$check($before === $scalar('SELECT COUNT(*) FROM mf_file_delivery') . ':' . $scalar('SELECT COUNT(*) FROM mf_file_delivery_part'), 'a repeated run added a letter');
$check(0 === $scalar("SELECT COUNT(*) FROM (SELECT DEDUP_KEY FROM b_rebit_notification_operation WHERE CONSUMER_KEY='files-order-email' GROUP BY DEDUP_KEY HAVING COUNT(*)>1) d"), 'a letter queued twice');
verifyDeliveryRecovery(ServiceLocator::getInstance(), $connection, $orderId, $check);
echo "J2 order email integration passed\n";

/** @param callable(bool, string): void $check */
function verifyDeliveryRecovery(ServiceLocator $services, Connection $connection, int $orderId, callable $check): void
{
    $files = $services->get(OrderFileAccess::class)->byOrderId($orderId)->files;
    $check(3 <= count($files), 'T04 requires at least three entitled originals');
    $original = array_values($files)[0];
    $check(str_starts_with($original->absolutePath, '/runtime/private/media/'), 'T04 must use the disposable media');
    $suffix = '.ops-t04-' . Uuid::uuid4()->toString() . '.jpg';
    $largePath = $original->absolutePath . $suffix;
    $largeRelative = $original->relativePath . $suffix;
    $bytes = AttachmentPlanner::FILE_BYTES + 1;
    $check(copy($original->absolutePath, $largePath), 'T04 copies the test original');
    $handle = fopen($largePath, 'r+b');
    if (false === $handle) {
        unlink($largePath);
        throw new RuntimeException('Cannot open the large T04 fixture.');
    }
    try {
        $check(ftruncate($handle, $bytes), 'T04 has a physical file above the attachment limit');
    } finally {
        fclose($handle);
    }
    $sql = $connection->getSqlHelper();
    $photo = $sql->forSql($original->photoId);
    try {
        $connection->queryExecute("UPDATE b_hlbd_mf_photo SET UF_BYTES={$bytes},UF_ORIGINAL_PATH='" . $sql->forSql($largeRelative) . "' WHERE UF_PUBLIC_ID='{$photo}'");
        clearstatcache(true, $largePath);
        $check(filesize($largePath) === $bytes, 'T04 metadata matches the large file');
        $deliveries = $services->get(DeliveryRepositoryInterface::class);
        $now = $services->get(ClockInterface::class)->now();
        $publicId = Uuid::uuid4()->toString();
        $check($deliveries->createManual($publicId, $orderId, 'ops.t04@example.invalid', bin2hex(random_bytes(32)), $now), 'T04 creates a separate test delivery');
        $delivery = $deliveries->find($publicId) ?? throw new RuntimeException('The T04 delivery is missing.');
        $notifications = new class($services->get(EmailNotificationInterface::class)) implements EmailNotificationInterface {
            private int $calls = 0;

            public function __construct(private readonly EmailNotificationInterface $real) {}

            public function queue(EmailNotificationInputDto $input): NotificationOperationOutputDto
            {
                if (2 === ++$this->calls) {
                    throw new RuntimeException('ops-t04-interrupted-before-second-letter');
                }

                return $this->real->queue($input);
            }

            public function status(string $operationId): ?NotificationOperationOutputDto
            {
                return $this->real->status($operationId);
            }
        };
        $sender = new OrderEmailSender(
            deliveries: $deliveries,
            links: $services->get(OrderEmailLinkInterface::class),
            access: $services->get(OrderFileAccess::class),
            planner: new AttachmentPlanner(1),
            composer: $services->get(OrderEmailComposerInterface::class),
            notifications: $notifications,
            clock: $services->get(ClockInterface::class),
            logger: new NullLogger(),
        );
        $interrupted = false;
        try {
            $sender->send($delivery);
        } catch (RuntimeException $error) {
            if ('ops-t04-interrupted-before-second-letter' !== $error->getMessage()) {
                throw $error;
            }
            $interrupted = true;
        }
        $check($interrupted, 'T04 reached the controlled failure between letters');
        $held = $deliveries->find($publicId) ?? throw new RuntimeException('The interrupted T04 delivery is missing.');
        $parts = $deliveries->parts($publicId);
        $queued = array_filter($parts, static fn(DeliveryPart $part): bool => null !== $part->operationId);
        $check(DeliveryStatusEnum::HELD === $held->status && 1 === count($queued), 'T04 preserves the first queued letter and the held delivery');
        $check(1 === count($held->linkOnly) && $held->linkOnly[0]->photoId === $original->photoId, 'T04 keeps the large original only by link');
        $check(count($files) - 1 === count($parts), 'T04 plans every smaller original exactly once');
        $before = json_encode([$held->linkOnly, array_map(static fn(DeliveryPart $part): array => $part->files, $parts)], JSON_THROW_ON_ERROR);
        $check(DeliveryStatusEnum::QUEUED === $services->get(OrderEmailSender::class)->send($held), 'T04 resumes the held delivery');
        $resumed = $deliveries->find($publicId) ?? throw new RuntimeException('The resumed T04 delivery is missing.');
        $parts = $deliveries->parts($publicId);
        $check($before === json_encode([$resumed->linkOnly, array_map(static fn(DeliveryPart $part): array => $part->files, $parts)], JSON_THROW_ON_ERROR), 'T04 reuses the exact persisted plan');
        $operationIds = array_map(static fn(DeliveryPart $part): ?string => $part->operationId, $parts);
        $check(!in_array(null, $operationIds, true) && count($parts) === count(array_unique($operationIds)), 'T04 queues every remaining part with its own operation');
        $operations = $connection->query("SELECT COUNT(*) AS TOTAL,COUNT(DISTINCT DEDUP_KEY) AS UNIQUE_KEYS FROM b_rebit_notification_operation WHERE CONSUMER_KEY='files-order-email' AND DEDUP_KEY LIKE 'order-email:{$publicId}:%'")->fetchRaw();
        $check(count($parts) === (int)$operations['TOTAL'] && (int)$operations['TOTAL'] === (int)$operations['UNIQUE_KEYS'], 'T04 creates no duplicate H1 operation');
        $check(DeliveryStatusEnum::QUEUED === $services->get(OrderEmailSender::class)->send($resumed), 'T04 repeated completion is harmless');
        foreach ($parts as $part) {
            $operation = $connection->query("SELECT BODY FROM b_rebit_notification_operation WHERE ID='" . $sql->forSql((string)$part->operationId) . "'")->fetchRaw();
            $check(str_contains((string)$operation['BODY'], $held->linkOnly[0]->name), 'T04 names the link-only original in every letter');
            $services->get(DeliverEmailUseCase::class)->execute((string)$part->operationId);
            $check('accepted' === $services->get(EmailNotificationInterface::class)->status((string)$part->operationId)?->status, 'T04 real H1 accepted the captured MIME');
        }
        $mail = glob('/runtime/ops-t04-mail/*.eml') ?: [];
        $check(count($parts) === count($mail), 'T04 captures one MIME per remaining original');
        foreach ($mail as $path) {
            $check(1 === preg_match_all('/^Content-Disposition:\s*attachment;/mi', (string)file_get_contents($path)), 'T04 sends one small original per MIME');
        }
        $proof = ['largeFileBytes' => $bytes, 'linkOnlyPhotos' => 1, 'queuedBeforeFailure' => 1, 'lettersAfterRecovery' => count($parts), 'capturedMimeLetters' => count($mail), 'duplicateOperations' => 0];
        file_put_contents('/runtime/ops-t04-verification.json', json_encode($proof, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        echo 'OPS T04 real recovery passed: ' . json_encode($proof, JSON_THROW_ON_ERROR) . "\n";
    } finally {
        $connection->queryExecute('UPDATE b_hlbd_mf_photo SET UF_BYTES=' . $original->bytes . ",UF_ORIGINAL_PATH='" . $sql->forSql($original->relativePath) . "' WHERE UF_PUBLIC_ID='{$photo}'");
        unlink($largePath);
    }
}

/** Bitrix mail is captured locally; the verifier never contacts SMTP. */
function custom_mail(string $to, string $subject, string $message, string $headers = '', string $parameters = '', mixed $context = null): bool
{
    static $sequence = 0;
    $directory = '/runtime/ops-t04-mail';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        return false;
    }

    return false !== file_put_contents(sprintf('%s/%03d.eml', $directory, ++$sequence), 'To: ' . $to . "\nSubject: " . $subject . "\n" . $headers . "\n\n" . $message);
}
