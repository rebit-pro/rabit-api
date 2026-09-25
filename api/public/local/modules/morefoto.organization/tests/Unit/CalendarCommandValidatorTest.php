<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Application\Calendar\Service\CalendarCommandValidator;
use Morefoto\Organization\Application\Calendar\Service\GroupCalendar;
use Morefoto\Organization\Application\Calendar\UseCase\ChangeGroupCalendarUseCase;
use Morefoto\Organization\Application\Institution\Contract\InstitutionTransactionInterface;
use Morefoto\Organization\Domain\Calendar\Repository\GroupCalendarRepository;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class CalendarCommandValidatorTest extends TestCase
{
    private const string GROUP = '12345678-abcd-4abc-8abc-123456789abc';
    private const string KEY = '0123456789abcdef0123456789abcdef';

    public function testAcceptsThePersisted32LowerHexKeyContract(): void
    {
        (new CalendarCommandValidator())->validate($this->command());
        (new CalendarCommandValidator())->validate($this->command(revision: 2147483646, reason: str_repeat('я', 1000)));
        $this->addToAssertionCount(1);
    }

    #[DataProvider('invalidCommandKeys')]
    public function testRejectsInvalidJournalKeys(string $key): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalendarCommandValidator())->validate($this->command(key: $key));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCommandKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'human readable label' => ['native-calendar-confirm'];
        yield '31 characters' => [str_repeat('a', 31)];
        yield '33 characters' => [str_repeat('a', 33)];
        yield 'uppercase hex' => [str_repeat('A', 32)];
        yield 'non hex' => [str_repeat('g', 32)];
        yield 'space' => [str_repeat('a', 31) . ' '];
        yield 'trailing newline' => [str_repeat('a', 32) . "\n"];
    }

    #[DataProvider('nonCanonicalCommandIds')]
    public function testRejectsNonCanonicalGroupIds(string $id): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalendarCommandValidator())->validate($this->command(groupId: $id));
    }

    /** @return iterable<string, array{string}> */
    public static function nonCanonicalCommandIds(): iterable
    {
        yield 'uppercase' => ['12345678-ABCD-4ABC-8ABC-123456789ABC'];
        yield 'compact' => ['12345678abcd4abc8abc123456789abc'];
        yield 'leading whitespace' => [' 12345678-abcd-4abc-8abc-123456789abc'];
        yield 'non uuid' => ['group-1'];
    }

    #[DataProvider('invalidActorsRevisionsAndReasons')]
    public function testRejectsInvalidActorRevisionAndReason(int $actor, int $revision, string $reason): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalendarCommandValidator())->validate($this->command(actor: $actor, revision: $revision, reason: $reason));
    }

    /** @return iterable<string, array{int, int, string}> */
    public static function invalidActorsRevisionsAndReasons(): iterable
    {
        yield 'zero actor' => [0, 1, 'Подтверждение'];
        yield 'zero revision' => [1, 0, 'Подтверждение'];
        yield 'revision without increment room' => [1, 2147483647, 'Подтверждение'];
        yield 'blank reason' => [1, 1, " \t "];
        yield 'invalid UTF-8 reason' => [1, 1, "\xC3\x28"];
        yield 'NUL in reason' => [1, 1, "При\0чина"];
        yield 'reason longer than 1000' => [1, 1, str_repeat('я', 1001)];
    }

    public function testUseCaseRejectsInvalidCommandBeforeTransactionAndAccess(): void
    {
        $calendar = $this->createMock(GroupCalendarInterface::class);
        $calendar->expects(self::never())->method(self::anything());
        $access = $this->createMock(InstitutionAccessInterface::class);
        $access->expects(self::never())->method(self::anything());
        $transaction = $this->createMock(InstitutionTransactionInterface::class);
        $transaction->expects(self::never())->method('execute');

        $this->expectException(\InvalidArgumentException::class);
        (new ChangeGroupCalendarUseCase($calendar, $access, $transaction, new CalendarCommandValidator()))
            ->extend($this->command(key: 'native-calendar-extend'), new \DateTimeImmutable('2026-10-01T12:00:00+03:00'), 'bearer')
        ;
    }

    public function testCalendarRejectsInvalidCommandBeforeRepositories(): void
    {
        $calendars = $this->createMock(GroupCalendarRepository::class);
        $calendars->expects(self::never())->method(self::anything());
        $operations = $this->createMock(InstitutionOperationRepository::class);
        $operations->expects(self::never())->method(self::anything());

        $this->expectException(\InvalidArgumentException::class);
        (new GroupCalendar($calendars, $operations, $this->createStub(CalendarClockInterface::class), new CalendarCommandValidator()))
            ->confirmLinkSent($this->command(groupId: 'group-1'))
        ;
    }

    private function command(string $groupId = self::GROUP, int $actor = 1, int $revision = 1, string $key = self::KEY, string $reason = 'Подтверждение'): CalendarCommandInputDto
    {
        return new CalendarCommandInputDto($groupId, $actor, $revision, $key, $reason);
    }
}
