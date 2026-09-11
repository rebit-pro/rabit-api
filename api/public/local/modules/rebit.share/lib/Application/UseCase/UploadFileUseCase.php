<?php

declare(strict_types=1);

namespace Rebit\Share\Application\UseCase;

use Rebit\Share\Domain\File\Dto\Request\UploadRequestFileRequestDto;
use Rebit\Share\Domain\File\Dto\Result\UploadFileResultDto;
use Rebit\Share\Domain\File\Exception\FileUploadFailedException;
use Rebit\Share\Domain\File\Exception\InvalidFileException;
use Rebit\Share\Domain\File\Service\FileUploadService;

final readonly class UploadFileUseCase
{
    public function __construct(
        private FileUploadService $service,
    ) {}

    /**
     * @throws InvalidFileException
     * @throws FileUploadFailedException
     */
    public function handle(UploadRequestFileRequestDto $dto, int $userId): UploadFileResultDto
    {
        return $this->service->upload($dto, $userId);
    }
}
