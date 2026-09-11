<?php

declare(strict_types=1);

namespace Rebit\Share\Domain\File\Dto\Request;

use Rebit\Share\Shared\Interface\RequestFileDtoInterface;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UploadRequestFileRequestDto implements RequestFileDtoInterface
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        public string $moduleId, // MODULE_ID для \CFile::SaveFile

        #[Assert\NotBlank]
        #[Assert\Type('string')]
        public string $name, // оригинальное имя файла

        #[Assert\NotBlank]
        #[Assert\Type('string')]
        public string $type, // MIME-type

        #[Assert\NotBlank]
        #[Assert\Type('string')]
        public string $tmpName, // путь к временному файлу (tmp_name)

        #[Assert\Positive]
        public int $size, // размер в байтах
    ) {}
}
