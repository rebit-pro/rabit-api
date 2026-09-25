<?php

declare(strict_types=1);

use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Application\Calendar\Service\CalendarCommandValidator;
use Morefoto\Organization\Application\Calendar\Service\GroupCalendar;
use Morefoto\Organization\Application\Calendar\UseCase\ChangeGroupCalendarUseCase;
use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Domain\Calendar\Repository\GroupCalendarRepository;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DI\ServiceLocator;
use Ramsey\Uuid\Uuid;

/**
 * Run only in the disposable C3 native fixture after migrations and real Auth setup.
 *
 * @param array{
 *     connection: Connection,
 *     locator: ServiceLocator,
 *     assert: callable(bool, string): void,
 *     staff: array{organizer: int, teacher: int, teacher2: int, inactiveteacher: int, wrongrole: int},
 *     actorBearer: string,
 *     createGroup: callable(): int,
 * } $context
 */
return static function(array $context): void {
    $connection = $context['connection'];
    $locator = $context['locator'];
    $assert = $context['assert'];
    $actor = $context['staff']['organizer'];
    $bearer = $context['actorBearer'];
    $group = $context['createGroup']();
    $row = $connection->query("SELECT UF_PUBLIC_ID,UF_REVISION FROM b_hlbd_mf_group WHERE ID={$group}")->fetch();
    $id = (string)$row['UF_PUBLIC_ID'];
    $revision = (int)$row['UF_REVISION'];
    /** @var GroupCalendarInterface $calendar */
    $calendar = $locator->get(GroupCalendarInterface::class);
    /** @var ChangeGroupCalendarUseCase $commands */
    $commands = $locator->get(ChangeGroupCalendarUseCase::class);
    /** @var InstitutionAccessInterface $access */
    $access = $locator->get(InstitutionAccessInterface::class);
    /** @var InstitutionTransactionInterface $transaction */
    $transaction = $locator->get(InstitutionTransactionInterface::class);
    $assert($calendar instanceof GroupCalendar, 'C3 calendar: DI resolves the persistent provider');
    $assert($calendar === $locator->get(GroupCalendarInterface::class), 'C3 calendar: provider is a stateless singleton');
    $expect = static function(callable $operation, string $class, ?int $code, string $label) use ($assert): void {
        $failure = null;
        try {
            $operation();
        } catch (Throwable $exception) {
            $failure = $exception;
        }
        $assert($failure instanceof $class && (null === $code || $code === $failure->getCode()), $label);
    };
    $snapshot = static function() use ($connection, $group, $id, $actor): array {
        return [
            'group' => $connection->query("SELECT DATE_FORMAT(UF_SENT_AT,'%Y-%m-%d %H:%i:%s') AS UF_SENT_AT,DATE_FORMAT(UF_CLOSES_AT,'%Y-%m-%d %H:%i:%s') AS UF_CLOSES_AT,DATE_FORMAT(UF_DELIVERY_DUE_AT,'%Y-%m-%d %H:%i:%s') AS UF_DELIVERY_DUE_AT,UF_REVISION FROM b_hlbd_mf_group WHERE ID={$group}")->fetch(),
            'history' => $connection->query("SELECT COUNT(*) AS N FROM b_hlbd_mf_organization_change WHERE UF_AGGREGATE_TYPE='group' AND UF_AGGREGATE_ID={$group}")->fetch(),
            'operations' => $connection->query("SELECT COUNT(*) AS N FROM mf_institution_operation WHERE actor_id={$actor} AND operation IN ('calendar.confirm/{$id}','calendar.extend/{$id}')")->fetch(),
        ];
    };
    $empty = $calendar->get($id);
    $assert('preparing' === $empty->calendar->status && null === $empty->calendar->sentAt && null === $empty->calendar->closesAt && null === $empty->calendar->deliveryDueAt, 'C3 calendar: an unsent persisted group is preparing');
    $before = $snapshot();
    $input = new CalendarCommandInputDto($id, $actor, $revision, md5('native-calendar-confirm'), 'Первое подтверждённое вручение ссылки');
    $started = time();
    $sent = $commands->confirmLinkSent($input, $bearer);
    $ended = time();
    $sentAt = new DateTimeImmutable($sent->calendar->sentAt);
    $close = new DateTimeImmutable($sent->calendar->closesAt);
    $delivery = new DateTimeImmutable($sent->calendar->deliveryDueAt);
    $assert($started <= $sentAt->getTimestamp() && $ended >= $sentAt->getTimestamp(), 'C3 calendar: first delivery uses the real server clock');
    $assert('Europe/Moscow' === $sent->calendar->timezone && '+03:00' === $sentAt->format('P'), 'C3 calendar: public timestamps use Moscow offset');
    $assert($sentAt->add(new DateInterval('P7D')) == $close && $close->add(new DateInterval('P7D')) == $delivery, 'C3 calendar: close and delivery are consecutive calendar weeks');
    $assert($revision + 1 === $sent->revision && 'open' === $sent->calendar->status, 'C3 calendar: first confirmation advances the group revision');
    $saved = $snapshot();
    $assert($sentAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') === $saved['group']['UF_SENT_AT'], 'C3 calendar: native DATETIME is stored in UTC');
    $assert((int)$before['history']['N'] + 1 === (int)$saved['history']['N'], 'C3 calendar: first confirmation records exactly one typed history entry');
    $replayed = $commands->confirmLinkSent($input, $bearer);
    $assert(json_encode($sent, JSON_THROW_ON_ERROR) === json_encode($replayed, JSON_THROW_ON_ERROR) && $saved === $snapshot(), 'C3 calendar: replay preserves the original result and all persistent counters');
    $expect(
        static fn() => $commands->confirmLinkSent(new CalendarCommandInputDto($id, $actor, $revision, $input->key, 'Другой payload'), $bearer),
        HttpException::class,
        409,
        'C3 calendar: idempotency key with a changed payload is rejected',
    );
    $again = $commands->confirmLinkSent(new CalendarCommandInputDto($id, $actor, $sent->revision, md5('native-calendar-retransmit'), 'Повторная передача'), $bearer);
    $assert($sent->revision === $again->revision && $sent->calendar == $again->calendar, 'C3 calendar: retransmission does not shift dates or increment the revision');
    $assert($saved['history'] === $snapshot()['history'], 'C3 calendar: retransmission does not fabricate a calendar history revision');
    $expect(
        static fn() => $commands->extend(new CalendarCommandInputDto($id, $actor, $revision, md5('native-calendar-stale'), 'Устаревшая команда'), $close->add(new DateInterval('P1D')), $bearer),
        HttpException::class,
        409,
        'C3 calendar: stale group revision is rejected',
    );
    $expect(
        static fn() => $commands->extend(new CalendarCommandInputDto($id, $actor, $sent->revision, md5('native-calendar-same-close'), 'Срок без изменения'), $close, $bearer),
        DomainException::class,
        null,
        'C3 calendar: extension must be strictly later than the previous close',
    );
    $extension = new CalendarCommandInputDto($id, $actor, $sent->revision, md5('native-calendar-extend'), 'Продление согласовано с куратором');
    $newClose = $close->add(new DateInterval('P2D'));
    $extended = $commands->extend($extension, $newClose, $bearer);
    $assert($sent->revision + 1 === $extended->revision && $sent->calendar->sentAt === $extended->calendar->sentAt, 'C3 calendar: extension advances revision but preserves first delivery');
    $assert($newClose->format(DateTimeInterface::ATOM) === $extended->calendar->closesAt && $newClose->add(new DateInterval('P7D'))->format(DateTimeInterface::ATOM) === $extended->calendar->deliveryDueAt, 'C3 calendar: delivery moves from the extended deadline');
    $extensionSnapshot = $snapshot();
    $assert($extended == $commands->extend($extension, $newClose, $bearer) && $extensionSnapshot === $snapshot(), 'C3 calendar: extension replay does not advance dates, history or revision');
    $expect(
        static fn() => $commands->extend($extension, $newClose->add(new DateInterval('P1D')), $bearer),
        HttpException::class,
        409,
        'C3 calendar: extension replay with another deadline is rejected',
    );
    $lastHistory = $connection->query("SELECT UF_ACTOR_ID,UF_FROM_REVISION,UF_TO_REVISION,UF_DELTA FROM b_hlbd_mf_organization_change WHERE UF_AGGREGATE_TYPE='group' AND UF_AGGREGATE_ID={$group} ORDER BY UF_TO_REVISION DESC LIMIT 1")->fetch();
    $delta = json_decode((string)$lastHistory['UF_DELTA'], true, 512, JSON_THROW_ON_ERROR);
    $assert($actor === (int)$lastHistory['UF_ACTOR_ID'] && $sent->revision === (int)$lastHistory['UF_FROM_REVISION'] && $extended->revision === (int)$lastHistory['UF_TO_REVISION'] && $extension->reason === $delta['reason'] && 'extendCalendar' === $delta['action'], 'C3 calendar: history identifies actor, reason and revision transition');

    // Calendar is a participant in the caller's transaction; work after it can still fail.
    $expect(
        static fn() => $transaction->execute(static function() use ($access, $calendar, $actor, $bearer, $id, $extended, $newClose): void {
            $access->lockState();
            $calendar->lock($id);
            $access->lockParticipants($actor, $bearer, []);
            $calendar->extend(new CalendarCommandInputDto($id, $actor, $extended->revision, md5('native-calendar-caller-rollback'), 'Проверка общей транзакции'), $newClose->add(new DateInterval('P1D')));
            throw new RuntimeException('Caller failed after the calendar mutation.');
        }),
        RuntimeException::class,
        null,
        'C3 calendar: caller failure propagates through the transaction',
    );
    $assert($extensionSnapshot === $snapshot(), 'C3 calendar: caller rollback restores dates, revision, history and idempotency together');

    // A history write failure must not leave a changed calendar or saved dedup result.
    $trigger = 'mf_c3_calendar_history_failure';
    $connection->queryExecute("CREATE TRIGGER {$trigger} BEFORE INSERT ON b_hlbd_mf_organization_change FOR EACH ROW BEGIN IF NEW.UF_AGGREGATE_TYPE='group' AND NEW.UF_AGGREGATE_ID={$group} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='C3 calendar history failure'; END IF; END");
    try {
        $expect(
            static fn() => $commands->extend(new CalendarCommandInputDto($id, $actor, $extended->revision, md5('native-calendar-history-failure'), 'История должна быть атомарной'), $newClose->add(new DateInterval('P1D')), $bearer),
            RuntimeException::class,
            null,
            'C3 calendar: an injected native history failure aborts the operation',
        );
        $assert($extensionSnapshot === $snapshot(), 'C3 calendar: history failure rolls back every calendar write');
    } finally {
        $connection->queryExecute("DROP TRIGGER {$trigger}");
    }

    // Equal native IDs and revisions in different aggregates must coexist in the typed journal.
    $connection->startTransaction();
    try {
        $existingInstitution = $connection->query("SELECT ID FROM b_hlbd_mf_organization_change WHERE UF_AGGREGATE_TYPE='institution' AND UF_AGGREGATE_ID={$group} AND UF_TO_REVISION={$extended->revision}")->fetch();
        if (false === $existingInstitution) {
            $from = $extended->revision - 1;
            $probe = Uuid::uuid4()->toString();
            $connection->queryExecute("INSERT INTO b_hlbd_mf_organization_change(UF_AGGREGATE_TYPE,UF_AGGREGATE_ID,UF_FROM_REVISION,UF_TO_REVISION,UF_ACTOR_ID,UF_OPERATION_ID,UF_DELTA,UF_OCCURRED_AT) VALUES('institution',{$group},{$from},{$extended->revision},{$actor},'{$probe}','{}',UTC_TIMESTAMP())");
        }
        $types = $connection->query("SELECT COUNT(DISTINCT UF_AGGREGATE_TYPE) AS N FROM b_hlbd_mf_organization_change WHERE UF_AGGREGATE_ID={$group} AND UF_TO_REVISION={$extended->revision}")->fetch();
        $assert(2 <= (int)$types['N'], 'C3 calendar: equal native IDs and revision numbers in institution/group do not collide');
    } finally {
        $connection->rollbackTransaction();
    }

    // Frozen clocks are test dependencies only; each provider still persists to the native database.
    $buildFrozen = static function(string $instant) use ($locator, $access, $transaction): array {
        $clock = new class(new DateTimeImmutable($instant)) implements CalendarClockInterface {
            public function __construct(private readonly DateTimeImmutable $instant) {}

            public function now(): DateTimeImmutable
            {
                return $this->instant;
            }
        };
        $provider = new GroupCalendar(
            $locator->get(GroupCalendarRepository::class),
            $locator->get(InstitutionOperationRepository::class),
            $clock,
            $locator->get(CalendarCommandValidator::class),
        );

        return [$provider, new ChangeGroupCalendarUseCase($provider, $access, $transaction, $locator->get(CalendarCommandValidator::class))];
    };
    $frozenGroup = $context['createGroup']();
    $frozenRow = $connection->query("SELECT UF_PUBLIC_ID,UF_REVISION FROM b_hlbd_mf_group WHERE ID={$frozenGroup}")->fetch();
    $frozenId = (string)$frozenRow['UF_PUBLIC_ID'];
    [$januaryProvider, $januaryCommands] = $buildFrozen('2028-01-31T23:30:00+03:00');
    $januaryInput = new CalendarCommandInputDto($frozenId, $actor, (int)$frozenRow['UF_REVISION'], md5('native-calendar-january'), 'Передача на границе месяца');
    $january = $januaryCommands->confirmLinkSent($januaryInput, $bearer);
    $assert('2028-02-07T23:30:00+03:00' === $january->calendar->closesAt && '2028-02-14T23:30:00+03:00' === $january->calendar->deliveryDueAt, 'C3 calendar: native dates cross the month boundary in Moscow time');
    [$beforeBoundary] = $buildFrozen('2028-02-07T23:29:59+03:00');
    [$atBoundary, $atBoundaryCommands] = $buildFrozen('2028-02-07T23:30:00+03:00');
    $assert('open' === $beforeBoundary->get($frozenId)->calendar->status && 'closed' === $atBoundary->get($frozenId)->calendar->status, 'C3 calendar: native projection closes exactly at the deadline');
    [$laterProvider, $laterCommands] = $buildFrozen('2028-02-20T12:00:00+03:00');
    $assert($january == $laterCommands->confirmLinkSent($januaryInput, $bearer), 'C3 calendar: a fresh provider and later clock replay the original persisted result');
    $lateResend = $laterCommands->confirmLinkSent(new CalendarCommandInputDto($frozenId, $actor, $january->revision, md5('native-calendar-late-resend'), 'Передача после закрытия'), $bearer);
    $assert($january->revision === $lateResend->revision && $january->calendar->closesAt === $lateResend->calendar->closesAt && 'closed' === $lateResend->calendar->status, 'C3 calendar: late retransmission keeps the group closed and does not slide deadlines');
    $expect(
        static fn() => $laterCommands->extend(new CalendarCommandInputDto($frozenId, $actor, $january->revision, md5('native-calendar-equal-now'), 'Новое закрытие совпало с now'), new DateTimeImmutable('2028-02-20T12:00:00+03:00'), $bearer),
        DomainException::class,
        null,
        'C3 calendar: a new deadline equal to server now is rejected',
    );
    $expect(
        static fn() => $laterCommands->extend(new CalendarCommandInputDto($frozenId, $actor, $january->revision, md5('native-calendar-before-now'), 'Новое закрытие уже в прошлом'), new DateTimeImmutable('2028-02-19T12:00:00+03:00'), $bearer),
        DomainException::class,
        null,
        'C3 calendar: a deadline later than the old close but before server now is rejected',
    );
    $teacherInput = new CalendarCommandInputDto($id, $context['staff']['teacher'], $extended->revision, md5('native-calendar-forbidden'), 'Нет полномочий');
    $expect(
        static fn() => $commands->confirmLinkSent($teacherInput, $bearer),
        HttpException::class,
        403,
        'C3 calendar: the internal C3 entry point requires organizer authorization',
    );
};
