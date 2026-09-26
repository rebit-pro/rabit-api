<?php

declare(strict_types=1);

namespace Morefoto\Legal\Application\Consent\UseCase;

use Morefoto\Legal\Application\Consent\Service\ConsentRecorder;
use Morefoto\Legal\Application\Document\Dto\LegalDocumentOutputDto;
use Rebit\Share\Application\Contract\Consent\Dto\AcceptedDocumentDto;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;

/** Фиксирует, что уже работающий сотрудник принял действующие редакции своих документов из кабинета.
 * Повторное принятие той же редакции безопасно и не создаёт дублей в журнале.
 */
final readonly class AcceptStaffConsentsUseCase
{
    public function __construct(
        private ConsentRecorder $recorder,
        private GetPendingStaffConsentsUseCase $pending,
    ) {}

    /**
     * @param list<AcceptedDocumentDto> $accepted
     *
     * @return list<LegalDocumentOutputDto> что осталось принять
     */
    public function execute(int $userId, array $accepted): array
    {
        $this->recorder->record(ConsentContextEnum::STAFF, $userId, $accepted);

        return $this->pending->execute($userId);
    }
}
