<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

use Rebit\Share\Contracts\Media\Dto\ChildMoveInputDto;
use Rebit\Share\Contracts\Media\Dto\ChildSetOutputDto;

/**
 * Полные наборы детей и их атомарный перенос между группами одной съёмки.
 * Права сотрудника проверяет вызывающий модуль; поставщик не начинает и не завершает транзакцию.
 */
interface ChildTransferInterface
{
    /**
     * Читает наборы без блокировок. Совместные кадры считаются относительно детей вне $childIds.
     *
     * @param list<int> $childIds внутренние ID детей Media
     *
     * @return array<int, ChildSetOutputDto> по ID ребёнка; дети другой съёмки и отсутствующие пропускаются
     */
    public function sets(int $shootId, array $childIds): array;

    /**
     * Внутри транзакции вызывающего: блокирует ревизию медиа съёмки (сериализация с разметкой, обложкой и ссылками),
     * затем строки детей и читает их наборы как sets().
     *
     * @param list<int> $childIds
     *
     * @return array<int, ChildSetOutputDto>
     */
    public function lockSets(int $shootId, array $childIds): array;

    /**
     * Следующие свободные коды детей группы в порядке A…Z, AA…ZZZ.
     *
     * @return list<string>
     */
    public function freeCodes(int $groupId, int $count): array;

    /**
     * Внутри транзакции вызывающего после lockSets(): переносит детей со всеми кадрами, сохраняя ID,
     * заменяет перенесённые обложки и повышает ревизию медиа съёмки.
     *
     * @param non-empty-list<ChildMoveInputDto> $moves
     *
     * @return int новая ревизия медиа съёмки
     */
    public function move(int $shootId, array $moves): int;
}
