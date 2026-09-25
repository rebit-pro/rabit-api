<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Context;

use Morefoto\Support\Application\Question\Contract\GalleryQuestionContextInterface;
use Morefoto\Support\Application\Question\Dto\GalleryQuestionContextDto;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;

/** Галерея по ссылке (Media), учреждение группы (Organization) и её куратор (Access) — только через публичные контракты. */
final readonly class GalleryQuestionContext implements GalleryQuestionContextInterface
{
    public function __construct(
        private GalleryAccessInterface $galleries,
        private GroupDirectoryInterface $directory,
        private InstitutionAccessInterface $institutions,
    ) {}

    public function resolve(string $galleryToken): GalleryQuestionContextDto
    {
        $group = $this->galleries->context($galleryToken)->group;
        $item = $this->directory->find($group->publicId);
        $curator = null === $item ? null : ($this->institutions->assignments([$item->institutionNativeId])[$item->institutionNativeId]->curatorName ?? null);

        return new GalleryQuestionContextDto($group->id, $group->institutionName, $group->groupName, $curator);
    }
}
