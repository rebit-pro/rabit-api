<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\UseCase;

use Rebit\Auth\Application\Access\Contract\AccessAccountInterface;
use Rebit\Auth\Application\Access\Dto\ChangePasswordInputDto;
use Rebit\Auth\Application\Auth\Contract\AuthTransactionInterface;
use Rebit\Auth\Domain\Access\Service\PasswordPolicy;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Меняет пароль вошедшего сотрудника после проверки текущего пароля; текущая сессия остаётся открытой.
 */
final readonly class ChangePasswordUseCase
{
    public function __construct(
        private AccessAccountInterface $accounts,
        private PasswordPolicy $policy,
        private AuthTransactionInterface $transaction,
    ) {}

    /**
     * @throws HttpException
     */
    public function execute(int $userId, ChangePasswordInputDto $input): void
    {
        $this->transaction->run(function() use ($userId, $input): void {
            $account = $this->accounts->lockById($userId);
            if (null === $account || !$account->active) {
                throw new HttpException('SESSION_REVOKED', 401);
            }
            if (!password_verify($input->currentPassword, $account->passwordHash)) {
                throw new HttpException('CURRENT_PASSWORD_INVALID', 422);
            }
            if (!$this->policy->isAcceptable($input->newPassword, $account->email)) {
                throw new HttpException('PASSWORD_WEAK', 422);
            }
            $this->accounts->changePassword($account->id, $input->newPassword);
        });
    }
}
