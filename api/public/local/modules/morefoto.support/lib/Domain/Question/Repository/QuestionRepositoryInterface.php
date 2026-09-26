<?php

declare(strict_types=1);

namespace Morefoto\Support\Domain\Question\Repository;

use Morefoto\Support\Domain\Question\Enum\AuthorEnum;

/** Беседы, реплики и ключи повторов; изменяющие методы вызываются внутри транзакции сценария. */
interface QuestionRepositoryInterface
{
    /** @return null|array{id: int, authorName: string} */
    public function findParent(string $keyHash): ?array;

    /** @return null|array{id: int, authorName: string} */
    public function findStaff(int $userId): ?array;

    /** Блокирует беседу до конца транзакции; false — беседы нет. */
    public function lock(int $questionId): bool;

    /** @return null|array{id: int, authorName: string} */
    public function lockStaff(int $userId): ?array;

    public function createParent(string $keyHash, int $groupId, string $authorName, string $context, \DateTimeImmutable $now): int;

    public function createStaff(int $userId, string $authorName, string $context, \DateTimeImmutable $now): int;

    public function createGuest(string $authorName, string $context, \DateTimeImmutable $now): int;

    /** Имя и учреждения сотрудника меняются: следующая реплика уходит в MAX с актуальным контекстом. */
    public function refreshStaff(int $questionId, string $authorName, string $context): void;

    /** Новая реплика автора в статусе pending — она же задание доставки в MAX. */
    public function addMessage(int $questionId, AuthorEnum $author, string $authorName, string $body, \DateTimeImmutable $now): int;

    /** false — реплика с этим mid уже сохранена (повтор webhook). */
    public function addCuratorReply(int $questionId, string $authorName, string $body, string $mid, \DateTimeImmutable $now): bool;

    /** Беседа, в которую бот отправил сообщение с этим mid. */
    public function questionByOutgoingMid(string $mid): ?int;

    /** @return list<array{
     *     id: int,
     *     author: string,
     *     authorName: string,
     *     body: string,
     *     createdAt: string,
     *     deliveryStatus: ?string,
     * }> */
    public function messages(int $questionId): array;

    public function countParentQuestions(int $groupId, \DateTimeImmutable $since): int;

    public function countGuestQuestions(\DateTimeImmutable $since): int;

    /** Удаляет счётчики адресов гостей с окном, начатым раньше момента: хеш адреса не хранится дольше окна. */
    public function forgetGuestAddresses(\DateTimeImmutable $before): void;

    /** Блокирует счётчик адреса до конца транзакции (новое окно начинается с $now) и возвращает обращения в окне. */
    public function lockGuestAddress(string $addressHash, \DateTimeImmutable $now): int;

    /** Засчитывает принятое обращение в окно заблокированного адреса. */
    public function addGuestAddressQuestion(string $addressHash): void;

    /** Реплики автора беседы (без ответов куратора) начиная с момента. */
    public function countOwnMessages(int $questionId, \DateTimeImmutable $since): int;

    /** @return null|array{payloadHash: string, questionId: int, sealedKey: ?string} */
    public function idempotency(string $scope, string $keyHash): ?array;

    public function remember(string $scope, string $keyHash, string $payloadHash, int $questionId, ?string $sealedKey, \DateTimeImmutable $now): void;
}
