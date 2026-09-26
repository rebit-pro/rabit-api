<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Service;

use Morefoto\Support\Application\Question\Dto\GalleryQuestionContextDto;
use Morefoto\Support\Application\Question\Dto\StaffQuestionContextDto;
use Morefoto\Support\Domain\Question\Enum\AuthorEnum;

/**
 * Собирает простой текст сообщения бота в группе кураторов: номер вопроса, автор, откуда вопрос и подсказка, как ответить.
 * Контекст сохраняется в беседе, поэтому повторные сообщения уходят с тем же заголовком.
 */
final readonly class MaxQuestionTextBuilder
{
    private const int CONTEXT_MAX = 500;
    private const string RULE = '———';
    private const string REPLY_HINT = 'Чтобы ответить, нажмите «Ответить» на этом сообщении.';

    public function galleryContext(GalleryQuestionContextDto $context): string
    {
        $text = 'Сад «' . $context->institutionName . '», группа «' . $context->groupName . '»';
        if (null !== $context->curatorName && '' !== $context->curatorName) {
            $text .= ', куратор группы: ' . $context->curatorName;
        }

        return mb_substr($text, 0, self::CONTEXT_MAX);
    }

    public function staffContext(StaffQuestionContextDto $context): string
    {
        $role = 'head' === $context->role ? 'Заведующая' : 'Воспитатель';
        $places = array_map(static fn(string $name): string => '«' . $name . '»', $context->institutionNames);

        return mb_substr([] === $places ? $role : $role . ' · ' . implode(', ', $places), 0, self::CONTEXT_MAX);
    }

    public function guestContext(string $contact): string
    {
        return mb_substr('Страница входа в кабинет · контакт: ' . $contact, 0, self::CONTEXT_MAX);
    }

    public function text(int $questionId, string $questionAuthor, string $authorName, string $context, string $body): string
    {
        [$title, $hint] = match ($questionAuthor) {
            // A guest has no page with the conversation: the reply goes to the contact, not through the bot.
            AuthorEnum::GUEST->value => ['Обращение №' . $questionId . ' · гость', 'Сайт не покажет ответ: свяжитесь по контакту выше.'],
            AuthorEnum::STAFF->value => ['Вопрос №' . $questionId . ' · сотрудник', self::REPLY_HINT],
            default => ['Вопрос №' . $questionId . ' · родитель', self::REPLY_HINT],
        };

        return $title . ' «' . $authorName . '»' . "\n"
            . $context . "\n"
            . self::RULE . "\n"
            . $body . "\n"
            . self::RULE . "\n"
            . $hint;
    }
}
