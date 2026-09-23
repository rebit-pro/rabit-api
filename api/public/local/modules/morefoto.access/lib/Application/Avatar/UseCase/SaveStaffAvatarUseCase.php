<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\UseCase;

use Morefoto\Access\Application\Avatar\Contract\AvatarInspectorInterface;
use Morefoto\Access\Application\Avatar\Contract\AvatarRendererInterface;
use Morefoto\Access\Application\Avatar\Contract\AvatarStorageInterface;
use Morefoto\Access\Application\Avatar\Contract\StaffAvatarRepositoryInterface;
use Morefoto\Access\Application\Avatar\Dto\InspectedAvatarDto;
use Morefoto\Access\Application\Avatar\Dto\RenderedAvatarDto;
use Morefoto\Access\Application\Avatar\Dto\SavedAvatarOutputDto;
use Morefoto\Access\Application\Avatar\Dto\UploadedAvatarInputDto;
use Morefoto\Access\Application\Avatar\Mapper\AvatarOutputMapper;
use Morefoto\Access\Application\Avatar\Service\AvatarAccess;
use Morefoto\Access\Domain\Avatar\Entity\StaffAvatar;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Делает загруженное фото аватаром сотрудника: проверяет файл, строит квадратные WebP 256 и 64 и публикует новую
 * версию; тот же файл повторно версию не меняет. Права доступа и сессии сотрудника при этом не затрагиваются.
 */
final readonly class SaveStaffAvatarUseCase
{
    public function __construct(
        private AvatarAccess $access,
        private StaffAvatarRepositoryInterface $avatars,
        private AvatarInspectorInterface $inspector,
        private AvatarRendererInterface $renderer,
        private AvatarStorageInterface $storage,
        private AvatarOutputMapper $output,
    ) {}

    /**
     * @throws HttpException
     */
    public function execute(int $actorUserId, int $userId, UploadedAvatarInputDto $upload): SavedAvatarOutputDto
    {
        $this->access->assertCanChange($actorUserId, $userId);
        $inspected = $this->inspector->inspect($upload);
        $current = $this->avatars->find($userId);
        if (null !== $current && $current->hasContent($inspected->fingerprint)) {
            return $this->saved($current);
        }
        $rendered = $this->renderer->render($inspected);
        $avatar = $this->avatars->locked(
            $userId,
            fn(?StaffAvatar $current): StaffAvatar => $this->publish($actorUserId, $userId, $current, $inspected, $rendered),
        );
        // Files of older versions go only after the new row is committed; a failed save leaves a harmless orphan
        // that the next successful save of this employee overwrites or prunes.
        $this->storage->prune($userId, $avatar->version);

        return $this->saved($avatar);
    }

    private function publish(
        int $actorUserId,
        int $userId,
        ?StaffAvatar $current,
        InspectedAvatarDto $inspected,
        RenderedAvatarDto $rendered,
    ): StaffAvatar {
        if (null !== $current && $current->hasContent($inspected->fingerprint)) {
            return $current;
        }
        $avatar = new StaffAvatar(
            userId: $userId,
            version: (null === $current ? 0 : $current->version) + 1,
            fingerprint: $inspected->fingerprint,
            mimeType: $inspected->mimeType,
            bytes: $inspected->bytes,
            width: $inspected->width,
            height: $inspected->height,
        );
        $this->storage->write($userId, $avatar->version, $rendered);
        $this->avatars->save($avatar, $actorUserId);

        return $avatar;
    }

    private function saved(StaffAvatar $avatar): SavedAvatarOutputDto
    {
        $output = $this->output->map($avatar->userId, $avatar->version);
        if (null === $output) {
            throw new \LogicException('A stored avatar always has a positive version.');
        }

        return new SavedAvatarOutputDto(userId: $avatar->userId, avatar: $output);
    }
}
