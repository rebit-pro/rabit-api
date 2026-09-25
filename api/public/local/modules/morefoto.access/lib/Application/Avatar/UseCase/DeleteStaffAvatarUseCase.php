<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\UseCase;

use Morefoto\Access\Application\Avatar\Contract\AvatarStorageInterface;
use Morefoto\Access\Application\Avatar\Contract\StaffAvatarRepositoryInterface;
use Morefoto\Access\Application\Avatar\Service\AvatarAccess;
use Morefoto\Access\Domain\Avatar\Entity\StaffAvatar;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Удаляет аватар сотрудника, после чего кабинет снова показывает его инициалы; повторное удаление ничего не меняет.
 * Права те же, что у загрузки: свой — любой сотрудник с доступом, чужой — только управляющий сотрудниками.
 */
final readonly class DeleteStaffAvatarUseCase
{
    public function __construct(
        private AvatarAccess $access,
        private StaffAvatarRepositoryInterface $avatars,
        private AvatarStorageInterface $storage,
    ) {}

    /**
     * @throws HttpException
     */
    public function execute(int $actorUserId, int $userId): void
    {
        $this->access->assertCanChange($actorUserId, $userId);
        $this->avatars->locked($userId, function(?StaffAvatar $current) use ($userId): null {
            if (null !== $current) {
                $this->avatars->delete($userId);
            }

            return null;
        });
        $this->storage->prune($userId, null);
    }
}
