<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Rebit\Share\Contracts\Access\Dto\InstitutionAssignmentOutputDto;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Morefoto\Handoff\Application\Link\Dto\GroupLinkListInputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkCorrectionInputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkPreparationInputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkTransmissionInputDto;
use Morefoto\Handoff\Application\Link\Dto\MarkupCorrectionFinishInputDto;
use Morefoto\Handoff\Application\Link\Dto\MarkupCorrectionStartInputDto;
use Morefoto\Handoff\Application\Link\Mapper\GroupLinkOutputMapper;
use Morefoto\Handoff\Application\Link\Service\GroupLinkCommandSession;
use Morefoto\Handoff\Application\Link\Service\GroupLinkReadiness;
use Morefoto\Handoff\Application\Link\UseCase\CorrectGroupLinkDateUseCase;
use Morefoto\Handoff\Application\Link\UseCase\FinishMarkupCorrectionUseCase;
use Morefoto\Handoff\Application\Link\UseCase\GetGroupLinkUseCase;
use Morefoto\Handoff\Application\Link\UseCase\ListGroupLinksUseCase;
use Morefoto\Handoff\Application\Link\UseCase\PrepareGroupLinkUseCase;
use Morefoto\Handoff\Application\Link\UseCase\StartMarkupCorrectionUseCase;
use Morefoto\Handoff\Application\Link\UseCase\TransmitGroupLinkUseCase;
use Morefoto\Handoff\Application\Request\Contract\HandoffTransactionInterface;
use Morefoto\Handoff\Domain\Link\Enum\LinkEventKindEnum;
use Morefoto\Handoff\Domain\Link\Repository\GroupLinkRepositoryInterface;
use Morefoto\Handoff\Domain\Link\Service\LinkDeliveryPolicy;
use Morefoto\Handoff\Domain\Link\Service\LinkPermissionPolicy;
use Morefoto\Handoff\Domain\Link\Service\LinkReadinessPolicy;
use Morefoto\Handoff\Domain\Link\Service\MarkupCorrectionAvailability;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkHistoryEntry;
use Morefoto\Handoff\Domain\Link\ValueObject\LinkState;
use Morefoto\Handoff\Domain\Request\ValueObject\IdempotencyKey;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Access\Dto\GroupAssignmentOutputDto;
use Rebit\Share\Contracts\Access\Dto\LinkActorOutputDto;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Access\GroupLinkAccessInterface;
use Rebit\Share\Contracts\Commerce\Dto\SalesReadinessOutputDto;
use Rebit\Share\Contracts\Commerce\GroupOrdersInterface;
use Rebit\Share\Contracts\Commerce\GroupSalesReadinessInterface;
use Rebit\Share\Contracts\Media\Dto\GalleryLinkOutputDto;
use Rebit\Share\Contracts\Media\Dto\GroupMaterialsOutputDto;
use Rebit\Share\Contracts\Media\GalleryLinkInterface;
use Rebit\Share\Contracts\Media\GroupMaterialsInterface;
use Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto;
use Rebit\Share\Contracts\Organization\Dto\CalendarMutationOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryPageOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectorySummaryOutputDto;
use Rebit\Share\Contracts\Organization\Dto\LinkSentInputDto;
use Rebit\Share\Contracts\Organization\Dto\MarkupCorrectionInputDto;
use Rebit\Share\Contracts\Organization\Dto\MarkupCorrectionOutputDto;
use Rebit\Share\Contracts\Organization\GroupCalendarInterface;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Organization\GroupMarkupCorrectionInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 *
 * In-memory ports keep the whole command order under test: access, lock, idempotency, readiness and history
 */
final class GroupLinkWorkflowTest extends TestCase
{
    private const string GROUP = '22345678-abcd-4abc-8abc-123456789abc';
    private const int ORGANIZER = 1;
    private const int CURATOR = 2;
    private const int TEACHER = 3;
    private const int HEAD = 4;
    private const int FOREIGN_CURATOR = 5;

