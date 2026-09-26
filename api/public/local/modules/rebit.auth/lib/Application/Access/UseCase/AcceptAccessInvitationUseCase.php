<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\UseCase;

use Random\RandomException;
use Rebit\Auth\Application\Access\Contract\AccessAccountInterface;
use Rebit\Auth\Application\Access\Contract\AccessLinkRepositoryInterface;
use Rebit\Auth\Application\Access\Dto\AcceptInvitationInputDto;
use Rebit\Auth\Application\Access\Service\AccessLinkGuard;
use Rebit\Auth\Application\Access\Service\SessionIssuer;
use Rebit\Auth\Application\Auth\Contract\AuthTransactionInterface;
use Rebit\Auth\Application\Auth\Contract\ClockInterface;
use Rebit\Auth\Application\Auth\Dto\Result\LoginResultDto;
use Rebit\Auth\Domain\Access\Entity\AccessLink;
use Rebit\Auth\Domain\Access\Enum\AccessLinkPurposeEnum;
use Rebit\Auth\Domain\Access\Service\PasswordPolicy;
use Rebit\Share\Application\Contract\Consent\ConsentRecorderInterface;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Завершает приглашение: сотрудник задаёт пароль и принимает свои юридические документы, учётка активируется,
 * ссылка гаснет и сразу открывается сессия. Повторное открытие той же ссылки после этого отклоняется.
 */
final readonly class AcceptAccessInvitationUseCase
{
    public function __construct(
        private AccessLinkRepositoryInterface $links,
        private AccessAccountInterface $accounts,
        private AccessLinkGuard $guard,
        private PasswordPolicy $policy,
        private SessionIssuer $sessions,
        private ClockInterface $clock,
        private AuthTransactionInterface $transaction,
        private ConsentRecorderInterface $consents,
    ) {}

    /**
     * @throws HttpException
     * @throws RandomException
     */
    public function execute(AcceptInvitationInputDto $input): LoginResultDto
    {
        return $this->transaction->run(function() use ($input): LoginResultDto {
            $now = $this->clock->now();
            $link = $this->guard->usable(
                $this->links->findByTokenHashForUpdate(AccessLink::hashToken($input->token)),
                AccessLinkPurposeEnum::INVITE,
                $now,
            );
            $account = $this->accounts->lockById($link->userId);
            if (null === $account || $account->active || !$account->pending) {
                throw new HttpException('LINK_USED', 410);
            }
            if (!$this->policy->isAcceptable($input->password, $account->email)) {
                throw new HttpException('PASSWORD_WEAK', 422);
            }
            $this->consents->record(ConsentContextEnum::STAFF, $account->id, $input->consents);

            $this->accounts->changePassword($account->id, $input->password);
            $this->accounts->activate($account->id);
            $this->links->markUsed($link->id, $now);

            return $this->sessions->issue($account);
        });
    }
}
