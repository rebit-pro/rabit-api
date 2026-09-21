<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\Service;

use Morefoto\Media\Domain\Gallery\Repository\GalleryCapabilityRepository;
use Morefoto\Media\Domain\Gallery\Repository\GalleryPhotoRepository;
use Morefoto\Media\Domain\Gallery\Service\GalleryAvailability;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;
use Rebit\Share\Contracts\Media\Dto\GalleryContextOutputDto;
use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Contracts\Organization\GalleryGroupInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Разрешает ключ галереи и собирает готовые назначения в пределах одной группы.
 * Подготовленная, но ещё не переданная галерея не раскрывает снимки.
 */
final readonly class GalleryAccess implements GalleryAccessInterface
{
    public function __construct(
        private GalleryCapabilityRepository $keys,
        private GalleryGroupInterface $groups,
        private GalleryPhotoRepository $photos,
        private GalleryAvailability $availability,
        private ClockInterface $clock,
    ) {}

    public function context(string $token): GalleryContextOutputDto
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new HttpException('GALLERY_NOT_FOUND', 404);
        }
        $key = $this->keys->find(hash('sha256', $token));
        if (false === $key) {
            throw new HttpException('GALLERY_NOT_FOUND', 404);
        }
        $group = $this->groups->get($key['GROUP_PUBLIC_ID']);
        $now = $this->clock->now();
        $state = $this->availability->state($group->sentAt, $group->closesAt, $now);

        return new GalleryContextOutputDto($group, $state, $now->format(DATE_ATOM), (int)$key['REVISION']);
    }

    public function resolve(string $token): GalleryAccessOutputDto
    {
        $context = $this->context($token);
        $group = $context->group;
        $state = $context->state;
        $assignments = [];
        if ('preparing' !== $state) {
            $result = $this->photos->list($group->id, $group->shootId);
            while (false !== ($row = $result->fetch())) {
                if (5000 === count($assignments)) {
                    throw new HttpException('GALLERY_TOO_LARGE', 409);
                }
                $assignments[] = new GalleryAssignmentOutputDto(
                    (string)$row['ASSIGNMENT_ID'],
                    (string)$row['PHOTO_ID'],
                    (string)$row['CHILD_ID'],
                    (int)$row['NATIVE_CHILD_ID'],
                    (string)$row['CODE'],
                    (string)$row['CODE'] . str_pad((string)$row['SEQUENCE_NO'], 3, '0', STR_PAD_LEFT),
                    (int)$row['UF_WIDTH'],
                    (int)$row['UF_HEIGHT'],
                    (int)$row['UF_REVISION'],
                );
            }
        }

        return new GalleryAccessOutputDto($group, $state, $context->referenceNow, $context->capabilityRevision, $assignments);
    }
}
