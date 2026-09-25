<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Support;

use Rebit\Share\Application\Contract\Consent\ConsentRecorderInterface;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;
use Rebit\Share\Shared\Exception\HttpException;

/** Двойник контракта Consent: без принятых документов отвечает как реальный, иначе запоминает запись. */
final class RecordingConsents implements ConsentRecorderInterface
{
    /** @var list<array{ConsentContextEnum, int, int}> */
    public array $records = [];

    public function record(ConsentContextEnum $context, int $subjectId, array $accepted): void
    {
        if ([] === $accepted) {
            throw new HttpException('CONSENT_REQUIRED', 422);
        }
        $this->records[] = [$context, $subjectId, count($accepted)];
    }
}
