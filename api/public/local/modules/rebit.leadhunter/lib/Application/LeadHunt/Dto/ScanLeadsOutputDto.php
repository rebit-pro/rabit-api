<?php

declare(strict_types=1);

namespace Rebit\Leadhunter\Application\LeadHunt\Dto;

/**
 * Итог одного прогона сканирования.
 */
final readonly class ScanLeadsOutputDto
{
    public function __construct(
        public int $matched,
        public int $added,
        public int $sent,
        public int $failed,
    ) {}
}
