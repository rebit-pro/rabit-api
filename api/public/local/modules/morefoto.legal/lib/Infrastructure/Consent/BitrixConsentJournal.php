<?php

declare(strict_types=1);

namespace Morefoto\Legal\Infrastructure\Consent;

use Bitrix\Main\Application;
use Morefoto\Legal\Domain\Consent\Repository\ConsentJournalInterface;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;

/** Журнал mf_legal_consent; пишет в текущее соединение, поэтому попадает в транзакцию вызывающего сценария. */
final readonly class BitrixConsentJournal implements ConsentJournalInterface
{
    public function add(ConsentContextEnum $context, int $subjectId, array $documents, \DateTimeImmutable $acceptedAt): void
    {
        if ([] === $documents) {
            return;
        }
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $moment = $acceptedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $rows = [];
        foreach ($documents as $document) {
            $rows[] = "('" . $context->value . "'," . $subjectId . ",'" . $helper->forSql($document->code->value) . "','"
                . $helper->forSql($document->version) . "','" . $moment . "')";
        }
        $connection->queryExecute('INSERT IGNORE INTO mf_legal_consent(CONTEXT,SUBJECT_ID,DOCUMENT_CODE,DOCUMENT_VERSION,ACCEPTED_AT) VALUES'
            . implode(',', $rows));
    }

    public function acceptedVersions(ConsentContextEnum $context, int $subjectId): array
    {
        $result = Application::getConnection()->query("SELECT DOCUMENT_CODE,DOCUMENT_VERSION FROM mf_legal_consent WHERE CONTEXT='"
            . $context->value . "' AND SUBJECT_ID=" . $subjectId);
        $accepted = [];
        while (false !== ($row = $result->fetch())) {
            $accepted[(string)$row['DOCUMENT_CODE']][] = (string)$row['DOCUMENT_VERSION'];
        }

        return $accepted;
    }
}
