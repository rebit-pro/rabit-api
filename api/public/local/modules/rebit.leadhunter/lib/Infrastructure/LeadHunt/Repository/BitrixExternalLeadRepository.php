<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Infrastructure\LeadHunt\Repository;

use Bitrix\Main\Type\DateTime;
use Rebit\Leadhunter\Application\LeadHunt\Dto\ExternalLeadDto;
use Rebit\Leadhunter\Application\LeadHunt\Dto\PendingLeadDto;
use Rebit\Leadhunter\Application\LeadHunt\Port\ExternalLeadRepositoryInterface;
use Rebit\Leadhunter\Domain\LeadHunt\Entity\Table\ExternalLeadTable;
use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadSourceEnum;
use Rebit\Leadhunter\Domain\LeadHunt\Enum\LeadStatusEnum;

/**
 * Хранилище внешних заявок на Bitrix ORM (таблица rebit_leadhunter_external_lead).
 */
final readonly class BitrixExternalLeadRepository implements ExternalLeadRepositoryInterface
{
    private const string KEYWORDS_SEPARATOR = ',';

    public function findExistingGuids(LeadSourceEnum $source, array $guids): array
    {
        if ([] === $guids) {
            return [];
        }

        $rows = ExternalLeadTable::getList([
            'select' => ['UF_GUID'],
            'filter' => [
                '=UF_SOURCE' => $source->value,
                '@UF_GUID' => $guids,
            ],
        ])->fetchAll();

        return array_column($rows, 'UF_GUID');
    }

    public function add(ExternalLeadDto $lead, array $matchedKeywords): void
    {
        $now = new DateTime();

        ExternalLeadTable::add([
            'UF_SOURCE' => $lead->source->value,
            'UF_GUID' => $lead->guid,
            'UF_TITLE' => mb_substr($lead->title, 0, 500),
            'UF_DESCRIPTION' => $lead->description,
            'UF_URL' => mb_substr($lead->url, 0, 500),
            'UF_MATCHED_KEYWORDS' => mb_substr(implode(self::KEYWORDS_SEPARATOR, $matchedKeywords), 0, 500),
            'UF_STATUS' => LeadStatusEnum::PENDING->value,
            'UF_ATTEMPTS' => 0,
            'UF_PUBLISHED_AT' => null !== $lead->publishedAt
                ? DateTime::createFromTimestamp($lead->publishedAt->getTimestamp())
                : null,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);
    }

    public function findPending(int $limit, int $maxAttempts): array
    {
        $rows = ExternalLeadTable::getList([
            'filter' => [
                '@UF_STATUS' => [LeadStatusEnum::PENDING->value, LeadStatusEnum::FAILED->value],
                '<UF_ATTEMPTS' => $maxAttempts,
            ],
            'order' => ['ID' => 'ASC'],
            'limit' => $limit,
        ])->fetchAll();

        return array_map(
            static fn(array $row): PendingLeadDto => new PendingLeadDto(
                id: (int)$row['ID'],
                source: LeadSourceEnum::from((string)$row['UF_SOURCE']),
                title: (string)$row['UF_TITLE'],
                description: (string)$row['UF_DESCRIPTION'],
                url: (string)$row['UF_URL'],
                matchedKeywords: '' !== (string)$row['UF_MATCHED_KEYWORDS']
                    ? explode(self::KEYWORDS_SEPARATOR, (string)$row['UF_MATCHED_KEYWORDS'])
                    : [],
                attempts: (int)$row['UF_ATTEMPTS'],
            ),
            $rows,
        );
    }

    public function markSent(int $id): void
    {
        ExternalLeadTable::update($id, [
            'UF_STATUS' => LeadStatusEnum::SENT->value,
            'UF_UPDATED_AT' => new DateTime(),
        ]);
    }

    public function markFailed(int $id, int $attempts): void
    {
        ExternalLeadTable::update($id, [
            'UF_STATUS' => LeadStatusEnum::FAILED->value,
            'UF_ATTEMPTS' => $attempts,
            'UF_UPDATED_AT' => new DateTime(),
        ]);
    }

    public function deleteOlderThanDays(int $days): void
    {
        $threshold = DateTime::createFromTimestamp(time() - $days * 86400);

        $rows = ExternalLeadTable::getList([
            'select' => ['ID'],
            'filter' => ['<UF_CREATED_AT' => $threshold],
        ])->fetchAll();

        foreach ($rows as $row) {
            ExternalLeadTable::delete((int)$row['ID']);
        }
    }
}
