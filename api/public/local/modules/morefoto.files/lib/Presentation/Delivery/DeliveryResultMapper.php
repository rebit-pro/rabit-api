<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Delivery;

use Morefoto\Files\Application\Delivery\Dto\DeliveryOutputDto;
use Morefoto\Files\Application\Delivery\Dto\OrderEmailSummaryOutputDto;
use Morefoto\Files\Presentation\Delivery\Result\Dto\FileDeliveryResultDto;
use Morefoto\Files\Presentation\Delivery\Result\Dto\OrderEmailResultDto;

/** Даты — по Москве в ATOM, как во всех ответах заказа. */
final readonly class DeliveryResultMapper
{
    private const string TIMEZONE = 'Europe/Moscow';

    public function summary(OrderEmailSummaryOutputDto $output): OrderEmailResultDto
    {
        return new OrderEmailResultDto(
            enabled: $output->enabled,
            recipient: $output->recipient,
            latest: null === $output->latest ? null : $this->delivery($output->latest),
            canResend: $output->canResend,
            resendAvailableAt: $this->moment($output->resendAvailableAt),
        );
    }

    public function delivery(DeliveryOutputDto $output): FileDeliveryResultDto
    {
        return new FileDeliveryResultDto(
            id: $output->id,
            kind: $output->kind,
            status: $output->status,
            recipient: $output->recipient,
            createdAt: (string)$this->moment($output->createdAt),
            acceptedAt: $this->moment($output->acceptedAt),
            letters: $output->letters,
            acceptedLetters: $output->acceptedLetters,
            photos: $output->photos,
            linkOnly: $output->linkOnly,
        );
    }

    private function moment(?\DateTimeImmutable $moment): ?string
    {
        return $moment?->setTimezone(new \DateTimeZone(self::TIMEZONE))->format(\DateTimeInterface::ATOM);
    }
}
