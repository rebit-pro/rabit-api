<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit;

use Morefoto\Access\Application\Bootstrap\UseCase\BootstrapOrganizerUseCase;
use Morefoto\Access\Presentation\Console\BootstrapOrganizerCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
final class BootstrapOrganizerCommandTest extends TestCase
{
    #[DataProvider('invalidInvocations')]
    public function testUnsafeInvocationDoesNotReachUseCase(string $environment, string $id, ?string $confirmation): void
    {
        $useCase = $this->createMock(BootstrapOrganizerUseCase::class);
        $useCase->expects(self::never())->method('execute');
        $tester = new CommandTester(new BootstrapOrganizerCommand($useCase, $environment));
        self::assertSame(2, $tester->execute(null === $confirmation ? ['user-id' => $id] : ['user-id' => $id, '--confirm-user-id' => $confirmation]));
    }

    public static function invalidInvocations(): iterable
    {
        yield 'production' => ['production', '10', '10'];
        yield 'missing environment' => ['', '10', '10'];
        yield 'missing confirmation' => ['local', '10', null];
        yield 'wrong confirmation' => ['test', '10', '11'];
        yield 'zero' => ['test', '0', '0'];
        yield 'overflow' => ['test', '99999999999999999999', '99999999999999999999'];
        yield 'negative' => ['test', '-1', '-1'];
    }

    public function testConfirmedLocalInvocationRevokesOldSessionMessage(): void
    {
        $useCase = $this->createMock(BootstrapOrganizerUseCase::class);
        $useCase->expects(self::once())->method('execute')->with(10)->willReturn(true);
        $tester = new CommandTester(new BootstrapOrganizerCommand($useCase, 'test'));
        self::assertSame(0, $tester->execute(['user-id' => '10', '--confirm-user-id' => '10']));
        self::assertStringContainsString('sign in again', $tester->getDisplay());
    }
}
