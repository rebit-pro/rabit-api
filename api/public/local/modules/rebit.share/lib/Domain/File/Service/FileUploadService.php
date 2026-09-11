<?php

declare(strict_types=1);

namespace Rebit\Share\Domain\File\Service;

use Rebit\Share\Domain\File\Dto\Request\UploadRequestFileRequestDto;
use Rebit\Share\Domain\File\Dto\Result\UploadFileResultDto;
use Rebit\Share\Domain\File\Exception\FileUploadFailedException;
use Rebit\Share\Domain\File\Exception\InvalidFileException;

final readonly class FileUploadService
{
    public function __construct(
        private UploadedFileOwnershipService $ownershipService,
    ) {}

    /**
     * @throws InvalidFileException
     * @throws FileUploadFailedException
     */
    public function upload(UploadRequestFileRequestDto $dto, int $userId): UploadFileResultDto
    {
        if ($dto->size <= 0 || !is_file($dto->tmpName) || !is_readable($dto->tmpName)) {
            throw new InvalidFileException('Некорректный временный файл.');
        }

        $fileArray = [
            'name' => $dto->name,
            'type' => $dto->type,
            'tmp_name' => $dto->tmpName,
            'size' => $dto->size,
            'error' => 0,
        ];

        $fileId = (int)\CFile::SaveFile($fileArray, $dto->moduleId);
        if ($fileId <= 0) {
            throw new FileUploadFailedException('Ошибка загрузки файла(ов).');
        }

        $this->ownershipService->remember($fileId, $userId, $dto->moduleId);

        $src = (string)\CFile::GetPath($fileId);

        return new UploadFileResultDto(
            id: $fileId,
            name: $dto->name,
            size: $dto->size,
            type: $dto->type,
            src: $src,
        );
    }
}
