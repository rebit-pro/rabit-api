<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Link\Service;

use Morefoto\Handoff\Domain\Link\Enum\LinkActionEnum;

/**
 * Применяет матрицу D08 к ссылкам групп: кто видит группу и кто проверяет, передаёт или исправляет дату передачи.
 * Группа вне области неотличима от несуществующей, а запрещённое действие в своей области отклоняется явно.
 */
final readonly class LinkPermissionPolicy
{
    /**
     * @param list<int> $institutionIds assigned institutions of a curator or head
     * @param list<int> $groupIds       assigned groups of a teacher
     */
    public function visible(string $role, array $institutionIds, array $groupIds, int $institutionId, int $groupId): bool
    {
        return match ($role) {
            'organizer' => true,
            'curator', 'head' => in_array($institutionId, $institutionIds, true),
            'teacher' => in_array($groupId, $groupIds, true),
            default => false,
        };
    }

    public function allows(string $role, LinkActionEnum $action): bool
    {
        return in_array($role, match ($action) {
            LinkActionEnum::READ => ['organizer', 'curator', 'head', 'teacher'],
            LinkActionEnum::PREPARE => ['organizer'],
            LinkActionEnum::TRANSMIT => ['organizer', 'curator', 'teacher'],
            LinkActionEnum::CORRECT => ['organizer', 'curator'],
        }, true);
    }

    /**
     * @param list<int> $institutionIds
     * @param list<int> $groupIds
     *
     * @return array{0: null|list<int>, 1: null|list<int>} institution and group restriction; null means unrestricted
     */
    public function scope(string $role, array $institutionIds, array $groupIds): array
    {
        return match ($role) {
            'organizer' => [null, null],
            'curator', 'head' => [$institutionIds, null],
            'teacher' => [null, $groupIds],
            default => [[], []],
        };
    }
}
