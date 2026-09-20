<?php

declare(strict_types=1);

namespace Rebit\Notification\Application\Lead\UseCase;

use Rebit\Notification\Application\Lead\Dto\LeadAttachmentDto;
use Rebit\Notification\Application\Lead\Dto\Request\SubmitLeadRequestDto;
use Rebit\Notification\Application\Lead\Dto\Result\SubmitLeadResultDto;
use Rebit\Notification\Application\Lead\Port\MosDizelLeadNotifierInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Принимает заявку mos-dizel.ru через изолированный канал доставки.
 */
final readonly class SubmitMosDizelLeadUseCase
{
    private SubmitLeadUseCase $delegate;

    public function __construct(MosDizelLeadNotifierInterface $notifier)
    {
        $this->delegate = new SubmitLeadUseCase($notifier);
    }

    /**
     * @throws HttpException
     */
    public function execute(SubmitLeadRequestDto $dto, ?LeadAttachmentDto $attachment = null): SubmitLeadResultDto
    {
        return $this->delegate->execute($dto, $attachment);
    }
}