    private GroupLinkRepositoryInterface&\stdClass $links;
    private GroupDirectoryInterface&\stdClass $directory;
    private GroupCalendarInterface&\stdClass $calendar;
    private GroupMaterialsInterface&\stdClass $materials;
    private GalleryLinkInterface&\stdClass $gallery;
    private GetGroupLinkUseCase $read;
    private PrepareGroupLinkUseCase $prepare;
    private TransmitGroupLinkUseCase $transmit;
    private CorrectGroupLinkDateUseCase $correct;
    private ListGroupLinksUseCase $list;
    private StartMarkupCorrectionUseCase $startMarkup;
    private FinishMarkupCorrectionUseCase $finishMarkup;
    private GroupOrdersInterface&\stdClass $orders;

    protected function setUp(): void
    {
        $this->links = $this->links();
        $this->directory = $this->directory();
        $this->calendar = $this->calendar($this->directory);
        $this->materials = $this->materials();
        $this->gallery = $this->gallery();
        $access = $this->access();
        $sales = $this->createStub(GroupSalesReadinessInterface::class);
        $sales->method('readiness')->willReturn([10 => new SalesReadinessOutputDto(3, str_repeat('c', 64))]);
        $teachers = $this->createStub(GroupAccessInterface::class);
        $teachers->method('assignments')->willReturn([10 => new GroupAssignmentOutputDto(self::TEACHER)]);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-22T08:00:00Z'));
        $transaction = new class implements HandoffTransactionInterface {
            public function execute(callable $operation): mixed
            {
                return $operation();
            }
        };
        $readiness = new GroupLinkReadiness($this->materials, $sales, $teachers, $this->links, new LinkReadinessPolicy());
        $session = new GroupLinkCommandSession($access, $this->calendar, $this->directory, $this->links, $readiness, new LinkPermissionPolicy());
        $mapper = new GroupLinkOutputMapper();
        $this->orders = $this->orders();
        $corrections = $this->corrections($this->directory);
        $this->read = new GetGroupLinkUseCase($access, $this->directory, $readiness, $this->links, $this->gallery, new LinkPermissionPolicy(), new LinkReadinessPolicy(), $mapper, $clock, $this->orders, new MarkupCorrectionAvailability());
        $this->startMarkup = new StartMarkupCorrectionUseCase($transaction, $session, $this->links, $this->orders, $corrections, $mapper);
        $this->finishMarkup = new FinishMarkupCorrectionUseCase($transaction, $session, $this->links, $corrections, $mapper);
        $this->prepare = new PrepareGroupLinkUseCase($transaction, $session, $this->links, $this->gallery, $mapper);
        $this->transmit = new TransmitGroupLinkUseCase($transaction, $session, $this->links, $this->gallery, $this->calendar, new LinkReadinessPolicy(), new LinkDeliveryPolicy(), $mapper);
        $institutions = $this->createStub(InstitutionAccessInterface::class);
        $institutions->method('assignments')->willReturn([3 => new InstitutionAssignmentOutputDto(curatorId: self::CURATOR, headId: self::HEAD, curatorName: 'Мария Иванова')]);
        $this->list = new ListGroupLinksUseCase($access, $this->directory, $readiness, $this->links, new LinkPermissionPolicy(), new LinkReadinessPolicy(), $mapper, $institutions);
        $this->correct = new CorrectGroupLinkDateUseCase($transaction, $session, $this->links, $this->gallery, $this->calendar, new LinkDeliveryPolicy(), $mapper);
    }

