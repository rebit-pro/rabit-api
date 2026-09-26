<?php

declare(strict_types=1);

namespace Morefoto\Support\Tests\Unit\Double;

use Morefoto\Support\Domain\Question\Enum\AuthorEnum;
use Morefoto\Support\Domain\Question\Repository\MaxChatRepositoryInterface;
use Morefoto\Support\Domain\Question\Repository\QuestionDeliveryRepositoryInterface;
use Morefoto\Support\Domain\Question\Repository\QuestionRepositoryInterface;

/** In-memory storage with the same observable rules as the MySQL repositories (unique mid, attempt guard). */
final class InMemoryQuestions implements QuestionRepositoryInterface, QuestionDeliveryRepositoryInterface, MaxChatRepositoryInterface
{
    /** @var array<int, array{author: string, keyHash: ?string, groupId: ?int, staffUserId: ?int, authorName: string, context: string, createdAt: \DateTimeImmutable}> */
    public array $questions = [];
    /** @var array<int, array{questionId: int, author: string, authorName: string, body: string, createdAt: \DateTimeImmutable, status: ?string, attempts: int, nextAt: ?\DateTimeImmutable, startedAt: ?\DateTimeImmutable, error: ?string, mid: ?string}> */
    public array $messages = [];
    /** @var array<string, array{payloadHash: string, questionId: int, sealedKey: ?string}> */
    public array $keys = [];
    /** @var array<int, array{chatId: int, lastEvent: string, botPresent: bool, seenAt: string}> */
    public array $chats = [];
    /** @var array<string, array{windowStartedAt: \DateTimeImmutable, questions: int}> */
    public array $guestAddresses = [];

    public function findParent(string $keyHash): ?array
    {
        foreach ($this->questions as $id => $question) {
            if ('parent' === $question['author'] && $keyHash === $question['keyHash']) {
                return ['id' => $id, 'authorName' => $question['authorName']];
            }
        }

        return null;
    }

    public function findStaff(int $userId): ?array
    {
        foreach ($this->questions as $id => $question) {
            if ($userId === $question['staffUserId']) {
                return ['id' => $id, 'authorName' => $question['authorName']];
            }
        }

        return null;
    }

    public function lock(int $questionId): bool
    {
        return isset($this->questions[$questionId]);
    }

    public function lockStaff(int $userId): ?array
    {
        return $this->findStaff($userId);
    }

    public function createParent(string $keyHash, int $groupId, string $authorName, string $context, \DateTimeImmutable $now): int
    {
        $id = 100 + count($this->questions);
        $this->questions[$id] = ['author' => 'parent', 'keyHash' => $keyHash, 'groupId' => $groupId, 'staffUserId' => null, 'authorName' => $authorName, 'context' => $context, 'createdAt' => $now];

        return $id;
    }

    public function createStaff(int $userId, string $authorName, string $context, \DateTimeImmutable $now): int
    {
        $id = 100 + count($this->questions);
        $this->questions[$id] = ['author' => 'staff', 'keyHash' => null, 'groupId' => null, 'staffUserId' => $userId, 'authorName' => $authorName, 'context' => $context, 'createdAt' => $now];

        return $id;
    }

    public function createGuest(string $authorName, string $context, \DateTimeImmutable $now): int
    {
        $id = 100 + count($this->questions);
        $this->questions[$id] = ['author' => 'guest', 'keyHash' => null, 'groupId' => null, 'staffUserId' => null, 'authorName' => $authorName, 'context' => $context, 'createdAt' => $now];

        return $id;
    }

    public function refreshStaff(int $questionId, string $authorName, string $context): void
    {
        $this->questions[$questionId]['authorName'] = $authorName;
        $this->questions[$questionId]['context'] = $context;
    }

    public function addMessage(int $questionId, AuthorEnum $author, string $authorName, string $body, \DateTimeImmutable $now): int
    {
        $id = 1 + count($this->messages);
        $this->messages[$id] = ['questionId' => $questionId, 'author' => $author->value, 'authorName' => $authorName, 'body' => $body, 'createdAt' => $now,
            'status' => 'pending', 'attempts' => 0, 'nextAt' => $now, 'startedAt' => null, 'error' => null, 'mid' => null];

        return $id;
    }

    public function addCuratorReply(int $questionId, string $authorName, string $body, string $mid, \DateTimeImmutable $now): bool
    {
        foreach ($this->messages as $message) {
            if ($mid === $message['mid']) {
                return false;
            }
        }
        $this->messages[1 + count($this->messages)] = ['questionId' => $questionId, 'author' => 'curator', 'authorName' => $authorName, 'body' => $body, 'createdAt' => $now,
            'status' => null, 'attempts' => 0, 'nextAt' => null, 'startedAt' => null, 'error' => null, 'mid' => $mid];

        return true;
    }

    public function questionByOutgoingMid(string $mid): ?int
    {
        foreach ($this->messages as $message) {
            if ($mid === $message['mid'] && in_array($message['author'], ['parent', 'staff'], true)) {
                return $message['questionId'];
            }
        }

        return null;
    }

    public function messages(int $questionId): array
    {
        $rows = [];
        foreach ($this->messages as $id => $message) {
            if ($questionId === $message['questionId']) {
                $rows[] = ['id' => $id, 'author' => $message['author'], 'authorName' => $message['authorName'], 'body' => $message['body'],
                    'createdAt' => $message['createdAt']->format('Y-m-d\TH:i:s\Z'), 'deliveryStatus' => $message['status']];
            }
        }

        return $rows;
    }

