<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Calendar\Service;

use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Domain\Calendar\Exception\CalendarRuleViolation;
use Morefoto\Organization\Domain\Calendar\Repository\GroupCalendarRepository;
use Morefoto\Organization\Domain\Calendar\ValueObject\GroupCalendar as Calendar;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto;
use Rebit\Share\Contracts\Organization\Dto\CalendarMutationOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;
use Rebit\Share\Contracts\Organization\Dto\LinkSentInputDto;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Единственный владелец календаря приёма группы: блокирует группу для внешнего сценария и меняет сроки по правилам D10.
 * Запись и исправление факта передачи ссылки пересчитывают закрытие и доставку, сохраняя изменение в журнале Organization.
 */
final readonly class GroupCalendar implements GroupCalendarInterface
{
    public function __construct(
        private GroupCalendarRepository $calendars,
        private InstitutionOperationRepository $operations,
        private CalendarClockInterface $clock,
        private CalendarCommandValidator $validator,
    ) {}

    public function lock(string $groupId): int
    {
        $institutionId = $this->calendars->lockParents($groupId);
        if (0 === $institutionId) {
            throw new HttpException('NOT_FOUND', 404);
        }

        return $institutionId;
    }

    public function get(string $groupId): CalendarMutationOutputDto
    {
        $row = $this->calendars->find($groupId)->fetch();
        if (false === $row) {
            throw new HttpException('NOT_FOUND', 404);
        }

        return new CalendarMutationOutputDto(
            $groupId,
            (int)$row['UF_REVISION'],
            CalendarProjection::create(Calendar::fromStorage($row['UF_SENT_AT'], $row['UF_CLOSES_AT'], $row['UF_DELIVERY_DUE_AT'], (string)$row['UF_TIMEZONE']), $this->clock->now()),
        );
    }

    public function confirmLinkSent(CalendarCommandInputDto $input): CalendarMutationOutputDto
    {
        $this->validator->validate($input);

        return $this->mutate($input, null);
    }

    public function extend(CalendarCommandInputDto $input, \DateTimeImmutable $newClosesAt): CalendarMutationOutputDto
    {
        $this->validator->validate($input);

        // DATETIME persistence has second precision; reject silent deadline truncation.
        if ('000000' !== $newClosesAt->format('u') || 1000 > (int)$newClosesAt->format('Y') || 9999 < (int)$newClosesAt->format('Y')) {
            throw new \InvalidArgumentException('A whole-second calendar deadline within the database range is required.');
        }

        return $this->mutate($input, $newClosesAt);
    }

    public function recordLinkSent(LinkSentInputDto $input): CalendarMutationOutputDto
    {
        return $this->deliver($input, false);
    }

    public function correctLinkSent(LinkSentInputDto $input): CalendarMutationOutputDto
    {
        return $this->deliver($input, true);
    }

    /** Handoff owns idempotency of delivery commands; every calendar change still lands in the organization journal. */
    private function deliver(LinkSentInputDto $input, bool $correction): CalendarMutationOutputDto
    {
        // lock() already acquired parents and the group before the caller locked profiles/Auth.
        $row = $this->calendars->find($input->groupId, true)->fetch();
        if (false === $row) {
            throw new HttpException('NOT_FOUND', 404);
        }
        $revision = (int)$row['UF_REVISION'];
        $before = Calendar::fromStorage($row['UF_SENT_AT'], $row['UF_CLOSES_AT'], $row['UF_DELIVERY_DUE_AT'], (string)$row['UF_TIMEZONE']);
        $now = $this->clock->now();
        try {
            $after = $correction ? $before->correctLinkSent($input->sentAt, $now) : $before->recordLinkSent($input->sentAt, $now);
        } catch (CalendarRuleViolation $violation) {
            throw new HttpException($violation->getMessage(), 'LINK_NOT_SENT' === $violation->getMessage() ? 409 : 422, $violation);
        }
        if ($after === $before) {
            return new CalendarMutationOutputDto($input->groupId, $revision, CalendarProjection::create($before, $now));
        }
        if (!$this->calendars->save((int)$row['ID'], $revision, $after)) {
            throw new HttpException('VERSION_CONFLICT', 409);
        }
        $this->calendars->record(
            id: (int)$row['ID'],
            from: $revision,
            to: $revision + 1,
            actor: $input->actorUserId,
            operationId: $input->operationId,
            delta: json_encode([
                'action' => $correction ? 'correctLinkSent' : 'recordLinkSent',
                'reason' => $input->reason,
                'before' => CalendarProjection::create($before, $now),
                'after' => CalendarProjection::create($after, $now),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        return new CalendarMutationOutputDto($input->groupId, $revision + 1, CalendarProjection::create($after, $now));
    }

    private function mutate(CalendarCommandInputDto $input, ?\DateTimeImmutable $newClosesAt): CalendarMutationOutputDto
    {
        $operation = (null === $newClosesAt ? 'calendar.confirm/' : 'calendar.extend/') . $input->groupId;
        $hash = hash('sha256', json_encode([
            $input->groupId, $input->actorUserId, $input->expectedRevision, $input->reason,
            $newClosesAt?->setTimezone(new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $previous = $this->calendars->findOperation($input->actorUserId, $operation, $input->key)->fetch();
        if (false !== $previous) {
            if (!hash_equals((string)$previous['payload_hash'], $hash)) {
                throw new HttpException('IDEMPOTENCY_CONFLICT', 409);
            }

            return $this->restore((string)$previous['result_json']);
        }
        // lock() already acquired parents and the group before caller locked profiles/Auth.
        $row = $this->calendars->find($input->groupId, true)->fetch();
        if (false === $row) {
            throw new HttpException('NOT_FOUND', 404);
        }
        if ($input->expectedRevision !== (int)$row['UF_REVISION']) {
            throw new HttpException('VERSION_CONFLICT', 409);
        }
        $before = Calendar::fromStorage($row['UF_SENT_AT'], $row['UF_CLOSES_AT'], $row['UF_DELIVERY_DUE_AT'], (string)$row['UF_TIMEZONE']);
        $now = $this->clock->now();
        $after = null === $newClosesAt ? $before->confirmLinkSent($now) : $before->extend($newClosesAt, $now);
        $revision = $input->expectedRevision;
        if ($after !== $before) {
            if (!$this->calendars->save((int)$row['ID'], $revision, $after)) {
                throw new HttpException('VERSION_CONFLICT', 409);
            }
            ++$revision;
            $this->calendars->record(
                id: (int)$row['ID'],
                from: $input->expectedRevision,
                to: $revision,
                actor: $input->actorUserId,
                operationId: Uuid::uuid4()->toString(),
                delta: json_encode([
                    'action' => null === $newClosesAt ? 'confirmLinkSent' : 'extendCalendar',
                    'reason' => $input->reason,
                    'before' => CalendarProjection::create($before, $now),
                    'after' => CalendarProjection::create($after, $now),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );
        }
        $result = new CalendarMutationOutputDto($input->groupId, $revision, CalendarProjection::create($after, $now));
        $this->operations->save($input->actorUserId, $operation, $input->key, $hash, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $result;
    }

    private function restore(string $json): CalendarMutationOutputDto
    {
        /** @var array{
         *     groupId: string,
         *     revision: int,
         *     calendar: array{timezone: string, sentAt: ?string, closesAt: ?string, deliveryDueAt: ?string, status: string},
         * } $result */
        $result = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new CalendarMutationOutputDto(
            $result['groupId'],
            $result['revision'],
            new GroupCalendarOutputDto(
                $result['calendar']['timezone'],
                $result['calendar']['sentAt'],
                $result['calendar']['closesAt'],
                $result['calendar']['deliveryDueAt'],
                $result['calendar']['status'],
            ),
        );
    }
}
