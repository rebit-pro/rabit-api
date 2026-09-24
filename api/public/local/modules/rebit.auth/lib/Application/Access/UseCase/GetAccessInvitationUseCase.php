<?php

declare(strict_types=1);

namespace Rebit\Auth\Application\Access\UseCase;

use Rebit\Auth\Application\Access\Contract\AccessAccountInterface;
use Rebit\Auth\Application\Access\Contract\AccessLinkRepositoryInterface;
use Rebit\Auth\Application\Access\Dto\AccessInvitationOutputDto;
use Rebit\Auth\Application\Access\Service\AccessLinkGuard;
use Rebit\Auth\Application\Auth\Contract\ClockInterface;
use Rebit\Auth\Domain\Access\Entity\AccessLink;
use Rebit\Auth\Domain\Access\Enum\AccessLinkPurposeEnum;
use Rebit\Auth\Domain\Access\Service\EmailMask;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Показывает приглашённому сотруднику, для какого адреса и до какого срока действует ссылка, не раскрывая адрес целиком.
 * Ссылка уже активированной учётки считается использованной.
 */
final readonly class GetAccessInvitationUseCase
{
    public function __construct(
        private AccessLinkRepositoryInterface $links,
        private AccessAccountInterface $accounts,
        private AccessLinkGuard $guard,
        private EmailMask $mask,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws HttpException
     */
    public function execute(string $token): AccessInvitationOutputDto
    {
        $link = $this->guard->usable(
            $this->links->findByTokenHash(AccessLink::hashToken($token)),
            AccessLinkPurposeEnum::INVITE,
            $this->clock->now(),
        );
        $account = $this->accounts->findById($link->userId);
        if (null === $account || $account->active || !$account->pending) {
            throw new HttpException('LINK_USED', 410);
        }

        return new AccessInvitationOutputDto(
            maskedEmail: $this->mask->mask($account->email),
            name: $account->name,
            expiresAt: (new \DateTimeImmutable('@' . $link->expiresAt))->format(DATE_ATOM),
        );
    }
}
