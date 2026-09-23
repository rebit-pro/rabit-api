<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit\Avatar;

use Morefoto\Access\Application\Avatar\Contract\AvatarInspectorInterface;
use Morefoto\Access\Application\Avatar\Contract\AvatarRendererInterface;
use Morefoto\Access\Application\Avatar\Contract\AvatarStorageInterface;
use Morefoto\Access\Application\Avatar\Contract\StaffAvatarRepositoryInterface;
use Morefoto\Access\Application\Avatar\Dto\InspectedAvatarDto;
use Morefoto\Access\Application\Avatar\Dto\RenderedAvatarDto;
use Morefoto\Access\Application\Avatar\Dto\UploadedAvatarInputDto;
use Morefoto\Access\Domain\Avatar\Entity\StaffAvatar;
use Morefoto\Access\Domain\Avatar\Enum\AvatarVariantEnum;
use Rebit\Share\Shared\Exception\HttpException;

final class InMemoryStaffAvatars implements StaffAvatarRepositoryInterface
{
    /** @var array<int, StaffAvatar> */
    public array $avatars = [];

    /** @param list<int> $staff users that have a staff profile */
    public function __construct(private readonly array $staff) {}

    public function find(int $userId): ?StaffAvatar
    {
        return $this->avatars[$userId] ?? null;
    }

    public function locked(int $userId, callable $operation): mixed
    {
        if (!in_array($userId, $this->staff, true)) {
            throw new HttpException('STAFF_NOT_FOUND', 404);
        }

        return $operation($this->avatars[$userId] ?? null);
    }

    public function save(StaffAvatar $avatar, int $updatedBy): void
    {
        $this->avatars[$avatar->userId] = $avatar;
    }

    public function delete(int $userId): void
    {
        unset($this->avatars[$userId]);
    }
}

final class InMemoryAvatarFiles implements AvatarStorageInterface
{
    /** @var array<string, string> key "<userId>/<version>-<size>" */
    public array $files = [];
    public int $writes = 0;

    public function write(int $userId, int $version, RenderedAvatarDto $avatar): void
    {
        ++$this->writes;
        $this->files[$userId . '/' . $version . '-256'] = $avatar->full;
        $this->files[$userId . '/' . $version . '-64'] = $avatar->thumb;
    }

    public function read(int $userId, int $version, AvatarVariantEnum $variant): ?string
    {
        return $this->files[$userId . '/' . $version . '-' . $variant->value] ?? null;
    }

    public function prune(int $userId, ?int $keepVersion): void
    {
        foreach (array_keys($this->files) as $key) {
            [$owner, $name] = explode('/', $key);
            if ((int)$owner === $userId && (null === $keepVersion || !str_starts_with($name, $keepVersion . '-'))) {
                unset($this->files[$key]);
            }
        }
    }
}

/** The upload path itself is the content: equal paths are equal files. */
final class PathFingerprintInspector implements AvatarInspectorInterface
{
    public function inspect(UploadedAvatarInputDto $upload): InspectedAvatarDto
    {
        return new InspectedAvatarDto($upload->tmpName, 'image/png', $upload->bytes, 640, 480, hash('sha256', $upload->tmpName));
    }
}

final class LabelRenderer implements AvatarRendererInterface
{
    public function render(InspectedAvatarDto $avatar): RenderedAvatarDto
    {
        return new RenderedAvatarDto('full:' . $avatar->tmpName, 'thumb:' . $avatar->tmpName);
    }
}
