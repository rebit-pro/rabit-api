<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Media;

/** Адрес каталога обычных групп одной съёмки для передачи организатором. */
interface ShootGalleryLinkInterface
{
    public function current(string $shootId): ?string;

    /** @param list<string> $publishedGroups открытые группы, старым действующим ключам которых требуется копируемая ссылка */
    public function ensure(string $shootId, array $publishedGroups): string;
}
