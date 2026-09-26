<?php

declare(strict_types=1);

namespace Rebit\Share\Presentation\Consent;

use Rebit\Share\Application\Contract\Consent\Dto\AcceptedDocumentDto;
use Rebit\Share\Presentation\Consent\Dto\AcceptedDocumentRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

/** Переводит принятые документы из запроса во вход контракта Consent; соответствие редакциям проверяет получатель. */
final readonly class AcceptedDocumentInputMapper
{
    private const int LIMIT = 10;

    /**
     * @param array<mixed> $requests
     *
     * @return list<AcceptedDocumentDto>
     */
    public function documents(array $requests): array
    {
        if (!array_is_list($requests) || self::LIMIT < count($requests)) {
            throw new HttpException('INVALID_CONSENT', 422);
        }
        $documents = [];
        foreach ($requests as $request) {
            if (!$request instanceof AcceptedDocumentRequestDto || 64 < strlen($request->code) || 32 < strlen($request->version)) {
                throw new HttpException('INVALID_CONSENT', 422);
            }
            $documents[] = new AcceptedDocumentDto($request->code, $request->version);
        }

        return $documents;
    }
}
