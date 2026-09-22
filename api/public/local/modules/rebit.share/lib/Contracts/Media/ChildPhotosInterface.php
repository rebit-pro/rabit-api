<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;

interface ChildPhotosInterface
{
    /**
     * Готовые кадры указанных детей одной группы без ссылок на изображения.
     * Доступ сотрудника к группе проверяет вызывающий модуль.
     *
     * @param list<int> $childIds внутренние ID детей Media
     *
     * @return list<GalleryAssignmentOutputDto>
     */
    public function ready(int $groupId, int $shootId, array $childIds): array;
}
