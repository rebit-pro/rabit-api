<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\ValueObject;

use Bitrix\Main\Type\DateTime;
use Rebit\Share\Shared\Exception\RebitException;

abstract class AbstractDateRange
{
    public const string DEFAULT_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        public \DateTimeInterface $start,
        public \DateTimeInterface $end,
    ) {
        $this->normalize();

        if ($this->end < $this->start) {
            throw new \LogicException('Дата окончания периода не может быть меньше даты начала.');
        }
    }

    public function getBitrixStart(): DateTime
    {
        return DateTime::createFromTimestamp($this->start->getTimestamp());
    }

    public function getBitrixEnd(): DateTime
    {
        return DateTime::createFromTimestamp($this->end->getTimestamp());
    }

    /**
     * Создает объект диапазона из строк указанного формата.
     *
     * @throws RebitException
     */
    public static function fromStrings(string $start, string $end, ?string $format = null): static
    {
        $formatToUse = $format ?? static::DEFAULT_FORMAT;

        $dateFrom = \DateTimeImmutable::createFromFormat($formatToUse, $start);
        $dateTo = \DateTimeImmutable::createFromFormat($formatToUse, $end);

        if (!$dateFrom instanceof \DateTimeImmutable || !$dateTo instanceof \DateTimeImmutable) {
            throw new RebitException('Неверный формат даты для ' . static::class . '.');
        }

        return new static($dateFrom, $dateTo);
    }

    /**
     * Проверяет, входит ли указанная дата/дата+время в диапазон (включительно).
     */
    public function contains(DateTime|\DateTimeInterface $point): bool
    {
        if ($point instanceof DateTime) {
            $point = (new \DateTimeImmutable())->setTimestamp($point->getTimestamp());
        }

        return $point >= $this->start && $point <= $this->end;
    }

    /**
     * Точка расширения для нормализации границ в наследниках.
     * По умолчанию ничего не делает.
     */
    protected function normalize(): void {}
}
