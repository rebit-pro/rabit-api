<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\UseCase;

use Random\RandomException;
use Rebit\Auth\Application\Access\Contract\AccessAccountInterface;
use Rebit\Auth\Application\Access\Contract\AccessLinkMailerInterface;
use Rebit\Auth\Application\Access\Contract\AccessLinkRepositoryInterface;
use Rebit\Auth\Application\Access\Contract\AccessTokenGeneratorInterface;
use Rebit\Auth\Application\Access\Dto\AccessLinkMailInputDto;
use Rebit\Auth\Application\Access\Dto\PasswordResetInputDto;
use Rebit\Auth\Application\Auth\Contract\AuthTransactionInterface;
use Rebit\Auth\Application\Auth\Contract\ClockInterface;
use Rebit\Auth\Domain\Access\Entity\AccessLink;
use Rebit\Auth\Domain\Access\Enum\AccessLinkPurposeEnum;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Отправляет ссылку для нового пароля, не раскрывая, есть ли такой адрес: результат одинаков для любого email.
 * Сотруднику, который ещё не завершил регистрацию, вместо сброса повторно уходит приглашение; частые повторы молча пропускаются.
 */
final readonly class RequestPasswordResetUseCase
{
    public function __construct(
        private AccessAccountInterface $accounts,
        private AccessLinkRepositoryInterface $links,
        private AccessTokenGeneratorInterface $tokens,
        private AccessLinkMailerInterface $mailer,
        private IssueAccessInvitationUseCase $invitations,
        private ClockInterface $clock,
        private AuthTransactionInterface $transaction,
        private int $ttlMinutes,
        private int $cooldownSeconds,
    ) {
        if (0 >= $ttlMinutes || 0 > $cooldownSeconds) {
            throw new \InvalidArgumentException('Password reset lifetime configuration is invalid.');
        }
    }

    /**
     * @throws HttpException
     * @throws RandomException
     */
    public function execute(PasswordResetInputDto $input): void
    {
        $this->transaction->run(function() use ($input): void {
            $account = $this->accounts->lockByEmail(mb_strtolower(trim($input->email)));
            if (null === $account) {
                return;
            }
            $now = $this->clock->now();
            if (!$account->active && $account->pending) {
                $current = $this->links->findForUserForUpdate($account->id, AccessLinkPurposeEnum::INVITE);
                if (null === $current || !$current->isResendBlocked($now)) {
                    $this->invitations->execute($account->id, null, force: true);
                }

                return;
            }
            if (!$account->active) {
                return;
            }
            $current = $this->links->findForUserForUpdate($account->id, AccessLinkPurposeEnum::RESET);
            if (null !== $current && $current->isResendBlocked($now)) {
                return;
            }

            $token = $this->tokens->generate();
            $expiresAt = $now + ($this->ttlMinutes * 60);
            $this->links->replace(
                userId: $account->id,
                purpose: AccessLinkPurposeEnum::RESET,
                tokenHash: AccessLink::hashToken($token),
                issuedAt: $now,
                expiresAt: $expiresAt,
                resendAvailableAt: $now + $this->cooldownSeconds,
                issuedBy: null,
            );
            $this->mailer->sendPasswordReset(new AccessLinkMailInputDto(
                userId: $account->id,
                recipient: $account->email,
                name: $account->name,
                token: $token,
                issuedAt: $now,
                expiresAt: $expiresAt,
            ));
        });
    }
}
