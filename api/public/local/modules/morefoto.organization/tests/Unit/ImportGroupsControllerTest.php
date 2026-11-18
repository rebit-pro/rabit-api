<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Morefoto\Organization\Presentation\Structure\Dto\ImportGroupsRequestDto;
use Morefoto\Organization\Presentation\Structure\ImportGroupsInputMapper;
use Rebit\Share\Shared\Exception\HttpException;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Tests\Support\CleanControllerSource;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class ImportGroupsControllerTest extends TestCase
{
    public function testControllerUsesOnlyPresentationDtosAndItsUseCase(): void
    {
        self::assertSame([], CleanControllerSource::violations(dirname(__DIR__, 2) . '/lib/Presentation/Controller/ImportGroupsController.php', [
            'Morefoto\Organization\Presentation\Controller',
            'Morefoto\Organization\Application\Structure\UseCase\ImportGroupsUseCase',
            'Morefoto\Organization\Presentation\Structure\Dto\ImportGroupsRequestDto',
            'Morefoto\Organization\Presentation\Structure\Dto\ImportGroupsResultDto',
            'Morefoto\Organization\Presentation\Structure\ImportGroupsInputMapper',
            'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
            'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
        ], []));
    }

    public function testChecksTheWholeMappingBeforeCreatingGroups(): void
    {
        $key = str_repeat('a', 32);
        $id = 'a1234567-1234-4123-8123-123456789012';
        $mapper = new ImportGroupsInputMapper();
        $groups = [' 1А ', '1А', '1Б'];
        $input = $mapper->map(new ImportGroupsRequestDto($groups, $id, $id, $key, 'Bearer example'));
        self::assertSame(['1А', '1А', '1Б'], $input->groups);
        $this->expectException(HttpException::class);
        $mapper->map(new ImportGroupsRequestDto(['1А', ' '], $id, $id, $key, 'Bearer example'));
    }
}
