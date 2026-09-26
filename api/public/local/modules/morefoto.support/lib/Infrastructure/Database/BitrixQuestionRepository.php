<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Database;

use Morefoto\Support\Domain\Question\Enum\AuthorEnum;
use Morefoto\Support\Domain\Question\Enum\DeliveryStatusEnum;
use Morefoto\Support\Domain\Question\Repository\QuestionRepositoryInterface;

final readonly class BitrixQuestionRepository implements QuestionRepositoryInterface
{
    public function __construct(private SupportSql $sql) {}

    public function findParent(string $keyHash): ?array
    {
        return $this->question("AUTHOR='parent' AND KEY_HASH=" . $this->sql->quote($keyHash), false);
    }

    public function findStaff(int $userId): ?array
    {
        return $this->question("AUTHOR='staff' AND STAFF_USER_ID=" . $this->sql->id($userId), false);
    }

    public function lock(int $questionId): bool
    {
        return null !== $this->question('ID=' . $this->sql->id($questionId), true);
    }

    public function lockStaff(int $userId): ?array
    {
        return $this->question("AUTHOR='staff' AND STAFF_USER_ID=" . $this->sql->id($userId), true);
    }

    public function createParent(string $keyHash, int $groupId, string $authorName, string $context, \DateTimeImmutable $now): int
    {
        return $this->sql->insert('INSERT INTO mf_support_question(AUTHOR,KEY_HASH,GROUP_ID,STAFF_USER_ID,AUTHOR_NAME,CONTEXT,CREATED_AT,LAST_MESSAGE_AT) VALUES('
            . "'parent'," . $this->sql->quote($keyHash) . ',' . $this->sql->id($groupId) . ',NULL,' . $this->sql->quote($authorName) . ','
            . $this->sql->quote($context) . ',' . $this->sql->moment($now) . ',' . $this->sql->moment($now) . ')');
    }

    public function createStaff(int $userId, string $authorName, string $context, \DateTimeImmutable $now): int
    {
        return $this->sql->insert('INSERT INTO mf_support_question(AUTHOR,KEY_HASH,GROUP_ID,STAFF_USER_ID,AUTHOR_NAME,CONTEXT,CREATED_AT,LAST_MESSAGE_AT) VALUES('
            . "'staff',NULL,NULL," . $this->sql->id($userId) . ',' . $this->sql->quote($authorName) . ','
            . $this->sql->quote($context) . ',' . $this->sql->moment($now) . ',' . $this->sql->moment($now) . ')');
    }

    public function createGuest(string $authorName, string $context, \DateTimeImmutable $now): int
    {
        return $this->sql->insert('INSERT INTO mf_support_question(AUTHOR,KEY_HASH,GROUP_ID,STAFF_USER_ID,AUTHOR_NAME,CONTEXT,CREATED_AT,LAST_MESSAGE_AT) VALUES('
            . "'guest',NULL,NULL,NULL," . $this->sql->quote($authorName) . ',' . $this->sql->quote($context) . ','
            . $this->sql->moment($now) . ',' . $this->sql->moment($now) . ')');
    }

    public function refreshStaff(int $questionId, string $authorName, string $context): void
    {
        $this->sql->execute('UPDATE mf_support_question SET AUTHOR_NAME=' . $this->sql->quote($authorName) . ',CONTEXT=' . $this->sql->quote($context)
            . ' WHERE ID=' . $this->sql->id($questionId));
    }

    public function addMessage(int $questionId, AuthorEnum $author, string $authorName, string $body, \DateTimeImmutable $now): int
    {
        $id = $this->sql->insert('INSERT INTO mf_support_message(QUESTION_ID,AUTHOR,AUTHOR_NAME,BODY,CREATED_AT,DELIVERY_STATUS,NEXT_ATTEMPT_AT) VALUES('
            . $this->sql->id($questionId) . ',' . $this->sql->quote($author->value) . ',' . $this->sql->quote($authorName) . ','
            . $this->sql->quote($body) . ',' . $this->sql->moment($now) . ",'" . DeliveryStatusEnum::PENDING->value . "'," . $this->sql->moment($now) . ')');
        $this->touch($questionId, $now);

        return $id;
    }

    public function addCuratorReply(int $questionId, string $authorName, string $body, string $mid, \DateTimeImmutable $now): bool
    {
        // The unique MAX_MID turns a repeated webhook delivery into a no-op instead of a second reply.
        $inserted = 1 === $this->sql->execute('INSERT INTO mf_support_message(QUESTION_ID,AUTHOR,AUTHOR_NAME,BODY,CREATED_AT,MAX_MID) VALUES('
            . $this->sql->id($questionId) . ",'" . AuthorEnum::CURATOR->value . "'," . $this->sql->quote($authorName) . ','
            . $this->sql->quote($body) . ',' . $this->sql->moment($now) . ',' . $this->sql->quote($mid) . ') ON DUPLICATE KEY UPDATE ID=ID');
        if ($inserted) {
            $this->touch($questionId, $now);
        }

        return $inserted;
    }

    public function questionByOutgoingMid(string $mid): ?int
    {
        $row = $this->sql->query("SELECT QUESTION_ID FROM mf_support_message WHERE AUTHOR IN ('parent','staff') AND MAX_MID=" . $this->sql->quote($mid))->fetch();

        return false === $row ? null : (int)$row['QUESTION_ID'];
    }

    public function messages(int $questionId): array
    {
        $result = $this->sql->query('SELECT ID,AUTHOR,AUTHOR_NAME,BODY,' . sprintf(SupportSql::ISO_MOMENT, 'CREATED_AT') . ' AS CREATED,DELIVERY_STATUS'
            . ' FROM mf_support_message WHERE QUESTION_ID=' . $this->sql->id($questionId) . ' ORDER BY ID');
        $messages = [];
        while (false !== ($row = $result->fetch())) {
            $messages[] = [
                'id' => (int)$row['ID'],
                'author' => (string)$row['AUTHOR'],
                'authorName' => (string)$row['AUTHOR_NAME'],
                'body' => (string)$row['BODY'],
                'createdAt' => (string)$row['CREATED'],
                'deliveryStatus' => null === $row['DELIVERY_STATUS'] ? null : (string)$row['DELIVERY_STATUS'],
            ];
        }

        return $messages;
    }

    public function countParentQuestions(int $groupId, \DateTimeImmutable $since): int
    {
        return $this->count("SELECT COUNT(*) AS TOTAL FROM mf_support_question WHERE AUTHOR='parent' AND GROUP_ID=" . $this->sql->id($groupId)
            . ' AND CREATED_AT>=' . $this->sql->moment($since));
    }

    public function countGuestQuestions(\DateTimeImmutable $since): int
    {
        // Covered by ix_mf_support_question_group: guest rows share GROUP_ID NULL.
        return $this->count("SELECT COUNT(*) AS TOTAL FROM mf_support_question WHERE AUTHOR='guest' AND GROUP_ID IS NULL AND CREATED_AT>=" . $this->sql->moment($since));
    }

    public function countOwnMessages(int $questionId, \DateTimeImmutable $since): int
    {
        return $this->count('SELECT COUNT(*) AS TOTAL FROM mf_support_message WHERE QUESTION_ID=' . $this->sql->id($questionId)
            . " AND AUTHOR<>'curator' AND CREATED_AT>=" . $this->sql->moment($since));
    }

    public function idempotency(string $scope, string $keyHash): ?array
    {
        $row = $this->sql->query('SELECT PAYLOAD_HASH,QUESTION_ID,SEALED_KEY FROM mf_support_idempotency WHERE SCOPE_KEY=' . $this->sql->quote($scope)
            . ' AND KEY_HASH=' . $this->sql->quote($keyHash) . ' FOR UPDATE')->fetch();

        return false === $row ? null : [
            'payloadHash' => (string)$row['PAYLOAD_HASH'],
            'questionId' => (int)$row['QUESTION_ID'],
            'sealedKey' => null === $row['SEALED_KEY'] ? null : (string)$row['SEALED_KEY'],
        ];
    }

    public function remember(string $scope, string $keyHash, string $payloadHash, int $questionId, ?string $sealedKey, \DateTimeImmutable $now): void
    {
        $this->sql->execute('INSERT INTO mf_support_idempotency(SCOPE_KEY,KEY_HASH,PAYLOAD_HASH,QUESTION_ID,SEALED_KEY,CREATED_AT) VALUES('
            . $this->sql->quote($scope) . ',' . $this->sql->quote($keyHash) . ',' . $this->sql->quote($payloadHash) . ','
            . $this->sql->id($questionId) . ',' . (null === $sealedKey ? 'NULL' : $this->sql->quote($sealedKey)) . ',' . $this->sql->moment($now) . ')');
    }

    /** @return null|array{id: int, authorName: string} */
    private function question(string $where, bool $lock): ?array
    {
        $row = $this->sql->query('SELECT ID,AUTHOR_NAME FROM mf_support_question WHERE ' . $where . ($lock ? ' FOR UPDATE' : ''))->fetch();

        return false === $row ? null : ['id' => (int)$row['ID'], 'authorName' => (string)$row['AUTHOR_NAME']];
    }

    private function touch(int $questionId, \DateTimeImmutable $now): void
    {
        $this->sql->execute('UPDATE mf_support_question SET LAST_MESSAGE_AT=' . $this->sql->moment($now) . ' WHERE ID=' . $this->sql->id($questionId));
    }

    private function count(string $sql): int
    {
        $row = $this->sql->query($sql)->fetch();

        return false === $row ? 0 : (int)$row['TOTAL'];
    }
}
