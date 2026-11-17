<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit;

use Morefoto\Files\Application\Files\Dto\MaintenanceReportOutputDto;
use Morefoto\Files\Application\Files\UseCase\DispatchPendingDownloadsUseCase;
use Morefoto\Files\Application\Files\UseCase\PurgeDownloadsUseCase;
use Morefoto\Files\Application\Institution\UseCase\MaintainInstitutionDigitalArchivesUseCase;
use Morefoto\Files\Presentation\Command\DispatchPendingDownloadsCommand;
use Morefoto\Files\Presentation\Command\PurgeDownloadsCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class DownloadsMaintenanceCommandTest extends TestCase
{
    #[DataProvider('reports')]
    public function testDispatchExitStatusIncludesBothBatches(int $buyerFailures, int $institutionFailures, int $expected): void
    {
        $buyer = $this->createMock(DispatchPendingDownloadsUseCase::class);
        $buyer->expects(self::once())->method('execute')->with(7)->willReturn(new MaintenanceReportOutputDto(1, $buyerFailures));
        $institution = $this->createMock(MaintainInstitutionDigitalArchivesUseCase::class);
        $institution->expects(self::once())->method('dispatch')->with(7)->willReturn(new MaintenanceReportOutputDto(2, $institutionFailures));

        $tester = new CommandTester(new DispatchPendingDownloadsCommand($buyer, $institution));
        self::assertSame($expected, $tester->execute(['--limit' => 7]));
    }

    #[DataProvider('reports')]
    public function testPurgeExitStatusIncludesBothBatches(int $buyerFailures, int $institutionFailures, int $expected): void
    {
        $buyer = $this->createMock(PurgeDownloadsUseCase::class);
        $buyer->expects(self::once())->method('execute')->with(7)->willReturn(new MaintenanceReportOutputDto(1, $buyerFailures));
        $institution = $this->createMock(MaintainInstitutionDigitalArchivesUseCase::class);
        $institution->expects(self::once())->method('purge')->with(7)->willReturn(new MaintenanceReportOutputDto(2, $institutionFailures));

        $tester = new CommandTester(new PurgeDownloadsCommand($buyer, $institution));
        self::assertSame($expected, $tester->execute(['--limit' => 7]));
    }

    /** @return iterable<string, array{
     *     0: int,
     *     1: int,
     *     2: int,
     * }> */
    public static function reports(): iterable
    {
        yield 'success' => [0, 0, Command::SUCCESS];
        yield 'buyer failure' => [1, 0, Command::FAILURE];
        yield 'institution failure' => [0, 1, Command::FAILURE];
    }
}
