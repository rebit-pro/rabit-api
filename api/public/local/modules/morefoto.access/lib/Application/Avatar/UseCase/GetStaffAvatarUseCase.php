<?php

declare(strict_types=1);

namespace Morefoto\Access\Application\Avatar\UseCase;

use Morefoto\Access\Application\Avatar\Contract\AvatarStorageInterface;
use Morefoto\Access\Application\Avatar\Contract\StaffAvatarRepositoryInterface;
use Morefoto\Access\Application\Avatar\Service\AvatarAccess;
use Morefoto\Access\Domain\Avatar\Enum\AvatarVariantEnum;
use Rebit\Share\Application\Contract\File\Dto\ImageContentOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Отдаёт вариант аватара сотрудника (64 или 256) любому сотруднику с доступом и только для текущей версии:
 * адрес с версией кешируется браузером навсегда, а устаревшая версия отвечает 404.
 */
final readonly class GetStaffAvatarUseCase
{
    private const string MIME_TYPE = 'image/webp';

    public function __construct(
        private AvatarAccess $access,
        private StaffAvatarRepositoryInterface $avatars,
        private AvatarStorageInterface $storage,
    ) {}

    /**
     * @throws HttpException
     */
    public function execute(int $actorUserId, int $userId, AvatarVariantEnum $variant, int $version): ImageContentOutputDto
    {
        $this->access->assertCanView($actorUserId);
        $avatar = $this->avatars->find($userId);
        if (null === $avatar || $avatar->version !== $version) {
            throw new HttpException('AVATAR_NOT_FOUND', 404);
        }
        $content = $this->storage->read($userId, $version, $variant);
        if (null === $content) {
            throw new HttpException('AVATAR_NOT_FOUND', 404);
        }

        return new ImageContentOutputDto(
            content: $content,
            mimeType: self::MIME_TYPE,
            etag: sprintf('%d-%d-%s', $userId, $version, $variant->value),
        );
    }
}
