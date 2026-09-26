<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\Dto;

use Rebit\Share\Application\Contract\Consent\Dto\AcceptedDocumentDto;

final readonly class AcceptInvitationInputDto
{
    /** @param list<AcceptedDocumentDto> $consents принятые сотрудником редакции документов */
    public function __construct(
        public string $token,
        public string $password,
        public array $consents,
    ) {}
}
