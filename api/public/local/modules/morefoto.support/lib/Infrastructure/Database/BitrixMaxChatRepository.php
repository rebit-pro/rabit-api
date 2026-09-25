<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Database;

use Morefoto\Support\Domain\Question\Repository\MaxChatRepositoryInterface;

final readonly class BitrixMaxChatRepository implements MaxChatRepositoryInterface
{
    public function __construct(private SupportSql $sql) {}

    public function seen(int $chatId, string $event, bool $botPresent, \DateTimeImmutable $now): void
    {
        $event = $this->sql->quote(substr((string)preg_replace('/[^a-z_]/', '_', $event), 0, 24));
        $present = $botPresent ? 1 : 0;
        $this->sql->execute('INSERT INTO mf_support_max_chat(CHAT_ID,LAST_EVENT,BOT_PRESENT,SEEN_AT) VALUES(' . $chatId . ',' . $event . ',' . $present . ','
            . $this->sql->moment($now) . ') ON DUPLICATE KEY UPDATE LAST_EVENT=VALUES(LAST_EVENT),BOT_PRESENT=VALUES(BOT_PRESENT),SEEN_AT=VALUES(SEEN_AT)');
    }

    public function recent(int $limit): array
    {
        $result = $this->sql->query('SELECT CHAT_ID,LAST_EVENT,BOT_PRESENT,' . sprintf(SupportSql::ISO_MOMENT, 'SEEN_AT') . ' AS SEEN'
            . ' FROM mf_support_max_chat ORDER BY SEEN_AT DESC LIMIT ' . max(1, min(100, $limit)));
        $chats = [];
        while (false !== ($row = $result->fetch())) {
            $chats[] = ['chatId' => (int)$row['CHAT_ID'], 'lastEvent' => (string)$row['LAST_EVENT'], 'botPresent' => 1 === (int)$row['BOT_PRESENT'], 'seenAt' => (string)$row['SEEN']];
        }

        return $chats;
    }
}
