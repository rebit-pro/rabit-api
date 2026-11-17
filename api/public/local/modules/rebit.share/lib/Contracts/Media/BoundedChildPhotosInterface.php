<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;

/** Ограниченная проекция готовых назначений для сохраняемых служебных составов без скрытого лимита покупательской выдачи. */
interface BoundedChildPhotosInterface
{
    public const int MAX_ROWS = 50_001;

    /**
     * Читает не более $limit назначений (1..MAX_ROWS). Результат размера limit может быть неполным:
     * вызывающий запрашивает свой предел + 1 и явно отклоняет превышение, не публикуя усечённый состав.
     * Правило текущих ready-кадров детей в съёмке и их текущей группе совпадает с ChildPhotosInterface.
     *
     * @param list<int> $childIds
     *
     * @return list<GalleryAssignmentOutputDto>
     */
    public function ready(int $shootId, array $childIds, int $limit): array;
}
