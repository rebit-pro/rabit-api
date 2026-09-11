<?php

declare(strict_types=1);

namespace Rebit\Share\Shared\ValueObject;

final class Phone
{
    private const int MIN_DIGITS = 10;
    private const int MAX_DIGITS = 15;

    private string $original;
    private string $normalized; // Хранится в формате +<digits> без разделителей

    public function __construct(string $phone)
    {
        $this->original = trim($phone);
        $this->normalized = self::normalize($this->original);

        $digits = preg_replace('/\D+/', '', $this->normalized);

        $len = strlen($digits);
        if ($len < self::MIN_DIGITS || $len > self::MAX_DIGITS) {
            throw new \LogicException('Некорректный телефон. Количество цифр: ' . $len);
        }
    }

    public static function fromString(string $phone): self
    {
        return new self($phone);
    }

    public function getOriginal(): string
    {
        return $this->original;
    }

    public function getNormalized(): string
    {
        return $this->normalized;
    }

    public function __toString(): string
    {
        return $this->normalized;
    }

    public function equals(self $other): bool
    {
        return $this->normalized === $other->normalized;
    }

    private static function normalize(string $phone): string
    {
        $phone = trim($phone);

        // Если есть битриксовая функция NormalizePhone – используем её
        if (function_exists('NormalizePhone')) {
            $normalized = (string)NormalizePhone($phone);
            $normalized = preg_replace('/\D+/', '', $normalized);
        } else {
            // Ручная очистка
            $normalized = preg_replace('/\D+/', '', $phone);
        }

        if ('' === $normalized) {
            throw new \LogicException('Пустое значение телефона.');
        }

        // Приведение для РФ: 8XXXXXXXXXX -> 7XXXXXXXXXX (если длина 11 и начинается с 8)
        if (11 === strlen($normalized) && '8' === $normalized[0]) {
            $normalized = '7' . substr($normalized, 1);
        }

        // Если нет кода страны и длина 10 — считаем что РФ
        if (10 === strlen($normalized)) {
            $normalized = '7' . $normalized;
        }

        // Префикс с плюсом
        return '+' . $normalized;
    }
}
