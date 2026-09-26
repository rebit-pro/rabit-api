<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\Result;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * #116: order of the deletion SQL against the FK RESTRICT links. The real statements run on MySQL in verify-photo-deletion.php.
 *
 * @internal
 */
final class MediaMutationDeletionSqlTest extends TestCase
{
    private DeletionSqlSpy $connection;

    protected function setUp(): void
    {
        $this->connection = new DeletionSqlSpy();
        (new \ReflectionProperty(Application::class, 'connection'))->setValue(null, $this->connection);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Application::class, 'connection'))->setValue(null, null);
    }

    public function testDuplicatesAssignmentsAndTheCoverLeaveBeforeThePhotoRows(): void
    {
        $this->connection->rows = [
            'SELECT GROUP_ID FROM mf_media_group_cover' => [['GROUP_ID' => '3'], ['GROUP_ID' => '4']],
            'SELECT p.ID FROM mf_photo_assignment a' => [['ID' => '12']],
        ];

        (new MediaMutationRepository())->deletePhotos([10, 11]);

        self::assertSame([
            'DELETE FROM b_hlbd_mf_photo WHERE UF_EXISTING_PHOTO_ID IN (10,11)',
            'DELETE FROM mf_photo_assignment WHERE PHOTO_ID IN (10,11)',
            'UPDATE mf_media_group_cover SET PHOTO_ID=12,UPDATED_AT=UTC_TIMESTAMP() WHERE GROUP_ID=3',
            'UPDATE mf_media_group_cover SET PHOTO_ID=12,UPDATED_AT=UTC_TIMESTAMP() WHERE GROUP_ID=4',
            'DELETE FROM b_hlbd_mf_photo WHERE ID IN (10,11)',
        ], $this->connection->writes);
    }

    public function testCoverWithoutAnotherLabeledPhotoIsRemoved(): void
    {
        $this->connection->rows = ['SELECT GROUP_ID FROM mf_media_group_cover' => [['GROUP_ID' => '3']]];

        (new MediaMutationRepository())->deletePhotos([10]);

        self::assertContains('DELETE FROM mf_media_group_cover WHERE GROUP_ID=3', $this->connection->writes);
        self::assertSame('DELETE FROM b_hlbd_mf_photo WHERE ID IN (10)', $this->connection->writes[array_key_last($this->connection->writes)]);
    }

    public function testProcessingPhotoRefusesTheWholeSetWithoutWrites(): void
    {
        $this->connection->rows = ['SELECT ID,UF_PUBLIC_ID,UF_STATUS' => [
            ['ID' => '10', 'UF_PUBLIC_ID' => 'ready-photo', 'UF_STATUS' => 'ready', 'UF_ORIGINAL_PATH' => 'a.jpg'],
            ['ID' => '11', 'UF_PUBLIC_ID' => 'busy-photo', 'UF_STATUS' => 'processing', 'UF_ORIGINAL_PATH' => 'b.jpg'],
        ]];

        try {
            (new MediaMutationRepository())->deletablePhotos(20, 3, ['ready-photo', 'busy-photo']);
            self::fail('A set with a processing photo must be refused.');
        } catch (HttpException $error) {
            self::assertSame('PHOTO_PROCESSING', $error->getMessage());
            self::assertSame(409, $error->getCode());
        }
        self::assertStringEndsWith('FOR UPDATE', $this->connection->reads[0]);
        self::assertSame([], $this->connection->writes);
    }

    public function testMissingPhotoIsNotDeletable(): void
    {
        $this->connection->rows = ['SELECT ID,UF_PUBLIC_ID,UF_STATUS' => [
            ['ID' => '10', 'UF_PUBLIC_ID' => 'ready-photo', 'UF_STATUS' => 'ready', 'UF_ORIGINAL_PATH' => null],
        ]];

        $this->expectExceptionObject(new HttpException('PHOTO_NOT_DELETABLE', 409));
        (new MediaMutationRepository())->deletablePhotos(20, 3, ['ready-photo', 'deleted-photo']);
    }
}

/** Records reads and writes; a read returns the rows registered for the prefix of its SQL. */
final class DeletionSqlSpy extends Connection
{
    /** @var array<string, list<array<string, null|string>>> */
    public array $rows = [];

    /** @var list<string> */
    public array $reads = [];

    /** @var list<string> */
    public array $writes = [];

    public function query(string $sql): Result
    {
        $this->reads[] = $sql;
        foreach ($this->rows as $prefix => $rows) {
            if (str_starts_with($sql, $prefix)) {
                return new DeletionSqlRows($rows);
            }
        }

        return new DeletionSqlRows([]);
    }

    public function queryExecute(string $sql): void
    {
        $this->writes[] = $sql;
    }
}

final class DeletionSqlRows extends Result
{
    /** @param list<array<string, null|string>> $rows */
    public function __construct(private array $rows) {}

    public function fetch(): array|false
    {
        return array_shift($this->rows) ?? false;
    }
}
