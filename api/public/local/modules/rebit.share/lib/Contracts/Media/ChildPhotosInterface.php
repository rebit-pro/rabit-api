<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;

interface ChildPhotosInterface
{
    /**
     * Готовые кадры указанных детей съёмки в их текущей группе без ссылок на изображения.
     * После переноса D3 это кадры новой группы с текущими кодами.
     * Доступ сотрудника к заказу или группе проверяет вызывающий модуль.
     *
     * @param list<int> $childIds внутренние ID детей Media
     *
     * @return list<GalleryAssignmentOutputDto>
     */
    public function ready(int $shootId, array $childIds): array;
}
