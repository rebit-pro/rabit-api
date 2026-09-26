<?php

declare(strict_types=1);

namespace Morefoto\Media\Presentation\Photo\Dto;

use Morefoto\Media\Presentation\Photo\PhotoInputMapper;
use Morefoto\Media\Presentation\Photo\PhotoListInputMapper;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\FormField;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\MultipartFile;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Shared\Interface\RequestUploadDtoInterface;

#[MultipartFile(missingCode: 'ONE_PHOTO_REQUIRED', failedCode: 'PHOTO_UPLOAD_FAILED')]
final readonly class UploadPhotoRequestDto implements RequestUploadDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'shoot_id', pattern: PhotoListInputMapper::ID_PATTERN)]
        public string $shootId,
        public string $tmpName,
        public string $filename,
        public int $bytes,
        #[FormField(errorCode: 'GROUP_REQUIRED', pattern: PhotoListInputMapper::ID_PATTERN)]
        public string $groupId,
        #[FormField(errorCode: 'INVALID_FINGERPRINT')]
        public ?string $fingerprint,
        #[FormField(errorCode: 'INVALID_CHILD_CODES', pattern: PhotoInputMapper::CHILD_CODES_PATTERN)]
        public ?string $childCodes = null,
    ) {}
}