    public function testOrganizerPreparesThenTeacherTransmitsAndRepeatKeepsDeadlines(): void
    {
        $before = $this->read->execute(self::ORGANIZER, self::GROUP);
        self::assertSame([1, false, [], null], [$before->revision, $before->prepared, $before->problems, $before->galleryToken]);

        $prepared = $this->prepare->execute(self::ORGANIZER, self::GROUP, $this->key('a'), new LinkPreparationInputDto(1, $before->signature));
        self::assertSame([2, $before->signature, true], [$prepared->revision, $prepared->signature, $prepared->prepared]);
        $card = $this->read->execute(self::HEAD, self::GROUP);
        self::assertSame(str_repeat('f', 64), $card->galleryToken);
        self::assertTrue($card->prepared);

        $sent = $this->transmit->execute(self::TEACHER, self::GROUP, $this->key('b'), new LinkTransmissionInputDto(2, $card->signature, new \DateTimeImmutable('2026-09-22T10:15:00+03:00')));
        self::assertSame(
            [3, '2026-09-22T10:15:00+03:00', '2026-09-29T10:15:00+03:00', '2026-10-06T10:15:00+03:00', '2026-10-06T10:15:00+03:00'],
            [$sent->revision, $sent->sentAt, $sent->closesAt, $sent->digitalSalesUntil, $sent->deliveryAt],
        );
        self::assertCount(1, $this->calendar->delivered);
        self::assertSame(self::TEACHER, $this->calendar->delivered[0]->actorUserId);

        $stale = $this->transmit->execute(self::CURATOR, self::GROUP, $this->key('c'), new LinkTransmissionInputDto(1, str_repeat('0', 64), new \DateTimeImmutable('2026-09-22T10:59:00+03:00')));
        self::assertSame([3, '2026-09-22T10:15:00+03:00', '2026-09-29T10:15:00+03:00'], [$stale->revision, $stale->sentAt, $stale->closesAt]);
        self::assertCount(1, $this->calendar->delivered);
        self::assertSame([LinkEventKindEnum::PREPARED, LinkEventKindEnum::TRANSMITTED], array_map(static fn(LinkHistoryEntry $entry): LinkEventKindEnum => $entry->kind, $this->links->history));
        $this->assertCode('LINK_ALREADY_SENT', 409, fn(): object => $this->prepare->execute(self::ORGANIZER, self::GROUP, $this->key('d'), new LinkPreparationInputDto(3, $card->signature)));
    }

    public function testListCountsTheWholeScopeOfTheActor(): void
    {
        $before = $this->read->execute(self::ORGANIZER, self::GROUP);
        $this->prepare->execute(self::ORGANIZER, self::GROUP, $this->key('a'), new LinkPreparationInputDto(1, $before->signature));
        $seen = count($this->directory->queries);

        $page = $this->list->execute(self::CURATOR, new GroupLinkListInputDto(null, null, 'open', 1, 25));

        self::assertSame(
            ['2026-09-22T08:00:00+00:00', 1, 0, 0, 0, 0, 1],
            [$page->summary->referenceNow, $page->summary->preparing, $page->summary->open, $page->summary->digital, $page->summary->closed, $page->summary->closingSoon, $page->summary->prepared],
        );
        // Counters keep the curator's institutions and drop the state filter; prepared counts only preparing groups.
        [$counters, $preparing] = array_slice($this->directory->queries, $seen);
        self::assertSame([[3], null, null], [$counters->institutionIds, $counters->groupIds, $counters->state]);
        self::assertSame([[3], null, 'preparing'], [$preparing->institutionIds, $preparing->groupIds, $preparing->state]);

        $seen = count($this->directory->queries);
        $teacherPage = $this->list->execute(self::TEACHER, new GroupLinkListInputDto(null, null, null, 1, 25));
        $teacher = array_slice($this->directory->queries, $seen)[0];
        self::assertSame([null, [10]], [$teacher->institutionIds, $teacher->groupIds]);
        // INF-11: the teacher sees whom to ask — the curator of the group's institution.
        self::assertSame(['Мария Иванова'], array_map(static fn($item): ?string => $item->curatorName, $teacherPage->items));
    }

    public function testSameKeyReplaysOnlyTheSameBody(): void
    {
        $signature = $this->read->execute(self::ORGANIZER, self::GROUP)->signature;
        $first = $this->prepare->execute(self::ORGANIZER, self::GROUP, $this->key('a'), new LinkPreparationInputDto(1, $signature));
        $replay = $this->prepare->execute(self::ORGANIZER, self::GROUP, $this->key('a'), new LinkPreparationInputDto(1, $signature));
        self::assertEquals($first, $replay);
        self::assertCount(1, $this->links->history);
        $this->assertCode('IDEMPOTENCY_CONFLICT', 409, fn(): object => $this->prepare->execute(self::ORGANIZER, self::GROUP, $this->key('a'), new LinkPreparationInputDto(2, $signature)));
    }

