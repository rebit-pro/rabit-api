<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\Result;
use Morefoto\Handoff\Domain\Request\Repository\StaffRequestRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\Dto\StaffRequestActorOutputDto;

require_once __DIR__ . '/../bootstrap.php';

/**
 * #26: HND-06 reads a page with a fixed number of SQL queries, whatever the page size.
 *
 * @internal
 */
final class StaffRequestRepositoryTest extends TestCase
{
    private StaffRequestSqlSpy $connection;

    protected function setUp(): void
    {
        $this->connection = new StaffRequestSqlSpy();
        (new \ReflectionProperty(Application::class, 'connection'))->setValue(null, $this->connection);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Application::class, 'connection'))->setValue(null, null);
    }

    #[DataProvider('sizes')]
    public function testPageQueryCountDoesNotDependOnItems(int $size): void
    {
        $this->connection->seed($size);
        $started = hrtime(true);

        $page = (new StaffRequestRepository())->page($this->organizer(), null, null, null, $size, 0);

        self::assertCount($size, $page['items']);
        // Counters, cards, rows with transfer results and history: four queries for 1, 100 and 1000 requests.
        self::assertCount(4, $this->connection->queries, implode(PHP_EOL, $this->connection->queries));
        self::assertLessThan(2.0, (hrtime(true) - $started) / 1e9);
        self::assertSame($size + 1, $page['total']);
    }

    /** @return iterable<string, array{int}> */
    public static function sizes(): iterable
    {
        yield 'one' => [1];
        yield 'page of 100' => [100];
        yield 'set of 1000' => [1000];
    }

    public function testRelatedRowsAndHistoryStayWithTheirRequestsInPageOrder(): void
    {
        $this->connection->seed(3);

        $items = (new StaffRequestRepository())->page($this->organizer(), null, null, null, 3, 0)['items'];

        self::assertSame(['request-1', 'request-2', 'request-3'], array_column($items, 'id'));
        self::assertSame(['row-2-1', 'row-2-2'], array_column($items[1]['rows'], 'id'));
        self::assertSame(['A', 'B'], array_column($items[1]['rows'], 'childCode'));
        self::assertSame(['photo-2-1'], $items[1]['rows'][0]['photoIds']);
        self::assertSame([['rowId' => 'row-2-1', 'fromGroupId' => 'group-1', 'fromChildCode' => 'A', 'targetGroupId' => 'staff-1', 'targetChildCode' => 'C', 'photoIds' => ['photo-2-1']]], $items[1]['results']);
        self::assertSame([], $items[0]['results']);
        self::assertSame(['submitted', 'clarification'], array_column($items[1]['history'], 'kind'));
        self::assertSame(['submitted'], array_column($items[2]['history'], 'kind'));
        self::assertSame(['eligible' => true, 'source' => 'verified_staff_assignment', 'verifiedAt' => '2026-09-25T10:00:00Z'], $items[0]['staffEligibility']);
    }

    public function testFiltersScopeAndPaginationReachTheCardQuery(): void
    {
        $this->connection->seed(2);
        $teacher = new StaffRequestActorOutputDto(7, 'Воспитатель', 'teacher', 1, [], [30, 31]);

        $page = (new StaffRequestRepository())->page($teacher, 'institution-1', 'shoot-1', 'clarification', 20, 40);

        [$counts, $cards] = $this->connection->queries;
        self::assertStringContainsString('r.CREATED_BY=7 AND NOT EXISTS (SELECT 1 FROM mf_staff_request_row visible_row WHERE visible_row.REQUEST_ID=r.ID AND visible_row.GROUP_ID NOT IN (30,31))', $cards);
        self::assertStringContainsString("i.UF_PUBLIC_ID='institution-1' AND s.UF_PUBLIC_ID='shoot-1' AND r.STATUS='clarification'", $cards);
        self::assertStringEndsWith('ORDER BY r.UPDATED_AT DESC,r.ID DESC LIMIT 20 OFFSET 40', $cards);
        // The split by status ignores the status filter; the filtered total is taken from it.
        self::assertStringNotContainsString('r.STATUS=', $counts);
        self::assertSame(1, $page['total']);
        self::assertSame(['submitted' => 2, 'clarification' => 1, 'transferred' => 0], $page['byStatus']);
    }

    public function testEmptyPageSkipsRelatedQueries(): void
    {
        $page = (new StaffRequestRepository())->page(new StaffRequestActorOutputDto(8, 'Руководитель', 'head', 1, [10], []), null, null, null, 25, 0);

        self::assertSame([], $page['items']);
        self::assertCount(2, $this->connection->queries);
        self::assertStringContainsString('r.INSTITUTION_ID IN (10)', $this->connection->queries[1]);
    }

    public function testDetailCardUsesTheSameBatchReads(): void
    {
        $this->connection->seed(2);
        $repository = new StaffRequestRepository();

        $view = $repository->view(['ID' => 2, 'PUBLIC_ID' => 'request-2', 'INSTITUTION_PUBLIC_ID' => 'institution-1', 'SHOOT_PUBLIC_ID' => 'shoot-1', 'CREATED_BY' => 7, 'CREATED_BY_NAME' => 'Воспитатель', 'CREATED_AT' => '2026-09-25T10:00:00Z', 'REVISION' => 3, 'STATUS' => 'submitted', 'COMMENT' => '', 'STAFF_ELIGIBLE' => 1, 'ELIGIBILITY_SOURCE' => 'verified_staff_assignment', 'ELIGIBILITY_VERIFIED_AT' => '2026-09-25T10:00:00Z']);

        self::assertSame(['row-2-1', 'row-2-2'], array_column($view['rows'], 'id'));
        self::assertCount(2, $view['history']);
        self::assertSame('staff-1', $repository->results(2)[0]['targetGroupId']);
        self::assertCount(3, $this->connection->queries);
    }

    private function organizer(): StaffRequestActorOutputDto
    {
        return new StaffRequestActorOutputDto(1, 'Организатор', 'organizer', 1, [], []);
    }
}

