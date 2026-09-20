<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\UseCase;

use Morefoto\Handoff\Application\Request\Service\StaffRequestWorkflow;

/**
 * Предоставляет сценарию просмотра одну служебную заявку по публичному идентификатору.
 *
 * Перед возвратом карточки поручает workflow повторно проверить актуальную роль и область видимости сотрудника.
 */
final readonly class GetStaffRequestUseCase
{
    public function __construct(private StaffRequestWorkflow $workflow) {}

    /** @return array<string,mixed> */
    public function execute(int $actorId, string $requestId): array
    {
        return $this->workflow->detail($actorId, $requestId);
    }
}
