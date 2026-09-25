<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Calendar\UseCase;

use Morefoto\Organization\Application\Calendar\Service\CalendarCommandValidator;
use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto;
use Rebit\Share\Contracts\Organization\Dto\CalendarMutationOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Внутренний сценарий C3: организатор подтверждает передачу ссылки или продлевает приём группы в одной транзакции
 * с блокировками Access и Organization. Невалидная команда отклоняется до транзакции; публичного маршрута у сценария нет.
 */
final readonly class ChangeGroupCalendarUseCase
{
    public function __construct(
        private GroupCalendarInterface $calendar,
        private InstitutionAccessInterface $access,
        private InstitutionTransactionInterface $transaction,
        private CalendarCommandValidator $validator,
    ) {}

    public function confirmLinkSent(CalendarCommandInputDto $input, string $bearer): CalendarMutationOutputDto
    {
        return $this->execute($input, $bearer, null);
    }

    public function extend(CalendarCommandInputDto $input, \DateTimeImmutable $newClosesAt, string $bearer): CalendarMutationOutputDto
    {
        return $this->execute($input, $bearer, $newClosesAt);
    }

    private function execute(CalendarCommandInputDto $input, string $bearer, ?\DateTimeImmutable $newClosesAt): CalendarMutationOutputDto
    {
        $this->validator->validate($input);

        return $this->transaction->execute(function() use ($input, $bearer, $newClosesAt): CalendarMutationOutputDto {
            $this->access->lockState();
            if ('organizer' !== $this->access->scope($input->actorUserId)->role) {
                throw new HttpException('FORBIDDEN', 403);
            }
            $this->calendar->lock($input->groupId);
            $this->access->lockParticipants($input->actorUserId, $bearer, []);

            return null === $newClosesAt ? $this->calendar->confirmLinkSent($input) : $this->calendar->extend($input, $newClosesAt);
        });
    }
}
