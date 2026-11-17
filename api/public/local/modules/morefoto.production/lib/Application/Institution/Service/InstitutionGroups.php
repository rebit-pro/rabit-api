<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\Service;

use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Morefoto\Production\Domain\Institution\ValueObject\InstitutionArchive;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Определяет текущую область учреждения и незакрытые группы через контракт владельца календаря.
 * Сверяет сохранённый выпуск перед публикацией; новая открытая съёмка не отнимает восстановление старой версии.
 * До первой успешной публикации проверяет весь текущий состав при каждом повторе независимо от поколения доступа.
 */
final readonly class InstitutionGroups
{
    public function __construct(private GroupDirectoryInterface $directory, private ArchivePolicy $policy) {}

    /** @return list<GroupDirectoryItemOutputDto> */
    public function all(string $institutionId): array
    {
        $groups = [];
        $page = 1;
        do {
            $result = $this->directory->page(new GroupDirectoryQueryInputDto(institutionIds: null, groupIds: null, institutionId: $institutionId, shootId: null, state: null, page: $page, pageSize: 100));
            foreach ($result->items as $group) {
                if ($group->institutionId !== $institutionId || isset($groups[$group->id])) {
                    throw new HttpException('COMPOSITION_CHANGED', 409);
                }
                $groups[$group->id] = $group;
            }
            ++$page;
        } while (100 === count($result->items));
        if (count($groups) !== $result->total) {
            throw new HttpException('COMPOSITION_CHANGED', 409);
        }
        ksort($groups, SORT_STRING);

        return array_values($groups);
    }

    public function deadline(GroupDirectoryItemOutputDto $group): ?\DateTimeImmutable
    {
        return null === $group->calendar->sentAt || null === $group->calendar->closesAt
            ? null : new \DateTimeImmutable($group->calendar->closesAt)->setTimezone(new \DateTimeZone('UTC'));
    }

    /** @param list<GroupDirectoryItemOutputDto> $groups
     * @return list<GroupDirectoryItemOutputDto>
     */
    public function blockers(array $groups, \DateTimeImmutable $now): array
    {
        return array_values(array_filter($groups, fn(GroupDirectoryItemOutputDto $group): bool => null === $this->deadline($group) || $this->deadline($group) > $now));
    }

    /** @param list<GroupDirectoryItemOutputDto> $groups */
    public function buildFrom(array $groups): ?\DateTimeImmutable
    {
        $latest = null;
        foreach ($groups as $group) {
            $deadline = $this->deadline($group);
            if (null === $deadline) {
                return null;
            }
            $latest = null === $latest || $deadline > $latest ? $deadline : $latest;
        }

        return null === $latest ? null : $this->policy->buildFrom($latest);
    }

    public function guard(InstitutionArchive $archive, \DateTimeImmutable $now): void
    {
        $current = [];
        foreach ($this->all($archive->institutionPublicId) as $group) {
            $current[$group->id] = $group;
        }
        if (null === $archive->firstReadyAt && count($current) !== count($archive->groups)) {
            throw new HttpException('COMPOSITION_CHANGED', 409);
        }
        foreach ($archive->groups as $saved) {
            $group = $current[$saved->id] ?? null;
            $deadline = null === $group ? null : $this->deadline($group);
            if (null === $group || $group->institutionNativeId !== $archive->institutionId || null === $deadline
                || $deadline->format('Y-m-d H:i:s') !== $saved->deadline) {
                throw new HttpException('COMPOSITION_CHANGED', 409);
            }
            if ($deadline > $now) {
                throw new HttpException('PRINT_SALES_OPEN', 409);
            }
            if ($this->policy->buildFrom($deadline) > $now) {
                throw new HttpException('BUILD_DELAY', 409);
            }
        }
    }
}