/**
 * Records SQL and answers with a synthetic set: request N has two rows (the first transferred for even N) and one or two
 * history entries. One extra clarification request is counted but never listed.
 */
final class StaffRequestSqlSpy extends Connection
{
    /** @var list<string> */
    public array $queries = [];

    private int $size = 0;

    public function seed(int $size): void
    {
        $this->size = $size;
    }

    public function query(string $sql): Result
    {
        $this->queries[] = $sql;
        $rows = match (true) {
            str_starts_with($sql, 'SELECT r.STATUS, COUNT(*)') => [['STATUS' => 'submitted', 'TOTAL' => $this->size], ['STATUS' => 'clarification', 'TOTAL' => 1]],
            str_contains($sql, 'FROM mf_staff_request_row rr') => $this->rows($this->ids($sql)),
            str_contains($sql, 'FROM mf_staff_request_history') => $this->history($this->ids($sql)),
            str_starts_with($sql, 'SELECT r.ID,r.PUBLIC_ID') => 0 === $this->size ? [] : array_map($this->card(...), range(1, $this->size)),
            default => [],
        };

        return new StaffRequestSqlResult($rows);
    }

    /** @return array<string, mixed> */
    private function card(int $id): array
    {
        return [
            'ID' => (string)$id,
            'PUBLIC_ID' => 'request-' . $id,
            'INSTITUTION_ID' => '10',
            'SHOOT_ID' => '20',
            'CREATED_BY' => '7',
            'CREATED_BY_NAME' => 'Воспитатель',
            'STATUS' => 'submitted',
            'REVISION' => '1',
            'COMMENT' => '',
            'STAFF_ELIGIBLE' => '1',
            'ELIGIBILITY_SOURCE' => 'verified_staff_assignment',
            'ELIGIBILITY_VERIFIED_AT' => '2026-09-25T10:00:00Z',
            'CREATED_AT' => '2026-09-25T10:00:00Z',
            'INSTITUTION_PUBLIC_ID' => 'institution-1',
            'SHOOT_PUBLIC_ID' => 'shoot-1',
        ];
    }

    /**
     * @param list<int> $ids
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $ids): array
    {
        $rows = [];
        foreach ($ids as $id) {
            foreach (['A', 'B'] as $index => $code) {
                $transferred = 0 === $id % 2 && 0 === $index;
                $rows[] = [
                    'REQUEST_ID' => (string)$id,
                    'PUBLIC_ID' => 'row-' . $id . '-' . ($index + 1),
                    'GROUP_PUBLIC_ID' => 'group-1',
                    'INPUT_CODE' => $code . '001',
                    'CODE' => $code,
                    'PHOTO_IDS_JSON' => '["photo-' . $id . '-' . ($index + 1) . '"]',
                    'TRANSFER_FROM_CODE' => $transferred ? $code : null,
                    'TARGET_GROUP_ID' => $transferred ? 'staff-1' : null,
                    'TRANSFER_CODE' => $transferred ? 'C' : null,
                    'TRANSFER_PHOTO_IDS_JSON' => $transferred ? '["photo-' . $id . '-1"]' : null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<array<string, mixed>>
     */
    private function history(array $ids): array
    {
        $history = [];
        foreach ($ids as $id) {
            foreach (0 === $id % 2 ? ['submitted', 'clarification'] : ['submitted'] as $kind) {
                $history[] = ['REQUEST_ID' => (string)$id, 'KIND' => $kind, 'ACTOR_ID' => '7', 'ACTOR_NAME' => 'Воспитатель', 'CREATED_AT' => '2026-09-25T10:00:00Z', 'COMMENT' => '', 'CONFIRMED' => '1'];
            }
        }

        return $history;
    }

    /** @return list<int> */
    private function ids(string $sql): array
    {
        if (1 !== preg_match('/REQUEST_ID IN \(([0-9,]+)\)/', $sql, $match)) {
            throw new \LogicException('Batch query without an ID list: ' . $sql);
        }

        return array_map('intval', explode(',', $match[1]));
    }
}

final class StaffRequestSqlResult extends Result
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(private array $rows) {}

    public function fetch(): array|false
    {
        return array_shift($this->rows) ?? false;
    }
}
