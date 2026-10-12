<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Application\Request\UseCase;

use Morefoto\Handoff\Application\Request\Service\StaffRequestWorkflow;

/**
 * Отзывает список детей сотрудников, который ещё не перенесён: автор передумал или список создан по ошибке.
 * Перенесённый список остаётся как история переноса кадров.
 */
final readonly class DeleteStaffRequestUseCase
{
    public function __construct(private StaffRequestWorkflow $workflow) {}

    public function execute(int $actorId, string $requestId): void
    {
        $this->workflow->remove($actorId, $requestId);
    }
}
