<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\UseCase;

use Random\RandomException;
use Rebit\Auth\Application\Access\Contract\AccessAccountInterface;
use Rebit\Auth\Application\Access\Contract\AccessLinkMailerInterface;
use Rebit\Auth\Application\Access\Contract\AccessLinkRepositoryInterface;
use Rebit\Auth\Application\Access\Contract\AccessTokenGeneratorInterface;
use Rebit\Auth\Application\Access\Dto\AccessInvitationStateOutputDto;
use Rebit\Auth\Application\Access\Dto\AccessLinkMailInputDto;
use Rebit\Auth\Application\Auth\Contract\ClockInterface;
use Rebit\Auth\Domain\Access\Entity\AccessLink;
use Rebit\Auth\Domain\Access\Enum\AccessLinkPurposeEnum;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Приглашает сотрудника, которому организатор выдал доступ: выпускает одноразовую ссылку и ставит письмо в очередь.
 * Работает только для учётки, ожидающей регистрации; новая ссылка заменяет прежнюю, частые повторы отклоняются.
 * Транзакцией владеет вызывающий сценарий.
 */
final readonly class IssueAccessInvitationUseCase
{
    public function __construct(
        private AccessAccountInterface $accounts,
        private AccessLinkRepositoryInterface $links,
        private AccessTokenGeneratorInterface $tokens,
        private AccessLinkMailerInterface $mailer,
        private ClockInterface $clock,
        private int $ttlHours,
        private int $cooldownSeconds,
    ) {
        if (0 >= $ttlHours || 0 > $cooldownSeconds) {
            throw new \InvalidArgumentException('Invitation lifetime configuration is invalid.');
        }
    }

    /**
     * @param bool $force skips the resend cooldown when the organizer changed the address
     *
     * @throws HttpException
     * @throws RandomException
     */
    public function execute(int $userId, ?int $issuedBy, bool $force = false): AccessInvitationStateOutputDto
    {
        $account = $this->accounts->lockById($userId);
        if (null === $account || $account->active || !$account->pending) {
            throw new HttpException('INVITATION_NOT_AVAILABLE', 409);
        }
        $now = $this->clock->now();
        $current = $this->links->findForUserForUpdate($userId, AccessLinkPurposeEnum::INVITE);
        if (!$force && null !== $current && $current->isResendBlocked($now)) {
            throw new HttpException('RATE_LIMITED', 429);
        }

        $token = $this->tokens->generate();
        $expiresAt = $now + ($this->ttlHours * 3600);
        $this->links->replace(
            userId: $userId,
            purpose: AccessLinkPurposeEnum::INVITE,
            tokenHash: AccessLink::hashToken($token),
            issuedAt: $now,
            expiresAt: $expiresAt,
            resendAvailableAt: $now + $this->cooldownSeconds,
            issuedBy: $issuedBy,
        );
        $this->mailer->sendInvitation(new AccessLinkMailInputDto(
            userId: $userId,
            recipient: $account->email,
            name: $account->name,
            token: $token,
            issuedAt: $now,
            expiresAt: $expiresAt,
        ));

        return new AccessInvitationStateOutputDto(
            userId: $userId,
            sentAt: self::iso($now),
            expiresAt: self::iso($expiresAt),
            state: 'sent',
        );
    }

    private static function iso(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))->format(DATE_ATOM);
    }
}
