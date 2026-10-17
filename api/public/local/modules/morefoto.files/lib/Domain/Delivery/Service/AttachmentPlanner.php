<?php

declare(strict_types=1);

namespace Morefoto\Files\Domain\Delivery\Service;

use Morefoto\Files\Domain\Delivery\ValueObject\LetterFile;
use Morefoto\Files\Domain\Delivery\ValueObject\LetterPlan;

/**
 * Раскладывает оригиналы заказа по письмам (J5-DEC-01/02): кадры по порядку страницы заказа, до 18 МБ исходных байт в
 * письме; кадр крупнее идёт один, кадр выше предела письма 20 МБ не прикладывается и скачивается по ссылке.
 */
final readonly class AttachmentPlanner
{
    public const int PART_BYTES = 18 * 1024 * 1024;
    /** Не выше предела вложений H1: после base64 письмо остаётся ниже лимита почтовых серверов в 30 МБ. */
    public const int FILE_BYTES = 20 * 1024 * 1024;

    public function __construct(private int $partBytes = self::PART_BYTES)
    {
        if (1 > $this->partBytes || self::FILE_BYTES < $this->partBytes) {
            throw new \InvalidArgumentException('The letter part limit is out of range.');
        }
    }

    /** @param list<LetterFile> $files */
    public function plan(array $files): LetterPlan
    {
        $letters = [];
        $linkOnly = [];
        $letter = [];
        $bytes = 0;
        foreach ($files as $file) {
            if (self::FILE_BYTES < $file->bytes) {
                $linkOnly[] = $file;
                continue;
            }
            if ([] !== $letter && $this->partBytes < $bytes + $file->bytes) {
                $letters[] = $letter;
                $letter = [];
                $bytes = 0;
            }
            $letter[] = $file;
            $bytes += $file->bytes;
        }
        if ([] !== $letter || [] === $letters) {
            $letters[] = $letter;
        }

        return new LetterPlan($letters, $linkOnly);
    }
}
