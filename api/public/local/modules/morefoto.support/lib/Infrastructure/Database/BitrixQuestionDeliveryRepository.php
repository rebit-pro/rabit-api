<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Database;

use Morefoto\Support\Domain\Question\Repository\QuestionDeliveryRepositoryInterface;

final readonly class BitrixQuestionDeliveryRepository implements QuestionDeliveryRepositoryInterface
{
    public function __construct(private SupportSql $sql) {}

    public function claim(int $messageId, \DateTimeImmutable $now, \DateTimeImmutable $staleBefore): ?array
    {
        $question = $this->sql->query('SELECT QUESTION_ID FROM mf_support_message WHERE ID=' . $this->sql->id($messageId))->fetch();
        if (false === $question) {
            return null;
        }
        $questionId = (int)$question['QUESTION_ID'];
        // The conversation row serializes claims: only the earliest unfinished reply of an author goes to MAX.
        $this->sql->query('SELECT ID FROM mf_support_question WHERE ID=' . $this->sql->id($questionId) . ' FOR UPDATE')->fetch();
        // Moments are compared in SQL: Bitrix converts fetched DATETIME columns into its own objects.
        $row = $this->sql->query('SELECT m.AUTHOR_NAME,m.BODY,m.DELIVERY_STATUS,m.ATTEMPTS,'
            . '(m.NEXT_ATTEMPT_AT IS NULL OR m.NEXT_ATTEMPT_AT<=' . $this->sql->moment($now) . ') AS IS_DUE,'
            . '(m.PROCESSING_STARTED_AT<=' . $this->sql->moment($staleBefore) . ') AS IS_STALE,'
            . "EXISTS(SELECT 1 FROM mf_support_message p WHERE p.QUESTION_ID=m.QUESTION_ID AND p.ID<m.ID AND p.DELIVERY_STATUS IN ('pending','processing')) AS IS_BLOCKED,"
            . 'q.ID AS QUESTION_ID,q.AUTHOR AS QUESTION_AUTHOR,q.CONTEXT FROM mf_support_message m'
            . ' JOIN mf_support_question q ON q.ID=m.QUESTION_ID WHERE m.ID=' . $this->sql->id($messageId) . ' FOR UPDATE')->fetch();
        if (false === $row) {
            return null;
        }
        $attempts = (int)$row['ATTEMPTS'];
        if ('processing' === $row['DELIVERY_STATUS'] && 1 === (int)$row['IS_STALE']) {
            $this->unknown($messageId, $attempts, 'processing_lease_expired');

            return null;
        }
        if ('pending' !== $row['DELIVERY_STATUS'] || 1 !== (int)$row['IS_DUE'] || 1 === (int)$row['IS_BLOCKED']) {
            return null;
        }
        $this->sql->execute("UPDATE mf_support_message SET DELIVERY_STATUS='processing',ATTEMPTS=ATTEMPTS+1,PROCESSING_STARTED_AT="
            . $this->sql->moment($now) . ',NEXT_ATTEMPT_AT=NULL WHERE ID=' . $this->sql->id($messageId) . " AND DELIVERY_STATUS='pending'");

        return [
            'id' => $messageId,
            'attempt' => $attempts + 1,
            'questionId' => (int)$row['QUESTION_ID'],
            'questionAuthor' => (string)$row['QUESTION_AUTHOR'],
            'authorName' => (string)$row['AUTHOR_NAME'],
            'context' => (string)$row['CONTEXT'],
            'body' => (string)$row['BODY'],
        ];
    }

    public function delivered(int $messageId, int $attempt, string $mid): void
    {
        $this->finish($messageId, $attempt, "DELIVERY_STATUS='delivered',MAX_MID=" . $this->sql->quote($mid) . ',LAST_ERROR_CODE=NULL');
    }

    public function retry(int $messageId, int $attempt, \DateTimeImmutable $nextAttemptAt, string $errorCode): void
    {
        $this->finish($messageId, $attempt, "DELIVERY_STATUS='pending',NEXT_ATTEMPT_AT=" . $this->sql->moment($nextAttemptAt) . ',LAST_ERROR_CODE=' . $this->code($errorCode));
    }

    public function failed(int $messageId, int $attempt, string $errorCode): void
    {
        $this->finish($messageId, $attempt, "DELIVERY_STATUS='failed',LAST_ERROR_CODE=" . $this->code($errorCode));
    }

    public function unknown(int $messageId, int $attempt, string $errorCode): void
    {
        $this->finish($messageId, $attempt, "DELIVERY_STATUS='unknown',LAST_ERROR_CODE=" . $this->code($errorCode));
    }

    public function recoverStale(\DateTimeImmutable $staleBefore): int
    {
        return $this->sql->execute("UPDATE mf_support_message SET DELIVERY_STATUS='unknown',LAST_ERROR_CODE='processing_lease_expired',PROCESSING_STARTED_AT=NULL"
            . " WHERE DELIVERY_STATUS='processing' AND PROCESSING_STARTED_AT<=" . $this->sql->moment($staleBefore));
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        $result = $this->sql->query("SELECT m.ID FROM mf_support_message m WHERE m.DELIVERY_STATUS='pending' AND m.NEXT_ATTEMPT_AT<=" . $this->sql->moment($now)
            . " AND NOT EXISTS(SELECT 1 FROM mf_support_message p WHERE p.QUESTION_ID=m.QUESTION_ID AND p.ID<m.ID AND p.DELIVERY_STATUS IN ('pending','processing'))"
            . ' ORDER BY m.ID LIMIT ' . max(1, min(500, $limit)));
        $ids = [];
        while (false !== ($row = $result->fetch())) {
            $ids[] = (int)$row['ID'];
        }

        return $ids;
    }

    public function nextPending(int $questionId): ?int
    {
        $row = $this->sql->query('SELECT ID FROM mf_support_message WHERE QUESTION_ID=' . $this->sql->id($questionId)
            . " AND DELIVERY_STATUS='pending' ORDER BY ID LIMIT 1")->fetch();

        return false === $row ? null : (int)$row['ID'];
    }

    public function countByStatus(): array
    {
        $result = $this->sql->query('SELECT DELIVERY_STATUS,COUNT(*) AS TOTAL FROM mf_support_message WHERE DELIVERY_STATUS IS NOT NULL GROUP BY DELIVERY_STATUS');
        $counts = [];
        while (false !== ($row = $result->fetch())) {
            $counts[(string)$row['DELIVERY_STATUS']] = (int)$row['TOTAL'];
        }

        return $counts;
    }

    private function finish(int $messageId, int $attempt, string $assignments): void
    {
        $this->sql->execute('UPDATE mf_support_message SET ' . $assignments . ',PROCESSING_STARTED_AT=NULL WHERE ID=' . $this->sql->id($messageId)
            . " AND DELIVERY_STATUS='processing' AND ATTEMPTS=" . max(0, $attempt));
    }

    private function code(string $errorCode): string
    {
        return $this->sql->quote(substr((string)preg_replace('/[^a-z0-9_]/', '_', strtolower($errorCode)), 0, 64));
    }
}
