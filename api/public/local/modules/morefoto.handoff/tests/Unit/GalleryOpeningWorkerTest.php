<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Morefoto\Handoff\Application\Link\Dto\LinkAssessmentOutputDto;
use Morefoto\Handoff\Application\Link\Service\GroupLinkReadiness;
use Morefoto\Handoff\Application\Link\UseCase\RunGalleryOpeningsUseCase;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Enum\OpeningStatusEnum;
use Morefoto\Handoff\Domain\Link\Enum\LinkProblemEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Repository\OpeningScheduleRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkReadinessPolicy;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkReadiness;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkState;
use Morefoto\Handoff\Domain\Link\ValueObject\OpeningSchedule;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Media\Dto\GalleryLinkOutputDto;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Contracts\Organization\Dto\CalendarMutationOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\LinkSentInputDto;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class GalleryOpeningWorkerTest extends TestCase
{
    private const string GROUP = '22345678-abcd-4abc-8abc-123456789abc';

    public function testLateWorkerUsesActualOpeningMomentAndDoesNotOpenTwice(): void
    {
        $scheduled = new \DateTimeImmutable('2026-11-04T12:00:00+03:00');
        $actual = new \DateTimeImmutable('2026-11-04T12:03:00.123456+03:00');
        $recorded = new \DateTimeImmutable('2026-11-04T12:03:00+03:00');
        $period = new GroupCalendarOutputDto('Europe/Moscow', null, null, null, null, 'preparing');
        $group = new GroupDirectoryItemOutputDto(10, self::GROUP, 'Группа', 'regular', 3, self::GROUP, 'Сад', 4, self::GROUP, 'Съёмка', $period);
        $signature = str_repeat('a', 64);
        $openings = $this->createMock(OpeningScheduleRepositoryInterface::class);
        $openings->method('due')->willReturnOnConsecutiveCalls([self::GROUP], []);
        $openings->method('find')->willReturn(new OpeningSchedule(10, $scheduled, OpeningStatusEnum::SCHEDULED, 7, null, null, null));
        $openings->expects(self::once())->method('opened')->with(10, $recorded);
        $openings->expects(self::never())->method('block');

        $calendar = $this->createMock(GroupCalendarInterface::class);
        $calendar->method('lock')->willReturn(3);
        $calendar->expects(self::once())->method('recordLinkSent')->with(self::callback(static function(LinkSentInputDto $input) use ($recorded): bool {
            return self::GROUP === $input->groupId && 7 === $input->actorUserId && $recorded == $input->sentAt;
        }))->willReturn(new CalendarMutationOutputDto(self::GROUP, 2, $period));
        $directory = $this->createStub(GroupDirectoryInterface::class);
        $directory->method('find')->willReturn($group);
        $links = $this->createMock(GroupLinkRepositoryInterface::class);
        $links->method('lock')->willReturn(new LinkState(2, $signature));
        $links->expects(self::once())->method('advance')->with(10, 2)->willReturn(3);
        $readiness = $this->createStub(GroupLinkReadiness::class);
        $readiness->method('assessLocked')->willReturn(new LinkAssessmentOutputDto(new LinkReadiness($signature, []), 1, 1));
        $galleryLinks = $this->createStub(GalleryLinkInterface::class);
        $galleryLinks->method('current')->willReturn(new GalleryLinkOutputDto(str_repeat('b', 64), $scheduled));
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($actual);
        $access = $this->createMock(GroupLinkAccessInterface::class);
        $access->expects(self::once())->method('lockState');
        $transaction = new class implements HandoffTransactionInterface {
            public function execute(callable $operation): mixed
            {
                return $operation();
            }
        };
        $worker = new RunGalleryOpeningsUseCase($transaction, $openings, $access, $calendar, $directory, $links, $readiness, new LinkReadinessPolicy(), $galleryLinks, $clock);

        self::assertSame(1, $worker->execute(100)->opened);
        self::assertSame(0, $worker->execute(100)->opened);
    }

    public function testLostReadinessBlocksOpeningWithoutStartingSales(): void
    {
        $scheduled = new \DateTimeImmutable('2026-11-04T12:00:00+03:00');
        $actual = new \DateTimeImmutable('2026-11-04T12:03:00+03:00');
        $period = new GroupCalendarOutputDto('Europe/Moscow', null, null, null, null, 'preparing');
        $group = new GroupDirectoryItemOutputDto(10, self::GROUP, 'Группа', 'regular', 3, self::GROUP, 'Сад', 4, self::GROUP, 'Съёмка', $period);
        $signature = str_repeat('a', 64);
        $openings = $this->createMock(OpeningScheduleRepositoryInterface::class);
        $openings->method('due')->willReturn([self::GROUP]);
        $openings->method('find')->willReturn(new OpeningSchedule(10, $scheduled, OpeningStatusEnum::SCHEDULED, 7, null, null, null));
        $openings->expects(self::once())->method('block')->with(10);
        $openings->expects(self::never())->method('opened');
        $calendar = $this->createMock(GroupCalendarInterface::class);
        $calendar->method('lock')->willReturn(3);
        $calendar->expects(self::never())->method('recordLinkSent');
        $directory = $this->createStub(GroupDirectoryInterface::class);
        $directory->method('find')->willReturn($group);
        $links = $this->createMock(GroupLinkRepositoryInterface::class);
        $links->method('lock')->willReturn(new LinkState(2, $signature));
        $links->expects(self::never())->method('advance');
        $readiness = $this->createStub(GroupLinkReadiness::class);
        $readiness->method('assessLocked')->willReturn(new LinkAssessmentOutputDto(new LinkReadiness($signature, [LinkProblemEnum::UNASSIGNED_PHOTOS]), 1, 1));
        $galleryLinks = $this->createStub(GalleryLinkInterface::class);
        $galleryLinks->method('current')->willReturn(new GalleryLinkOutputDto(str_repeat('b', 64), $scheduled));
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($actual);
        $access = $this->createStub(GroupLinkAccessInterface::class);
        $transaction = new class implements HandoffTransactionInterface {
            public function execute(callable $operation): mixed
            {
                return $operation();
            }
        };
        $worker = new RunGalleryOpeningsUseCase($transaction, $openings, $access, $calendar, $directory, $links, $readiness, new LinkReadinessPolicy(), $galleryLinks, $clock);

        $result = $worker->execute(100);
        self::assertSame(0, $result->opened);
        self::assertSame(1, $result->blocked);
    }
}
