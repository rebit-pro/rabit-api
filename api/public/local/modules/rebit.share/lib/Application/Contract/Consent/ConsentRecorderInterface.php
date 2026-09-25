<?php

declare(strict_types=1);

namespace Rebit\Share\Application\Contract\Consent;

use Rebit\Share\Application\Contract\Consent\Dto\AcceptedDocumentDto;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;
use Rebit\Share\Shared\Exception\HttpException;

/** Фиксирует принятие юридических документов в сценарии модуля-потребителя внутри его транзакции. */
interface ConsentRecorderInterface
{
    /**
     * Принимает запись, только если присланы все действующие версии документов контекста.
     *
     * @param list<AcceptedDocumentDto> $accepted
     *
     * @throws HttpException CONSENT_REQUIRED (422) с действующими версиями в details.documents
     */
    public function record(ConsentContextEnum $context, int $subjectId, array $accepted): void;
}
