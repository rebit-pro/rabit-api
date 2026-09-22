<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\Service;

use Morefoto\Media\Application\Gallery\Dto\GalleryCapabilityOutputDto;
use Morefoto\Media\Domain\Gallery\Repository\GalleryCapabilityRepository;
use Rebit\Share\Contracts\Organization\GalleryGroupInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Выдаёт и отзывает непредсказуемые ключи приватной галереи для сценария передачи ссылки.
 * Не активирует продажи: состояние определяется фактическими сроками группы, которыми управляет Organization.
 */
final readonly class GalleryCapabilityLifecycle
{
    public function __construct(private GalleryCapabilityRepository $keys, private GalleryGroupInterface $groups) {}

    public function issue(string $groupId): GalleryCapabilityOutputDto
    {
        $group = $this->groups->get($groupId);
        $token = bin2hex(random_bytes(32));
        $this->keys->issue($group->publicId, hash('sha256', $token), $token);

        return new GalleryCapabilityOutputDto($token, 1);
    }

    public function revoke(string $token, int $revision): void
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/D', $token) || 1 > $revision) {
            throw new HttpException('GALLERY_NOT_FOUND', 404);
        }
        $this->keys->revoke(hash('sha256', $token), $revision);
    }
}