    public function testPreparationRejectsProblemsStaleFormsAndOtherRoles(): void
    {
        $signature = $this->read->execute(self::ORGANIZER, self::GROUP)->signature;
        $this->assertCode('REVISION_CONFLICT', 409, fn(): object => $this->prepare->execute(self::ORGANIZER, self::GROUP, $this->key('a'), new LinkPreparationInputDto(2, $signature)));
        $this->assertCode('SIGNATURE_CONFLICT', 409, fn(): object => $this->prepare->execute(self::ORGANIZER, self::GROUP, $this->key('b'), new LinkPreparationInputDto(1, str_repeat('0', 64))));
        $this->assertCode('FORBIDDEN', 403, fn(): object => $this->prepare->execute(self::CURATOR, self::GROUP, $this->key('c'), new LinkPreparationInputDto(1, $signature)));
        $this->assertCode('GROUP_NOT_FOUND', 404, fn(): object => $this->prepare->execute(self::FOREIGN_CURATOR, self::GROUP, $this->key('d'), new LinkPreparationInputDto(1, $signature)));
        $this->materials->snapshot = new GroupMaterialsOutputDto(12, 1, 0, 4, str_repeat('b', 64));
        $blocked = $this->read->execute(self::ORGANIZER, self::GROUP);
        self::assertSame(['photosProcessing'], $blocked->problems);
        $this->assertCode('LINK_NOT_READY', 409, fn(): object => $this->prepare->execute(self::ORGANIZER, self::GROUP, $this->key('e'), new LinkPreparationInputDto(1, $blocked->signature)));
        self::assertNull($this->gallery->link);
        self::assertSame([], $this->links->history);
    }

    public function testChangedMaterialsInvalidatePreparationAndDeliveryCannotPrecedeTheLink(): void
    {
        $signature = $this->read->execute(self::ORGANIZER, self::GROUP)->signature;
        $this->prepare->execute(self::ORGANIZER, self::GROUP, $this->key('a'), new LinkPreparationInputDto(1, $signature));
        $this->assertCode('SENT_AT_BEFORE_LINK', 422, fn(): object => $this->transmit->execute(self::ORGANIZER, self::GROUP, $this->key('b'), new LinkTransmissionInputDto(2, $signature, new \DateTimeImmutable('2026-09-22T09:59:00+03:00'))));
        $this->assertCode('FORBIDDEN', 403, fn(): object => $this->transmit->execute(self::HEAD, self::GROUP, $this->key('c'), new LinkTransmissionInputDto(2, $signature, new \DateTimeImmutable('2026-09-22T10:15:00+03:00'))));

        $this->materials->snapshot = new GroupMaterialsOutputDto(13, 0, 0, 4, str_repeat('b', 64));
        $changed = $this->read->execute(self::ORGANIZER, self::GROUP);
        self::assertFalse($changed->prepared);
        self::assertNotSame($signature, $changed->signature);
        $this->assertCode('SIGNATURE_CONFLICT', 409, fn(): object => $this->transmit->execute(self::ORGANIZER, self::GROUP, $this->key('d'), new LinkTransmissionInputDto(2, $signature, new \DateTimeImmutable('2026-09-22T10:15:00+03:00'))));
        $this->assertCode('LINK_NOT_PREPARED', 409, fn(): object => $this->transmit->execute(self::ORGANIZER, self::GROUP, $this->key('e'), new LinkTransmissionInputDto(2, $changed->signature, new \DateTimeImmutable('2026-09-22T10:15:00+03:00'))));
        self::assertSame([], $this->calendar->delivered);
    }

