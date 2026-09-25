<?php

declare(strict_types=1);

namespace Morefoto\Support\Application\Question\Service;

use Morefoto\Support\Application\Question\Dto\GalleryQuestionContextDto;
use Morefoto\Support\Application\Question\Dto\StaffQuestionContextDto;
use Morefoto\Support\Domain\Question\Enum\AuthorEnum;

/**
 * Собирает простой текст сообщения бота в группе кураторов: номер вопроса, автор, откуда вопрос и подсказка про «Ответить».
 * Контекст сохраняется в беседе, поэтому повторные сообщения уходят с тем же заголовком.
 */
final readonly class MaxQuestionTextBuilder
{
    private const int CONTEXT_MAX = 500;
    private const string RULE = '———';

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

    public function text(int $questionId, string $questionAuthor, string $authorName, string $context, string $body): string
    {
        $who = AuthorEnum::STAFF->value === $questionAuthor ? 'сотрудник' : 'родитель';

        return 'Вопрос №' . $questionId . ' · ' . $who . ' «' . $authorName . '»' . "\n"
            . $context . "\n"
            . self::RULE . "\n"
            . $body . "\n"
            . self::RULE . "\n"
            . 'Чтобы ответить, нажмите «Ответить» на этом сообщении.';
    }
}
