<?php

declare(strict_types=1);

namespace Morefoto\Media\Application\Gallery\Service;

use Morefoto\Media\Domain\Gallery\Repository\GalleryCapabilityRepository;
use Rebit\Share\Contracts\Media\Dto\GalleryLinkOutputDto;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;

/**
 * Даёт сотрудникам повторно скопировать действующую ссылку галереи группы и выдаёт её при первой подготовке к передаче.
 * Покупатель по-прежнему открывает галерею только по хешу ключа; отозванная ссылка сотрудникам не показывается.
 */
final readonly class GalleryLinks implements GalleryLinkInterface
{
    public function __construct(
        private GalleryCapabilityRepository $keys,
        private GalleryCapabilityLifecycle $lifecycle,
    ) {}

    public function current(string $groupId): ?GalleryLinkOutputDto
    {
        $row = $this->keys->current($groupId);
        if (false === $row) {
            return null;
        }
        $issuedAt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $row['CREATED_AT'], new \DateTimeZone('UTC'));
        if (false === $issuedAt) {
            throw new \UnexpectedValueException('Invalid stored gallery link timestamp.');
        }

        return new GalleryLinkOutputDto($row['TOKEN'], $issuedAt);
    }

    public function ensure(string $groupId): GalleryLinkOutputDto
    {
        $current = $this->current($groupId);
        if (null !== $current) {
            return $current;
        }
        $this->lifecycle->issue($groupId);

        return $this->current($groupId) ?? throw new \UnexpectedValueException('Issued gallery link is not readable.');
    }
}
