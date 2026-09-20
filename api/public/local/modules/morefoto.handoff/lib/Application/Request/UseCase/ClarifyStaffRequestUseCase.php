<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\UseCase;

use Morefoto\Handoff\Application\Request\Dto\ClarificationInputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestMutationOutputDto;
use Morefoto\Handoff\Application\Request\Service\StaffRequestWorkflow;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;

/**
 * Обслуживает возврат служебной заявки автору для исправления данных.
 *
 * Проверяет через workflow полномочия куратора, явное подтверждение, revision и сохраняет причину в истории.
 */
final readonly class ClarifyStaffRequestUseCase
{
    public function __construct(private StaffRequestWorkflow $workflow) {}

    public function execute(int $actorId, string $requestId, IdempotencyKey $key, ClarificationInputDto $input): StaffRequestMutationOutputDto
    {
        return $this->workflow->clarify($actorId, $requestId, $key, $input);
    }
}
