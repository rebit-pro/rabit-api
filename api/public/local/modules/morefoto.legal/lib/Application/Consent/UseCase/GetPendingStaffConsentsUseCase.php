<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Consent\UseCase;

use Morefoto\Legal\Application\Consent\Service\ConsentRecorder;
use Morefoto\Legal\Application\Document\Dto\LegalDocumentOutputDto;
use Morefoto\Legal\Application\Document\Mapper\LegalDocumentMapper;
use Morefoto\Legal\Domain\Consent\Repository\ConsentJournalInterface;
use Morefoto\Legal\Domain\Document\Service\ReconsentPolicy;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;

/** Сообщает кабинету, какие редакции документов сотрудник ещё не принял.
 * Нужен сотрудникам, приглашённым до появления согласия, и всем при выходе редакции с повторным принятием.
 */
final readonly class GetPendingStaffConsentsUseCase
{
    public function __construct(
        private ConsentRecorder $recorder,
        private ConsentJournalInterface $journal,
        private ReconsentPolicy $policy,
        private LegalDocumentMapper $mapper,
    ) {}

    /** @return list<LegalDocumentOutputDto> */
    public function execute(int $userId): array
    {
        return $this->mapper->documents($this->policy->pending(
            $this->recorder->required(ConsentContextEnum::STAFF),
            $this->journal->acceptedVersions(ConsentContextEnum::STAFF, $userId),
        ));
    }
}
