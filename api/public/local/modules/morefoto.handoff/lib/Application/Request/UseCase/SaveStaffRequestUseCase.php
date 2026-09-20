<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\UseCase;

use Morefoto\Handoff\Application\Request\Dto\StaffRequestMutationInputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestMutationOutputDto;
use Morefoto\Handoff\Application\Request\Service\StaffRequestWorkflow;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;

/**
 * Реализует создание нового списка сотрудника и повторную подачу списка после уточнения.
 *
 * Делегирует workflow проверку автора, области, ребёнка, optimistic lock и идемпотентное сохранение истории.
 */
final readonly class SaveStaffRequestUseCase
{
    public function __construct(private StaffRequestWorkflow $workflow) {}

    public function execute(int $actorId, ?string $requestId, IdempotencyKey $key, StaffRequestMutationInputDto $input): StaffRequestMutationOutputDto
    {
        return $this->workflow->save($actorId, $requestId, $key, $input);
    }
}
