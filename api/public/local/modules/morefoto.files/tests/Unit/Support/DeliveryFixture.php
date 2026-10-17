<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit\Support;

use Morefoto\Files\Application\Delivery\Service\DeliveryView;
use Morefoto\Files\Application\Delivery\Service\OrderEmailSender;
use Morefoto\Files\Application\Delivery\UseCase\DispatchOrderEmailsUseCase;
use Morefoto\Files\Application\Delivery\UseCase\GetFileDeliveryUseCase;
use Morefoto\Files\Application\Delivery\UseCase\GetOrderEmailUseCase;
use Morefoto\Files\Application\Delivery\UseCase\SendOrderEmailAgainUseCase;
use Morefoto\Files\Application\Files\Contract\DownloadIdGeneratorInterface;
use Morefoto\Files\Domain\Delivery\Service\AttachmentPlanner;
use Morefoto\Files\Domain\Delivery\Service\DeliveryPolicy;
use Morefoto\Files\Infrastructure\Mail\OrderEmailComposer;
use Psr\Log\NullLogger;
use Rebit\Share\Application\Contract\Notification\Dto\EmailNotificationInputDto;
use Rebit\Share\Application\Contract\Notification\Dto\NotificationOperationOutputDto;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;
use Rebit\Share\Contracts\Commerce\Dto\OrderEmailLinkOutputDto;
use Rebit\Share\Contracts\Commerce\Dto\OrderEmailOutputDto;
use Rebit\Share\Contracts\Commerce\OrderEmailLinkInterface;

/**
 * J2/J5: заказ 7 FilesFixture оплачен и подтверждён в 2027-02-01 10:00; H1 и Commerce подменены, правила, разбиение по
 * письмам и тексты — настоящие. H1 дедуплицирует по ключу, как b_rebit_notification_operation.
 */
final class DeliveryFixture implements OrderEmailLinkInterface, EmailNotificationInterface, DownloadIdGeneratorInterface
{
    public const string EMAIL = 'mama@example.test';
    public const string LINK_KEY = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public FilesFixture $files;
    public InMemoryDeliveries $deliveries;
    public bool $enabled = true;
    public bool $paid = true;
    public int $links = 0;
    /** @var list<EmailNotificationInputDto> */
    public array $queued = [];
    /** @var array<string, string> операция → статус H1 */
    public array $statuses = [];
    /** @var array<string, string> ключ дедупликации → операция */
    public array $operations = [];
    /** Ключ дедупликации, на котором H1 «падает» один раз: падение между письмами. */
    public ?string $failOn = null;
    /** Вызовы queue(), включая повторы с уже известным ключом и сбой. */
    public int $calls = 0;
    /** Лимит части для разбиения; по умолчанию — J5-DEC-01. */
    public int $partBytes = AttachmentPlanner::PART_BYTES;
    private int $sequence = 0;

    public function __construct()
    {
        $this->files = new FilesFixture();
        $this->deliveries = new InMemoryDeliveries();
    }

    public function dispatch(): DispatchOrderEmailsUseCase
    {
        return new DispatchOrderEmailsUseCase($this->deliveries, $this, $this->sender(), $this, new DeliveryPolicy(), $this->files->clock());
    }

    public function resend(): SendOrderEmailAgainUseCase
    {
        return new SendOrderEmailAgainUseCase($this->files->access(), $this, $this->deliveries, $this->sender(), new DeliveryView($this->deliveries, $this), $this, new DeliveryPolicy(), $this->files->clock());
    }

    public function summary(): GetOrderEmailUseCase
    {
        return new GetOrderEmailUseCase($this->files->access(), $this, $this->deliveries, new DeliveryView($this->deliveries, $this), new DeliveryPolicy());
    }

    public function delivery(): GetFileDeliveryUseCase
    {
        return new GetFileDeliveryUseCase($this->files->access(), $this->deliveries, new DeliveryView($this->deliveries, $this));
    }

    private function sender(): OrderEmailSender
    {
        return new OrderEmailSender(
            $this->deliveries,
            $this,
            $this->files->access(),
            new AttachmentPlanner($this->partBytes),
            new OrderEmailComposer('https://app.example.test/'),
            $this,
            $this->files->clock(),
            new NullLogger(),
        );
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function paidSince(\DateTimeImmutable $since, int $afterOrderId, int $limit): array
    {
        $recorded = new \DateTimeImmutable('2027-02-01 10:00:00', new \DateTimeZone('UTC'));
        $after = $recorded > $since || ($recorded == $since && 7 > $afterOrderId);

        return $this->paid && $after ? [$this->order()] : [];
    }

    public function find(int $orderId): ?OrderEmailOutputDto
    {
        return $this->paid && 7 === $orderId ? $this->order() : null;
    }

    public function link(int $orderId): OrderEmailLinkOutputDto
    {
        ++$this->links;

        return new OrderEmailLinkOutputDto(self::LINK_KEY, '2027-02-28 07:00:00');
    }

    public function queue(EmailNotificationInputDto $input): NotificationOperationOutputDto
    {
        ++$this->calls;
        if ($input->deduplicationKey === $this->failOn) {
            $this->failOn = null;
            throw new \RuntimeException('Notification storage is unavailable.');
        }
        $id = $this->operations[$input->deduplicationKey] ?? null;
        if (null === $id) {
            $this->queued[] = $input;
            $id = sprintf('cccccccc-cccc-4ccc-8ccc-%012d', count($this->queued));
            $this->operations[$input->deduplicationKey] = $id;
            $this->statuses[$id] = 'pending';
        }

        return new NotificationOperationOutputDto($id, $this->statuses[$id], 0, $input->maxAttempts, null, null);
    }

    public function status(string $operationId): ?NotificationOperationOutputDto
    {
        $status = $this->statuses[$operationId] ?? null;

        return null === $status ? null : new NotificationOperationOutputDto($operationId, $status, 1, 10, null, 'accepted' === $status ? '2027-02-10 09:01:00' : null);
    }

    public function uuid(): string
    {
        return sprintf('dddddddd-dddd-4ddd-8ddd-%012d', ++$this->sequence);
    }

    private function order(): OrderEmailOutputDto
    {
        return new OrderEmailOutputDto(7, FilesFixture::ORDER, 'MF-0007', self::EMAIL, '2027-01-31 07:00:00', '2027-02-01 10:00:00');
    }
}
