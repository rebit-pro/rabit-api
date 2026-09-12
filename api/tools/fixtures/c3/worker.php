<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Organization\Application\Calendar\UseCase\ChangeGroupCalendarUseCase;
use Morefoto\Organization\Application\Structure\Dto\GroupMutationInputDto;
use Morefoto\Organization\Application\Structure\Dto\ShootMutationInputDto;
use Morefoto\Organization\Application\Structure\UseCase\SaveGroupUseCase;
use Morefoto\Organization\Application\Structure\UseCase\SaveShootUseCase;
use Morefoto\Organization\Domain\Structure\Exception\StructureVersionConflictException;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto;
use Rebit\Share\Shared\Exception\HttpException;

$documentRoot = $argv[1] ?? '';
$jobFile = $argv[2] ?? '';
if (!str_starts_with($documentRoot, '/tmp/rabit-w02-') || !str_starts_with($jobFile, $documentRoot . '/c3-concurrency-')) {
    throw new RuntimeException('C3 worker requires its disposable fixture job.');
}
$job = json_decode((string)file_get_contents($jobFile), true, 32, JSON_THROW_ON_ERROR);
foreach (['ready', 'result'] as $path) {
    if (!is_string($job[$path] ?? null) || dirname($jobFile) !== dirname($job[$path])) {
        throw new RuntimeException('Invalid C3 worker output path.');
    }
}
// Existing connection bootstrap initializes native Bitrix, never a new schema or production prolog.
require __DIR__ . '/../c2/connect.php';
RegisterModuleDependences('main', 'OnUserTypeBuildList', 'main', CUserTypeDate::class, 'GetUserTypeDescription');
$autoload = require '/app/vendor/autoload.php';
$autoload->addPsr4('Morefoto\Organization\\', '/app/public/local/modules/morefoto.organization/lib/', true);
$connection = Application::getConnection();
$connectionId = (int)$connection->query('SELECT CONNECTION_ID() id')->fetch()['id'];
file_put_contents($job['ready'], (string)$connectionId);
$locator = ServiceLocator::getInstance();
try {
    $actor = $job['actor'];
    $bearer = $job['bearer'];
    $create = $job['create'] ?? false;
    if ('shoot' === $job['action']) {
        $result = $locator->get(SaveShootUseCase::class)->execute($actor, $bearer, new StructureId($job['target']), $create, new ShootMutationInputDto(
            key: $job['key'],
            name: $job['name'],
            dateProvided: $job['dateProvided'] ?? false,
            date: $job['date'] ?? null,
            revision: $job['revision'] ?? null,
        ));
    } elseif ('group' === $job['action']) {
        $result = $locator->get(SaveGroupUseCase::class)->execute($actor, $bearer, new StructureId($job['target']), $create, new GroupMutationInputDto(
            key: $job['key'],
            name: $job['name'] ?? null,
            groupKind: $job['groupKind'] ?? null,
            revision: $job['revision'] ?? null,
            teacherProvided: $job['teacherProvided'] ?? false,
            teacherId: $job['teacherId'] ?? null,
            assignmentSignature: $job['signature'] ?? null,
            replaceAssignments: $job['replaceAssignments'] ?? false,
            reason: $job['reason'] ?? null,
        ));
    } elseif (in_array($job['action'], ['calendarConfirm', 'calendarExtend'], true)) {
        $input = new CalendarCommandInputDto($job['target'], $actor, $job['revision'], $job['key'], $job['reason']);
        $commands = $locator->get(ChangeGroupCalendarUseCase::class);
        $result = 'calendarConfirm' === $job['action']
            ? $commands->confirmLinkSent($input, $bearer)
            : $commands->extend($input, new DateTimeImmutable($job['closesAt']), $bearer);
    } else {
        throw new InvalidArgumentException('Unknown C3 worker action.');
    }
    $output = ['status' => $create ? 201 : 200, 'result' => $result];
} catch (Throwable $exception) {
    $status = $exception instanceof StructureVersionConflictException ? 409 : ($exception instanceof HttpException ? $exception->getCode() : 500);
    $output = ['status' => $status, 'type' => $exception::class, 'message' => $exception->getMessage()];
}
$output['transactionLevel'] = (new ReflectionProperty($connection, 'transactionLevel'))->getValue($connection);
file_put_contents($job['result'] . '.tmp', json_encode($output, JSON_THROW_ON_ERROR));
rename($job['result'] . '.tmp', $job['result']);
