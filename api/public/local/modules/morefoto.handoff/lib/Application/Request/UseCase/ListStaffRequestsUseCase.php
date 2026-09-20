<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\UseCase;

use Morefoto\Handoff\Application\Request\Dto\StaffRequestListInputDto;
use Morefoto\Handoff\Application\Request\Service\StaffRequestWorkflow;

/**
 * Обслуживает получение списка служебных заявок с фильтрами и пагинацией.
 *
 * Возвращает только заявки из подтверждённой сервером области текущего сотрудника и доступные ему варианты формы.
 */
final readonly class ListStaffRequestsUseCase
{
    public function __construct(private StaffRequestWorkflow $workflow) {}

    /** @return array<string,mixed> */
    public function execute(int $actorId, StaffRequestListInputDto $input): array
    {
        return $this->workflow->list($actorId, $input);
    }
}
