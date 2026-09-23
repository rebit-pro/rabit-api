<?php

declare(strict_types=1);

namespace Morefoto\Media\Domain\Transfer\Service;

/**
 * Предметные правила переноса полного набора ребёнка между группами одной съёмки.
 * Выдаёт свободные коды в порядке прототипа (A…Z, AA…ZZZ), сверяет набор с ожидаемым и находит кадры,
 * общие с детьми, которые остаются на месте.
 */
final readonly class ChildTransferPolicy
{
    private const int CODE_LIMIT = 18278;

    public function isCode(string $code): bool
    {
        return 1 === preg_match('/^[A-Z]{1,3}$/D', $code);
    }

    /**
     * @param list<string> $taken
     *
     * @return list<string>
     */
    public function freeCodes(array $taken, int $count): array
    {
        if (1 > $count) {
            return [];
        }
        $used = array_fill_keys($taken, true);
        $codes = [];
        for ($index = 0; self::CODE_LIMIT > $index; ++$index) {
            $code = $this->codeAt($index);
            if (!isset($used[$code])) {
                $codes[] = $code;
                if ($count === count($codes)) {
                    return $codes;
                }
            }
        }

        throw new \OverflowException('The group has no free child codes left.');
    }

    /**
     * Порядок и повторы не важны: сравниваются множества ID кадров.
     *
     * @param list<string> $expected
     * @param list<string> $current
     */
    public function sameSet(array $expected, array $current): bool
    {
        $expected = array_values(array_unique($expected));
        $current = array_values(array_unique($current));
        sort($expected);
        sort($current);

        return $expected === $current;
    }

    /**
     * Кадры, назначенные кроме переносимых ещё и ребёнку, который остаётся.
     *
     * @param array<int, list<int>> $photoChildren ID кадра => ID всех детей, которым он назначен
     * @param array<int, true>      $moving        ID переносимых детей
     *
     * @return list<int>
     */
    public function sharedPhotos(array $photoChildren, array $moving): array
    {
        $shared = [];
        foreach ($photoChildren as $photoId => $children) {
            foreach ($children as $childId) {
                if (!isset($moving[$childId])) {
                    $shared[] = $photoId;
                    break;
                }
            }
        }

        return $shared;
    }

    public function frameCode(string $childCode, int $sequence): string
    {
        return $childCode . str_pad((string)$sequence, 3, '0', STR_PAD_LEFT);
    }

    /** Нумерация без нуля: 0 → A, 25 → Z, 26 → AA, 701 → ZZ, 702 → AAA. */
    private function codeAt(int $index): string
    {
        $code = '';
        for ($value = $index + 1; 0 < $value; $value = intdiv($value - 1, 26)) {
            $code = chr(65 + ($value - 1) % 26) . $code;
        }

        return $code;
    }
}
