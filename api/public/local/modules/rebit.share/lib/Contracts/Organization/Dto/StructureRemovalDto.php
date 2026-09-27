<?php

declare(strict_types=1);

namespace Rebit\Share\Contracts\Organization\Dto;

/**
 * Удаляемая часть структуры во внутренних ID Organization: группы, съёмки и учреждения.
 * Группы перечислены все, включая группы удаляемых съёмок и учреждений.
 */
final readonly class StructureRemovalDto
{
    /**
     * @param list<int> $groupIds
     * @param list<int> $shootIds
     * @param list<int> $institutionIds
     */
    public function __construct(
        public array $groupIds,
        public array $shootIds,
        public array $institutionIds,
    ) {}
}
