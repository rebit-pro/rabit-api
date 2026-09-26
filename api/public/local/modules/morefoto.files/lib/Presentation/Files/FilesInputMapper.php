<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Files;

use Morefoto\Files\Application\Files\Dto\RequestDownloadInputDto;
use Morefoto\Files\Domain\Download\Enum\DownloadKindEnum;
use Morefoto\Files\Presentation\Files\Request\Dto\CreateDownloadRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class FilesInputMapper
{
    private const string UUID = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D';

    public function create(CreateDownloadRequestDto $request): RequestDownloadInputDto
    {
        if (1 !== preg_match('/^[a-fA-F0-9]{32}$/D', $request->idempotencyKey)) {
            throw new HttpException('INVALID_IDEMPOTENCY_KEY', 422);
        }
        $kind = DownloadKindEnum::tryFrom($request->kind);
        if (null === $kind) {
            throw new HttpException('INVALID_DOWNLOAD', 422, null, ['kind' => 'Use file or zip.']);
        }
        $photoIds = null;
        if (null !== $request->photoIds) {
            $photoIds = [];
            foreach ($request->photoIds as $photoId) {
                if (!is_string($photoId) || 1 !== preg_match(self::UUID, $photoId)) {
                    throw new HttpException('INVALID_DOWNLOAD', 422, null, ['photoIds' => 'Photo IDs must be UUID strings.']);
                }
                $photoIds[] = $photoId;
            }
        }

        return new RequestDownloadInputDto($kind, $photoIds, strtolower($request->idempotencyKey));
    }
}
