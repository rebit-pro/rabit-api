<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;
use Rebit\Share\Contracts\Media\Dto\GroupChildOutputDto;

/** Дети разметки для отчётов: текущие коды групп и первый готовый кадр. Доступ сотрудника проверяет вызывающий модуль. */
interface GroupChildrenInterface
{
    /**
     * @param list<int> $groupIds native Organization group IDs
     *
     * @return list<GroupChildOutputDto> по группе, затем по коду: A…Z, AA…
     */
    public function children(array $groupIds): array;

    /**
     * @param list<int> $groupIds native Organization group IDs
     *
     * @return array<int, int> число детей по native ID группы; группы без детей отсутствуют
     */
    public function counts(array $groupIds): array;

    public function find(string $childId): ?GroupChildOutputDto;

    /** Миниатюра готового кадра; null — производной нет. */
    public function thumbnail(string $photoId): ?PreviewContentOutputDto;

    /** Водяное превью готового кадра для крупного просмотра; null — производной нет. */
    public function preview(string $photoId): ?PreviewContentOutputDto;
}
