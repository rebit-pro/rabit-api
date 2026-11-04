<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Link;

use Morefoto\Handoff\Application\Link\Dto\OpeningCommandInputDto;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use Morefoto\Handoff\Presentation\Link\Request\Dto\CancelGalleryOpeningRequestDto;
use Morefoto\Handoff\Presentation\Link\Request\Dto\ScheduleGalleryOpeningRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

/** Проверяет московское время открытия и переводит HTTP-команды в типизированный сценарий Handoff. */
final readonly class OpeningInputMapper
{
    public function schedule(ScheduleGalleryOpeningRequestDto $request): OpeningCommandInputDto
    {
        if (1 > $request->revision || 1 !== preg_match('/^[a-f0-9]{64}$/D', $request->signature)) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }
        if (!$request->confirmed) {
            throw new HttpException('CONFIRMATION_REQUIRED', 422);
        }
        $openAt = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $request->openAt);
        if (false === $openAt || $openAt->format('Y-m-d\TH:i:sP') !== $request->openAt || '+03:00' !== $openAt->format('P')
            || 1000 > (int)$openAt->format('Y') || 9999 < (int)$openAt->format('Y')) {
            throw new HttpException('INVALID_OPENING_TIME', 422);
        }

        return new OpeningCommandInputDto($request->revision, $request->signature, $openAt);
    }

    public function cancel(CancelGalleryOpeningRequestDto $request): OpeningCommandInputDto
    {
        if (1 > $request->revision) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        return new OpeningCommandInputDto($request->revision, '', null);
    }

    public function key(CancelGalleryOpeningRequestDto|ScheduleGalleryOpeningRequestDto $request): IdempotencyKey
    {
        return new IdempotencyKey($request->idempotencyKey);
    }
}
