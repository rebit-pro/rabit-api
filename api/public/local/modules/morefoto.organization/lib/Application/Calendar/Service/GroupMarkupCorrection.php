<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Calendar\Service;

use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Domain\Calendar\Exception\CalendarRuleViolation;
use Morefoto\Organization\Domain\Calendar\Repository\GroupCalendarRepository;
use Morefoto\Organization\Domain\Calendar\Service\MarkupCorrectionPolicy;
use Morefoto\Organization\Domain\Calendar\ValueObject\GroupCalendar as Calendar;
use Rebit\Share\Contracts\Organization\Dto\MarkupCorrectionInputDto;
use Rebit\Share\Contracts\Organization\Dto\MarkupCorrectionOutputDto;
use Rebit\Share\Contracts\Organization\GroupMarkupCorrectionInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Ставит переданную группу на паузу продаж для исправления разметки (D4) и снимает паузу. Момент начала хранится в
 * группе, каждое изменение повышает её ревизию, чтобы сохранённые расчёты корзины устарели, и попадает в журнал
 * Organization; T0 и сроки продаж не меняются.
 */
final readonly class GroupMarkupCorrection implements GroupMarkupCorrectionInterface
{
    public function __construct(
        private GroupCalendarRepository $calendars,
        private MarkupCorrectionPolicy $policy,
        private CalendarClockInterface $clock,
    ) {}

    public function start(MarkupCorrectionInputDto $input): MarkupCorrectionOutputDto
    {
        $row = $this->row($input->groupId);
        if (null !== $row['UF_MARKUP_CORRECTION_AT']) {
            return $this->output($input->groupId, (int)$row['UF_REVISION'], (string)$row['UF_MARKUP_CORRECTION_AT'], (string)$row['UF_TIMEZONE']);
        }
        $now = $this->clock->now();
        $calendar = Calendar::fromStorage($row['UF_SENT_AT'], $row['UF_CLOSES_AT'], $row['UF_DIGITAL_SALES_UNTIL'], $row['UF_DELIVERY_DUE_AT'], (string)$row['UF_TIMEZONE']);
        try {
            $this->policy->assertCanStart($calendar->status($now));
        } catch (CalendarRuleViolation $violation) {
            throw new HttpException($violation->getMessage(), 409, $violation);
        }

        return $this->save($input, $row, $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'startMarkupCorrection');
    }

    public function finish(MarkupCorrectionInputDto $input): MarkupCorrectionOutputDto
    {
        $row = $this->row($input->groupId);
        if (null === $row['UF_MARKUP_CORRECTION_AT']) {
            throw new HttpException('CORRECTION_NOT_STARTED', 409);
        }

        return $this->save($input, $row, null, 'finishMarkupCorrection');
    }

    /** @return array<string, mixed> */
    private function row(string $groupId): array
    {
        // lock() already acquired parents and the group before the caller locked profiles/Auth.
        $row = $this->calendars->find($groupId, true)->fetch();
        if (false === $row) {
            throw new HttpException('NOT_FOUND', 404);
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    private function save(MarkupCorrectionInputDto $input, array $row, ?string $startedAt, string $action): MarkupCorrectionOutputDto
    {
        $revision = (int)$row['UF_REVISION'];
        if (!$this->calendars->saveMarkupCorrection((int)$row['ID'], $revision, $startedAt)) {
            throw new HttpException('VERSION_CONFLICT', 409);
        }
        $this->calendars->record(
            id: (int)$row['ID'],
            from: $revision,
            to: $revision + 1,
            actor: $input->actorUserId,
            operationId: $input->operationId,
            delta: json_encode(['action' => $action, 'before' => $row['UF_MARKUP_CORRECTION_AT'], 'after' => $startedAt], JSON_THROW_ON_ERROR),
        );

        return $this->output($input->groupId, $revision + 1, $startedAt, (string)$row['UF_TIMEZONE']);
    }

    private function output(string $groupId, int $revision, ?string $startedAt, string $timezone): MarkupCorrectionOutputDto
    {
        return new MarkupCorrectionOutputDto(
            groupId: $groupId,
            revision: $revision,
            startedAt: null === $startedAt ? null : new \DateTimeImmutable($startedAt, new \DateTimeZone('UTC'))->setTimezone(new \DateTimeZone($timezone))->format(\DateTimeInterface::ATOM),
        );
    }
}