    public function countParentQuestions(int $groupId, \DateTimeImmutable $since): int
    {
        return count(array_filter($this->questions, static fn(array $question): bool => $groupId === $question['groupId'] && $question['createdAt'] >= $since));
    }

    public function countGuestQuestions(\DateTimeImmutable $since): int
    {
        return count(array_filter($this->questions, static fn(array $question): bool => 'guest' === $question['author'] && $question['createdAt'] >= $since));
    }

    public function forgetGuestAddresses(\DateTimeImmutable $before): void
    {
        $this->guestAddresses = array_filter($this->guestAddresses, static fn(array $window): bool => $window['windowStartedAt'] >= $before);
    }

    public function lockGuestAddress(string $addressHash, \DateTimeImmutable $now): int
    {
        $this->guestAddresses[$addressHash] ??= ['windowStartedAt' => $now, 'questions' => 0];

        return $this->guestAddresses[$addressHash]['questions'];
    }

    public function addGuestAddressQuestion(string $addressHash): void
    {
        ++$this->guestAddresses[$addressHash]['questions'];
    }

    public function countOwnMessages(int $questionId, \DateTimeImmutable $since): int
    {
        return count(array_filter($this->messages, static fn(array $message): bool => $questionId === $message['questionId'] && 'curator' !== $message['author'] && $message['createdAt'] >= $since));
    }

    public function idempotency(string $scope, string $keyHash): ?array
    {
        return $this->keys[$scope . '|' . $keyHash] ?? null;
    }

    public function remember(string $scope, string $keyHash, string $payloadHash, int $questionId, ?string $sealedKey, \DateTimeImmutable $now): void
    {
        $this->keys[$scope . '|' . $keyHash] = ['payloadHash' => $payloadHash, 'questionId' => $questionId, 'sealedKey' => $sealedKey];
    }

    public function claim(int $messageId, \DateTimeImmutable $now, \DateTimeImmutable $staleBefore): ?array
    {
        $message = $this->messages[$messageId] ?? null;
        if (null === $message || 'pending' !== $message['status'] || (null !== $message['nextAt'] && $message['nextAt'] > $now) || $this->blocked($messageId)) {
            return null;
        }
        $this->messages[$messageId]['status'] = 'processing';
        $this->messages[$messageId]['attempts'] = $attempt = $message['attempts'] + 1;
        $this->messages[$messageId]['startedAt'] = $now;
        $question = $this->questions[$message['questionId']];

        return ['id' => $messageId, 'attempt' => $attempt, 'questionId' => $message['questionId'], 'questionAuthor' => $question['author'],
            'authorName' => $message['authorName'], 'context' => $question['context'], 'body' => $message['body']];
    }

    public function delivered(int $messageId, int $attempt, string $mid): void
    {
        $this->finish($messageId, $attempt, 'delivered', null, null, $mid);
    }

    public function retry(int $messageId, int $attempt, \DateTimeImmutable $nextAttemptAt, string $errorCode): void
    {
        $this->finish($messageId, $attempt, 'pending', $errorCode, $nextAttemptAt, null);
    }

    public function failed(int $messageId, int $attempt, string $errorCode): void
    {
        $this->finish($messageId, $attempt, 'failed', $errorCode, null, null);
    }

    public function unknown(int $messageId, int $attempt, string $errorCode): void
    {
        $this->finish($messageId, $attempt, 'unknown', $errorCode, null, null);
    }

    public function recoverStale(\DateTimeImmutable $staleBefore): int
    {
        $count = 0;
        foreach ($this->messages as $id => $message) {
            if ('processing' === $message['status'] && $message['startedAt'] <= $staleBefore) {
                $this->messages[$id]['status'] = 'unknown';
                ++$count;
            }
        }

        return $count;
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        $ids = [];
        foreach ($this->messages as $id => $message) {
            if ('pending' === $message['status'] && $message['nextAt'] <= $now && !$this->blocked($id)) {
                $ids[] = $id;
            }
        }

        return array_slice($ids, 0, $limit);
    }

    public function nextPending(int $questionId): ?int
    {
        foreach ($this->messages as $id => $message) {
            if ($questionId === $message['questionId'] && 'pending' === $message['status']) {
                return $id;
            }
        }

        return null;
    }

    public function countByStatus(): array
    {
        $counts = [];
        foreach ($this->messages as $message) {
            if (null !== $message['status']) {
                $counts[$message['status']] = ($counts[$message['status']] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function seen(int $chatId, string $event, bool $botPresent, \DateTimeImmutable $now): void
    {
        $this->chats[$chatId] = ['chatId' => $chatId, 'lastEvent' => $event, 'botPresent' => $botPresent, 'seenAt' => $now->format('Y-m-d\TH:i:s\Z')];
    }

    public function recent(int $limit): array
    {
        return array_values($this->chats);
    }

    private function blocked(int $messageId): bool
    {
        foreach ($this->messages as $id => $message) {
            if ($id < $messageId && $message['questionId'] === $this->messages[$messageId]['questionId'] && in_array($message['status'], ['pending', 'processing'], true)) {
                return true;
            }
        }

        return false;
    }

    private function finish(int $messageId, int $attempt, string $status, ?string $error, ?\DateTimeImmutable $nextAt, ?string $mid): void
    {
        if ('processing' !== $this->messages[$messageId]['status'] || $attempt !== $this->messages[$messageId]['attempts']) {
            return;
        }
        $this->messages[$messageId]['status'] = $status;
        $this->messages[$messageId]['error'] = $error;
        $this->messages[$messageId]['nextAt'] = $nextAt;
        if (null !== $mid) {
            $this->messages[$messageId]['mid'] = $mid;
        }
    }
}
