<?php

declare(strict_types=1);

namespace Morefoto\Organization\Infrastructure\Handoff;

use Bitrix\Main\Application;
use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Application\Calendar\Service\CalendarProjection;
use Morefoto\Organization\Domain\Calendar\ValueObject\GroupCalendar;
use Morefoto\Organization\Domain\Structure\Exception\StructureStorageException;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryPageOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;

final readonly class GroupDirectory implements GroupDirectoryInterface
{
    private const string UUID = '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D';
    private const string FIELDS = "g.ID,g.UF_PUBLIC_ID,g.UF_NAME,g.UF_KIND,g.UF_TIMEZONE,
        DATE_FORMAT(g.UF_SENT_AT,'%Y-%m-%d %H:%i:%s') AS SENT_AT,
        DATE_FORMAT(g.UF_CLOSES_AT,'%Y-%m-%d %H:%i:%s') AS CLOSES_AT,
        DATE_FORMAT(g.UF_DELIVERY_DUE_AT,'%Y-%m-%d %H:%i:%s') AS DELIVERY_DUE_AT,
        s.ID AS SHOOT_ID,s.UF_PUBLIC_ID AS SHOOT_PUBLIC_ID,s.UF_NAME AS SHOOT_NAME,
        i.ID AS INSTITUTION_ID,i.UF_PUBLIC_ID AS INSTITUTION_PUBLIC_ID,i.UF_NAME AS INSTITUTION_NAME";
    private const string SOURCE = ' FROM b_hlbd_mf_group g INNER JOIN b_hlbd_mf_shoot s ON s.ID=g.UF_SHOOT_ID INNER JOIN b_hlbd_mf_institution i ON i.ID=s.UF_INSTITUTION_ID';

    public function __construct(private CalendarClockInterface $clock) {}

    public function page(GroupDirectoryQueryInputDto $query): GroupDirectoryPageOutputDto
    {
        if (1 > $query->page || 1 > $query->pageSize || 100 < $query->pageSize) {
            throw new \InvalidArgumentException('Invalid group directory page.');
        }
        $now = $this->clock->now();
        $where = $this->where($query, $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        $offset = ($query->page - 1) * $query->pageSize;
        try {
            $connection = Application::getConnection();
            $count = $connection->query('SELECT COUNT(*) AS TOTAL' . self::SOURCE . ' WHERE ' . $where)->fetch();
            $result = $connection->query('SELECT ' . self::FIELDS . self::SOURCE . ' WHERE ' . $where
                . " ORDER BY s.UF_CREATED_AT DESC,s.ID DESC,g.UF_NAME ASC,g.ID ASC LIMIT {$query->pageSize} OFFSET {$offset}");
            $items = [];
            while (false !== ($row = $result->fetch())) {
                $items[] = $this->item($row, $now);
            }
        } catch (\Throwable $error) {
            throw new StructureStorageException('Cannot read the group directory.', 0, $error);
        }

        return new GroupDirectoryPageOutputDto($items, false === $count ? 0 : (int)$count['TOTAL']);
    }

    public function find(string $groupId): ?GroupDirectoryItemOutputDto
    {
        if (1 !== preg_match(self::UUID, $groupId)) {
            return null;
        }
        try {
            $row = Application::getConnection()->query('SELECT ' . self::FIELDS . self::SOURCE . " WHERE g.UF_PUBLIC_ID='{$groupId}'")->fetch();
        } catch (\Throwable $error) {
            throw new StructureStorageException('Cannot read the group directory.', 0, $error);
        }

        return false === $row ? null : $this->item($row, $this->clock->now());
    }

    private function where(GroupDirectoryQueryInputDto $query, string $now): string
    {
        $conditions = [match ($query->state) {
            null => '1=1',
            'preparing' => 'g.UF_SENT_AT IS NULL',
            'open' => "g.UF_SENT_AT IS NOT NULL AND g.UF_CLOSES_AT>'{$now}'",
            'closed' => "g.UF_CLOSES_AT<='{$now}'",
            default => throw new \InvalidArgumentException('Unsupported group state filter.'),
        }];
        if (null !== $query->institutionIds) {
            $conditions[] = 'i.ID IN (' . $this->ids($query->institutionIds) . ')';
        }
        if (null !== $query->groupIds) {
            $conditions[] = 'g.ID IN (' . $this->ids($query->groupIds) . ')';
        }
        if (null !== $query->institutionId) {
            $conditions[] = "i.UF_PUBLIC_ID='" . $this->uuid($query->institutionId) . "'";
        }
        if (null !== $query->shootId) {
            $conditions[] = "s.UF_PUBLIC_ID='" . $this->uuid($query->shootId) . "'";
        }

        return implode(' AND ', $conditions);
    }

    /** @param list<int> $ids */
    private function ids(array $ids): string
    {
        $positive = array_filter($ids, static fn(int $id): bool => 0 < $id);

        return [] === $positive ? '0' : implode(',', $positive);
    }

    private function uuid(string $value): string
    {
        if (1 !== preg_match(self::UUID, $value)) {
            throw new \InvalidArgumentException('Invalid directory filter.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function item(array $row, \DateTimeImmutable $now): GroupDirectoryItemOutputDto
    {
        $calendar = GroupCalendar::fromStorage($this->instant($row['SENT_AT']), $this->instant($row['CLOSES_AT']), $this->instant($row['DELIVERY_DUE_AT']), (string)$row['UF_TIMEZONE']);

        return new GroupDirectoryItemOutputDto(
            nativeId: (int)$row['ID'],
            id: (string)$row['UF_PUBLIC_ID'],
            name: (string)$row['UF_NAME'],
            kind: (string)$row['UF_KIND'],
            institutionNativeId: (int)$row['INSTITUTION_ID'],
            institutionId: (string)$row['INSTITUTION_PUBLIC_ID'],
            institutionName: (string)$row['INSTITUTION_NAME'],
            shootNativeId: (int)$row['SHOOT_ID'],
            shootId: (string)$row['SHOOT_PUBLIC_ID'],
            shootName: (string)$row['SHOOT_NAME'],
            calendar: CalendarProjection::create($calendar, $now),
        );
    }

    private function instant(mixed $value): ?string
    {
        return null === $value ? null : (string)$value;
    }
}