    public function testCuratorCorrectsTheRecordedDateWithHistory(): void
    {
        $this->assertCode('LINK_NOT_SENT', 409, fn(): object => $this->correct->execute(self::CURATOR, self::GROUP, $this->key('a'), new LinkCorrectionInputDto(1, $this->read->execute(self::CURATOR, self::GROUP)->signature, new \DateTimeImmutable('2026-09-22T10:00:00+03:00'), 'Ошибка в дате')));
        $signature = $this->read->execute(self::ORGANIZER, self::GROUP)->signature;
        $this->prepare->execute(self::ORGANIZER, self::GROUP, $this->key('b'), new LinkPreparationInputDto(1, $signature));
        $this->transmit->execute(self::ORGANIZER, self::GROUP, $this->key('c'), new LinkTransmissionInputDto(2, $signature, new \DateTimeImmutable('2026-09-22T10:45:00+03:00')));
        $this->assertCode('FORBIDDEN', 403, fn(): object => $this->correct->execute(self::TEACHER, self::GROUP, $this->key('d'), new LinkCorrectionInputDto(3, $signature, new \DateTimeImmutable('2026-09-22T10:15:00+03:00'), 'Ошибка в дате')));

        $corrected = $this->correct->execute(self::CURATOR, self::GROUP, $this->key('e'), new LinkCorrectionInputDto(3, $signature, new \DateTimeImmutable('2026-09-22T10:15:00+03:00'), 'Ошибка в дате'));
        self::assertSame([4, '2026-09-22T10:15:00+03:00', '2026-09-29T10:15:00+03:00'], [$corrected->revision, $corrected->sentAt, $corrected->closesAt]);
        $entry = $this->links->history[2];
        self::assertSame(
            [LinkEventKindEnum::CORRECTED, 'Ошибка в дате', '2026-09-22T10:45:00+03:00', '2026-09-29T10:45:00+03:00', 'curator'],
            [$entry->kind, $entry->reason, $entry->previousSentAt, $entry->previousClosesAt, $entry->actorName],
        );
        self::assertSame('Ошибка в дате', $this->calendar->delivered[1]->reason);
    }

    public function testCuratorPausesSalesForAMarkupCorrectionAndFinishesItWithoutTouchingTheDeadlines(): void
    {
        $this->transmitted();
        $card = $this->read->execute(self::CURATOR, self::GROUP);
        self::assertSame([true, null], [$card->markupCorrectable, $card->markupCorrectionAt]);

        $started = $this->startMarkup->execute(self::CURATOR, self::GROUP, $this->key('1'), new MarkupCorrectionStartInputDto($card->revision));
        self::assertSame([4, '2026-09-22T11:00:00+03:00'], [$started->revision, $started->startedAt]);
        $replay = $this->startMarkup->execute(self::CURATOR, self::GROUP, $this->key('1'), new MarkupCorrectionStartInputDto($card->revision));
        self::assertSame([4, '2026-09-22T11:00:00+03:00'], [$replay->revision, $replay->startedAt], 'The same key replays the stored result.');
        $paused = $this->read->execute(self::ORGANIZER, self::GROUP);
        self::assertSame([false, '2026-09-22T11:00:00+03:00'], [$paused->markupCorrectable, $paused->markupCorrectionAt]);
        $this->assertCode('CORRECTION_IN_PROGRESS', 409, fn(): object => $this->startMarkup->execute(self::ORGANIZER, self::GROUP, $this->key('2'), new MarkupCorrectionStartInputDto(4)));

        $this->materials->snapshot = new GroupMaterialsOutputDto(12, 0, 0, 4, str_repeat('b', 64));
        $corrected = $this->read->execute(self::CURATOR, self::GROUP)->signature;
        $this->assertCode('SIGNATURE_CONFLICT', 409, fn(): object => $this->finishMarkup->execute(self::CURATOR, self::GROUP, $this->key('3'), new MarkupCorrectionFinishInputDto(4, $card->signature)));
        $finished = $this->finishMarkup->execute(self::CURATOR, self::GROUP, $this->key('4'), new MarkupCorrectionFinishInputDto(4, $corrected));
        self::assertSame([5, null], [$finished->revision, $finished->startedAt]);
        self::assertNull($this->read->execute(self::CURATOR, self::GROUP)->markupCorrectionAt);
        self::assertSame('2026-09-29T10:15:00+03:00', $this->directory->calendar->closesAt, 'T0 and the deadlines stay as transmitted.');
        self::assertCount(1, $this->calendar->delivered);
        $events = array_slice($this->links->history, 2);
        self::assertSame(
            [[LinkEventKindEnum::MARKUP_STARTED, null, 'curator'], [LinkEventKindEnum::MARKUP_FINISHED, $corrected, 'curator']],
            array_map(static fn(LinkHistoryEntry $entry): array => [$entry->kind, $entry->signature, $entry->actorName], $events),
        );
    }

