<?php

declare(strict_types=1);

namespace Morefoto\Support\Domain\Question\Service;

use Rebit\Share\Shared\Exception\HttpException;

/**
 * Предметные правила текста вопросов: имя родителя и реплика хранятся как простой текст ограниченной длины,
 * без управляющих символов, чтобы история на сайте и сообщение в MAX выглядели одинаково.
 */
final readonly class QuestionTextPolicy
{
    public const int NAME_MAX = 60;
    public const int MESSAGE_MAX = 2000;
    public const int CONTACT_MAX = 120;
    /** Лимит текста POST /messages MAX. */
    public const int CURATOR_MAX = 4000;

    public function name(string $name): string
    {
        $name = trim((string)preg_replace('/\s+/u', ' ', $name));
        if (!$this->plain($name) || '' === $name || self::NAME_MAX < mb_strlen($name)) {
            throw new HttpException('INVALID_QUESTION_NAME', 422);
        }

        return $name;
    }

    public function message(string $message): string
    {
        $message = trim(str_replace(["\r\n", "\r"], "\n", $message));
        if (!$this->plain($message) || '' === $message || self::MESSAGE_MAX < mb_strlen($message)) {
            throw new HttpException('INVALID_QUESTION_MESSAGE', 422);
        }

        return $message;
    }

    /** Контакт гостя: email или телефон не короче 10 цифр — иначе куратору некуда ответить. */
    public function contact(string $contact): string
    {
        $contact = trim((string)preg_replace('/\s+/u', ' ', $contact));
        $email = 1 === preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/uD', $contact);
        $phone = 1 === preg_match('/^\+?[\d\s()\-]+$/D', $contact) && 10 <= strlen((string)preg_replace('/\D/', '', $contact));
        if (!$this->plain($contact) || self::CONTACT_MAX < mb_strlen($contact) || (!$email && !$phone)) {
            throw new HttpException('INVALID_FEEDBACK_CONTACT', 422);
        }

        return $contact;
    }

    /** Ответ куратора из MAX: пустой или нетекстовый ответ не публикуется; длинный укорачивается до лимита MAX. */
    public function curatorReply(?string $text): ?string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string)$text));
        $text = (string)preg_replace('/[^\P{Cc}\n\t]/u', '', $text);

        return '' === $text ? null : mb_substr($text, 0, self::CURATOR_MAX);
    }

    private function plain(string $text): bool
    {
        return 1 === preg_match('//u', $text) && 0 === preg_match('/[^\P{Cc}\n\t]/u', $text);
    }
}
