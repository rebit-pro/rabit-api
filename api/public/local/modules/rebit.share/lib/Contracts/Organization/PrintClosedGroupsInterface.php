<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization;

use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;

/** Группы с переданной ссылкой, у которых закончился приём печати, — очередь сборки архива печати L1. */
interface PrintClosedGroupsInterface
{
    /**
     * Группы, срок печати которых лежит после курсора (срок, ID группы) и не позже границы, по возрастанию срока и ID.
     * Закрытый срок печати больше не меняется (PRINT_SALES_CLOSED), поэтому курсор не пропускает группы.
     *
     * @param \DateTimeImmutable $after        срок печати последней обработанной группы
     * @param int                $afterGroupId внутренний ID последней обработанной группы с этим сроком
     * @param \DateTimeImmutable $until        включительная граница срока
     *
     * @return list<GroupDirectoryItemOutputDto>
     */
    public function closedBetween(\DateTimeImmutable $after, int $afterGroupId, \DateTimeImmutable $until, int $limit): array;
}
