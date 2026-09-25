<?php

declare(strict_types=1);

namespace Morefoto\Support\Domain\Question\Repository;

/** Чаты MAX, из которых приходили события бота: GET /chats снят с поддержки, ID группы берётся отсюда. */
interface MaxChatRepositoryInterface
{
    public function seen(int $chatId, string $event, bool $botPresent, \DateTimeImmutable $now): void;

    /** @return list<array{chatId: int, lastEvent: string, botPresent: bool, seenAt: string}> */
    public function recent(int $limit): array;
}
