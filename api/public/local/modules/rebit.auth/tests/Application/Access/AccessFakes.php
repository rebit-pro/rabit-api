<?php

declare(strict_types=1);

namespace Rebit\Auth\Tests\Application\Access;

use Bitrix\Main\Type\DateTime;
use Rebit\Auth\Application\Access\Contract\AccessAccountInterface;
use Rebit\Auth\Application\Access\Contract\AccessLinkMailerInterface;
use Rebit\Auth\Application\Access\Contract\AccessLinkRepositoryInterface;
use Rebit\Auth\Application\Access\Contract\AccessTokenGeneratorInterface;
use Rebit\Auth\Application\Access\Dto\AccessAccountDto;
use Rebit\Auth\Application\Access\Dto\AccessLinkMailInputDto;
use Rebit\Auth\Application\Auth\Contract\LoginUserRepositoryInterface;
use Rebit\Auth\Application\Auth\Contract\TokenGeneratorInterface;
use Rebit\Auth\Domain\Access\Entity\AccessLink;
use Rebit\Auth\Domain\Access\Enum\AccessLinkPurposeEnum;
use Rebit\Auth\Domain\User\Entity\UserCredentials;

final class InMemoryAccessAccounts implements AccessAccountInterface
{
    /** @var array<int, AccessAccountDto> */
    public array $accounts = [];

    public function add(int $id, string $email, bool $active, bool $pending, string $password = 'old-password-1'): void
    {
        $this->accounts[$id] = new AccessAccountDto($id, $email, 'Анна', $active, $pending, password_hash($password, PASSWORD_DEFAULT));
    }

    public function findById(int $userId): ?AccessAccountDto
    {
        return $this->accounts[$userId] ?? null;
    }

    public function lockById(int $userId): ?AccessAccountDto
    {
        return $this->accounts[$userId] ?? null;
    }

    public function lockByEmail(string $email): ?AccessAccountDto
    {
        foreach ($this->accounts as $account) {
            if (mb_strtolower($account->email) === mb_strtolower($email)) {
                return $account;
            }
        }

        return null;
    }

    public function changePassword(int $userId, string $password): void
    {
        $a = $this->accounts[$userId];
        $this->accounts[$userId] = new AccessAccountDto($a->id, $a->email, $a->name, $a->active, $a->pending, password_hash($password, PASSWORD_DEFAULT));
    }

    public function activate(int $userId): void
    {
        $a = $this->accounts[$userId];
        $this->accounts[$userId] = new AccessAccountDto($a->id, $a->email, $a->name, true, false, $a->passwordHash);
    }
}

final class InMemoryAccessLinks implements AccessLinkRepositoryInterface
{
    /** @var array<string, AccessLink> */
    public array $links = [];
    private int $nextId = 1;

    public function findByTokenHash(string $tokenHash): ?AccessLink
    {
        foreach ($this->links as $link) {
            if ($link->tokenHash === $tokenHash) {
                return $link;
            }
        }

        return null;
    }

    public function findByTokenHashForUpdate(string $tokenHash): ?AccessLink
    {
        return $this->findByTokenHash($tokenHash);
    }

    public function findForUserForUpdate(int $userId, AccessLinkPurposeEnum $purpose): ?AccessLink
    {
        return $this->links[$userId . ':' . $purpose->value] ?? null;
    }

    public function replace(
        int $userId,
        AccessLinkPurposeEnum $purpose,
        string $tokenHash,
        int $issuedAt,
        int $expiresAt,
        int $resendAvailableAt,
        ?int $issuedBy,
    ): void {
        $key = $userId . ':' . $purpose->value;
        $id = $this->links[$key]->id ?? $this->nextId++;
        $this->links[$key] = new AccessLink($id, $userId, $purpose, $tokenHash, $issuedAt, $expiresAt, $resendAvailableAt, null, $issuedBy);
    }

    public function markUsed(int $id, int $usedAt): void
    {
        foreach ($this->links as $key => $l) {
            if ($l->id === $id) {
                $this->links[$key] = new AccessLink($l->id, $l->userId, $l->purpose, $l->tokenHash, $l->issuedAt, $l->expiresAt, $l->resendAvailableAt, $usedAt, $l->issuedBy);
            }
        }
    }

    public function invitationsFor(array $userIds): array
    {
        $output = [];
        foreach ($userIds as $userId) {
            if (isset($this->links[$userId . ':invite'])) {
                $output[$userId] = $this->links[$userId . ':invite'];
            }
        }

        return $output;
    }

    /** Shifts every link of the user and purpose into the past, as if the clock had moved on. */
    public function age(int $userId, AccessLinkPurposeEnum $purpose, int $seconds): void
    {
        $l = $this->links[$userId . ':' . $purpose->value];
        $this->links[$userId . ':' . $purpose->value] = new AccessLink(
            $l->id,
            $l->userId,
            $l->purpose,
            $l->tokenHash,
            $l->issuedAt - $seconds,
            $l->expiresAt - $seconds,
            $l->resendAvailableAt - $seconds,
            $l->usedAt,
            $l->issuedBy,
        );
    }
}

final class SequenceAccessTokens implements AccessTokenGeneratorInterface
{
    private int $counter = 0;

    public function generate(): string
    {
        ++$this->counter;

        return str_pad('token' . $this->counter, 43, 'x');
    }
}

final class RecordingAccessMailer implements AccessLinkMailerInterface
{
    /** @var list<array{string, AccessLinkMailInputDto}> */
    public array $sent = [];

    public function sendInvitation(AccessLinkMailInputDto $mail): void
    {
        $this->sent[] = ['invite', $mail];
    }

    public function sendPasswordReset(AccessLinkMailInputDto $mail): void
    {
        $this->sent[] = ['reset', $mail];
    }
}

final class RecordingSessions implements LoginUserRepositoryInterface
{
    /** @var array<int, string> */
    public array $tokens = [];

    public function findActiveByEmail(string $email): ?UserCredentials
    {
        return null;
    }

    public function findActiveByEmailForUpdate(string $email): ?UserCredentials
    {
        return null;
    }

    public function updateToken(int $userId, string $token, DateTime $expiresAt): void
    {
        $this->tokens[$userId] = $token;
    }
}

final class SequenceSessionTokens implements TokenGeneratorInterface
{
    private int $counter = 0;

    public function generate(): string
    {
        return 'session-' . ++$this->counter;
    }
}