    public function testMarkupCorrectionIsRefusedWithOrdersBeforeTransmissionForOtherRolesAndUnreadyMaterials(): void
    {
        $this->assertCode('LINK_NOT_SENT', 409, fn(): object => $this->startMarkup->execute(self::ORGANIZER, self::GROUP, $this->key('a'), new MarkupCorrectionStartInputDto(1)));
        self::assertFalse($this->read->execute(self::ORGANIZER, self::GROUP)->markupCorrectable);
        $this->transmitted();
        $this->assertCode('CORRECTION_NOT_STARTED', 409, fn(): object => $this->finishMarkup->execute(self::ORGANIZER, self::GROUP, $this->key('b'), new MarkupCorrectionFinishInputDto(3, str_repeat('a', 64))));
        $this->assertCode('FORBIDDEN', 403, fn(): object => $this->startMarkup->execute(self::TEACHER, self::GROUP, $this->key('c'), new MarkupCorrectionStartInputDto(3)));
        $this->assertCode('FORBIDDEN', 403, fn(): object => $this->startMarkup->execute(self::HEAD, self::GROUP, $this->key('d'), new MarkupCorrectionStartInputDto(3)));
        $this->assertCode('GROUP_NOT_FOUND', 404, fn(): object => $this->startMarkup->execute(self::FOREIGN_CURATOR, self::GROUP, $this->key('e'), new MarkupCorrectionStartInputDto(3)));
        $this->assertCode('REVISION_CONFLICT', 409, fn(): object => $this->startMarkup->execute(self::ORGANIZER, self::GROUP, $this->key('f'), new MarkupCorrectionStartInputDto(2)));

        // Any order, even an unpaid one, closes the correction.
        $this->orders->exists = true;
        self::assertFalse($this->read->execute(self::ORGANIZER, self::GROUP)->markupCorrectable);
        $this->assertCode('GROUP_HAS_ORDERS', 409, fn(): object => $this->startMarkup->execute(self::ORGANIZER, self::GROUP, $this->key('5'), new MarkupCorrectionStartInputDto(3)));
        self::assertNull($this->directory->markupCorrectionAt);

        $this->orders->exists = false;
        $this->startMarkup->execute(self::ORGANIZER, self::GROUP, $this->key('6'), new MarkupCorrectionStartInputDto(3));
        $this->materials->snapshot = new GroupMaterialsOutputDto(11, 0, 1, 4, str_repeat('c', 64));
        $unready = $this->read->execute(self::ORGANIZER, self::GROUP);
        self::assertSame(['unassignedPhotos'], $unready->problems);
        $this->assertCode('LINK_NOT_READY', 409, fn(): object => $this->finishMarkup->execute(self::ORGANIZER, self::GROUP, $this->key('9'), new MarkupCorrectionFinishInputDto(4, $unready->signature)));
        self::assertSame('2026-09-22T11:00:00+03:00', $this->directory->markupCorrectionAt, 'Sales stay paused until the materials are ready.');
    }

    private function transmitted(): void
    {
        $signature = $this->read->execute(self::ORGANIZER, self::GROUP)->signature;
        $this->prepare->execute(self::ORGANIZER, self::GROUP, $this->key('7'), new LinkPreparationInputDto(1, $signature));
        $this->transmit->execute(self::ORGANIZER, self::GROUP, $this->key('8'), new LinkTransmissionInputDto(2, $signature, new \DateTimeImmutable('2026-09-22T10:15:00+03:00')));
    }

    private function orders(): GroupOrdersInterface&\stdClass
    {
        return new class extends \stdClass implements GroupOrdersInterface {
            public bool $exists = false;

            public function exist(string $groupPublicId): bool
            {
                return $this->exists;
            }
        };
    }

    private function corrections(\stdClass $directory): GroupMarkupCorrectionInterface
    {
        return new readonly class($directory) implements GroupMarkupCorrectionInterface {
            public function __construct(private \stdClass $directory) {}

            public function start(MarkupCorrectionInputDto $input): MarkupCorrectionOutputDto
            {
                $this->directory->markupCorrectionAt = '2026-09-22T11:00:00+03:00';

                return new MarkupCorrectionOutputDto($input->groupId, 9, $this->directory->markupCorrectionAt);
            }

            public function finish(MarkupCorrectionInputDto $input): MarkupCorrectionOutputDto
            {
                $this->directory->markupCorrectionAt = null;

                return new MarkupCorrectionOutputDto($input->groupId, 10, null);
            }
        };
    }

    private function key(string $char): IdempotencyKey
    {
        return new IdempotencyKey(str_repeat($char, 32));
    }

    private function assertCode(string $code, int $status, callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected ' . $code);
        } catch (HttpException $exception) {
            self::assertSame([$code, $status], [$exception->getMessage(), $exception->getCode()]);
        }
    }

    private function access(): GroupLinkAccessInterface
    {
        return new class implements GroupLinkAccessInterface {
            public function actor(int $userId): LinkActorOutputDto
            {
                return match ($userId) {
                    1 => new LinkActorOutputDto(1, 'organizer', 'organizer', [], []),
                    2 => new LinkActorOutputDto(2, 'curator', 'curator', [3], []),
                    3 => new LinkActorOutputDto(3, 'teacher', 'teacher', [], [10]),
                    4 => new LinkActorOutputDto(4, 'head', 'head', [3], []),
                    default => new LinkActorOutputDto($userId, 'foreign', 'curator', [9], []),
                };
            }

            public function lockState(): void {}

            public function lockActor(int $userId): LinkActorOutputDto
            {
                return $this->actor($userId);
            }
        };
    }

    private function directory(): GroupDirectoryInterface&\stdClass
    {
        return new class extends \stdClass implements GroupDirectoryInterface {
            public GroupCalendarOutputDto $calendar;
            public ?string $markupCorrectionAt = null;

            /** @var list<GroupDirectoryQueryInputDto> */
            public array $queries = [];

            public function __construct()
            {
                $this->calendar = new GroupCalendarOutputDto('Europe/Moscow', null, null, null, null, 'preparing');
            }

            public function summary(GroupDirectoryQueryInputDto $query, int $closingWithinHours): GroupDirectorySummaryOutputDto
            {
                $this->queries[] = $query;

                return new GroupDirectorySummaryOutputDto(1, 0, 0, 0, 0, '2026-09-22T08:00:00+00:00');
            }

            public function nativeIds(GroupDirectoryQueryInputDto $query): array
            {
                $this->queries[] = $query;

                return [10];
            }

            public function page(GroupDirectoryQueryInputDto $query): GroupDirectoryPageOutputDto
            {
                return new GroupDirectoryPageOutputDto([$this->item()], 1);
            }

            public function find(string $groupId): ?GroupDirectoryItemOutputDto
            {
                return '22345678-abcd-4abc-8abc-123456789abc' === $groupId ? $this->item() : null;
            }

            private function item(): GroupDirectoryItemOutputDto
            {
                return new GroupDirectoryItemOutputDto(10, '22345678-abcd-4abc-8abc-123456789abc', 'Солнышко', 'regular', 3, '32345678-abcd-4abc-8abc-123456789abc', 'Сад', 5, '12345678-abcd-4abc-8abc-123456789abc', 'Осень', $this->calendar, $this->markupCorrectionAt);
            }
        };
    }

    private function calendar(\stdClass $directory): GroupCalendarInterface&\stdClass
    {
        return new class($directory) extends \stdClass implements GroupCalendarInterface {
            /** @var list<LinkSentInputDto> */
            public array $delivered = [];

            public function __construct(private readonly \stdClass $directory) {}

            public function lock(string $groupId): int
            {
                return 3;
            }

            public function get(string $groupId): CalendarMutationOutputDto
            {
                throw new \LogicException('Not used by link commands.');
            }

            public function confirmLinkSent(CalendarCommandInputDto $input): CalendarMutationOutputDto
            {
                throw new \LogicException('Not used by link commands.');
            }

            public function extend(CalendarCommandInputDto $input, \DateTimeImmutable $newClosesAt): CalendarMutationOutputDto
            {
                throw new \LogicException('Not used by link commands.');
            }

            public function recordLinkSent(LinkSentInputDto $input): CalendarMutationOutputDto
            {
                return $this->deliver($input);
            }

            public function correctLinkSent(LinkSentInputDto $input): CalendarMutationOutputDto
            {
                return $this->deliver($input);
            }

            private function deliver(LinkSentInputDto $input): CalendarMutationOutputDto
            {
                $this->delivered[] = $input;
                $sent = $input->sentAt->setTimezone(new \DateTimeZone('Europe/Moscow'));
                $calendar = new GroupCalendarOutputDto(
                    'Europe/Moscow',
                    $sent->format(\DateTimeInterface::ATOM),
                    $sent->modify('+7 days')->format(\DateTimeInterface::ATOM),
                    $sent->modify('+14 days')->format(\DateTimeInterface::ATOM),
                    $sent->modify('+14 days')->format(\DateTimeInterface::ATOM),
                    'open',
                );
                $this->directory->calendar = $calendar;

                return new CalendarMutationOutputDto($input->groupId, 9, $calendar);
            }
        };
    }

    private function materials(): GroupMaterialsInterface&\stdClass
    {
        return new class extends \stdClass implements GroupMaterialsInterface {
            public GroupMaterialsOutputDto $snapshot;

            public function __construct()
            {
                $this->snapshot = new GroupMaterialsOutputDto(12, 0, 0, 4, str_repeat('a', 64));
            }

            public function snapshots(array $groupIds): array
            {
                return [10 => $this->snapshot];
            }

            public function lock(int $shootId, int $groupId): GroupMaterialsOutputDto
            {
                return $this->snapshot;
            }
        };
    }

    private function gallery(): GalleryLinkInterface&\stdClass
    {
        return new class extends \stdClass implements GalleryLinkInterface {
            public ?GalleryLinkOutputDto $link = null;

            public function current(string $groupId): ?GalleryLinkOutputDto
            {
                return $this->link;
            }

            public function ensure(string $groupId): GalleryLinkOutputDto
            {
                return $this->link ??= new GalleryLinkOutputDto(str_repeat('f', 64), new \DateTimeImmutable('2026-09-22T07:00:30Z'));
            }
        };
    }

    private function links(): GroupLinkRepositoryInterface&\stdClass
    {
        return new class extends \stdClass implements GroupLinkRepositoryInterface {
            /** @var array<int, LinkState> */
            public array $states = [];

            /** @var list<LinkHistoryEntry> */
            public array $history = [];

            /** @var array<string, array{payloadHash: string, result: string}> */
            public array $operations = [];

            public function states(array $groupIds): array
            {
                return array_intersect_key($this->states, array_flip($groupIds));
            }

            public function preparedCount(array $groupIds): int
            {
                return count(array_filter(
                    array_intersect_key($this->states, array_flip($groupIds)),
                    static fn(LinkState $state): bool => null !== $state->preparedSignature,
                ));
            }

            public function lock(int $groupId): LinkState
            {
                return $this->states[$groupId] ?? new LinkState();
            }

            public function prepare(int $groupId, int $expectedRevision, string $signature, int $actorId): int
            {
                $this->states[$groupId] = new LinkState($expectedRevision + 1, $signature);

                return $expectedRevision + 1;
            }

            public function advance(int $groupId, int $expectedRevision): int
            {
                $this->states[$groupId] = new LinkState($expectedRevision + 1, $this->lock($groupId)->preparedSignature);

                return $expectedRevision + 1;
            }

            public function appendHistory(LinkHistoryEntry $entry): void
            {
                $this->history[] = $entry;
            }

            public function history(int $groupId): array
            {
                return [];
            }

            public function pendingStaffRequests(array $groupIds): array
            {
                return [];
            }

            public function idempotency(int $actorId, string $resource, string $key): ?array
            {
                return $this->operations[$actorId . $resource . $key] ?? null;
            }

            public function remember(int $actorId, string $resource, string $key, string $payloadHash, string $result): void
            {
                $this->operations[$actorId . $resource . $key] = ['payloadHash' => $payloadHash, 'result' => $result];
            }
        };
    }
}
