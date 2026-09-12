<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Organization\Application\Institution\Dto\ListInstitutionsInputDto;
use Morefoto\Organization\Application\Institution\UseCase\CreateInstitutionUseCase;
use Morefoto\Organization\Application\Institution\UseCase\GetInstitutionUseCase;
use Morefoto\Organization\Application\Institution\UseCase\ListInstitutionsUseCase;
use Morefoto\Organization\Application\Institution\UseCase\UpdateInstitutionUseCase;
use Morefoto\Organization\Domain\Institution\Exception\InstitutionNotFoundException;
use Morefoto\Organization\Domain\Institution\Exception\InstitutionVersionConflictException;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionRepository;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionDetails;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;
use Bitrix\Main\ModuleManager;
use Sprint\Migration\Version20260911210001;

$checks = [];
$stage = 'bootstrap';
$assert = static function(bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Check failed: ' . $label);
    }
    $checks[] = $label;
};
$expect = static function(callable $operation, string $class, string $label) use ($assert): void {
    try {
        $operation();
    } catch (Throwable $exception) {
        $assert($exception instanceof $class, $label . ' (' . $exception::class . ')');

        return;
    }
    throw new RuntimeException('Expected failure: ' . $label);
};
try {
    $fixture = require __DIR__ . '/fixtures/w02/bootstrap.php';
    $sql = $fixture['connection'];
    $root = $fixture['documentRoot'];
    symlink('/app/public/local/modules/morefoto.organization', $root . '/local/modules/morefoto.organization');
    require_once '/kernel/modules/highloadblock/install/index.php';
    $assert((new highloadblock())->InstallDB(), 'native highloadblock installed');
    require_once '/app/public/local/php_interface/migrations.foundation/Version20260911210001.php';
    $migration = new Version20260911210001();
    ob_start();
    try {
        $migration->up();
        $migration->up();
        $migration->down();
        $assert(!Application::getConnection()->isTableExists('b_hlbd_mf_institution'), 'empty down removes schema');
        $migration->up();
    } finally {
        ob_end_clean();
    }
    $assert(Loader::includeModule('morefoto.organization'), 'native module loads without Composer changes');
    $assert(!ModuleManager::isModuleInstalled('morefoto.access'), 'Access is absent; C1 does not depend on B1');
    $locator = ServiceLocator::getInstance();
    $create = $locator->get(CreateInstitutionUseCase::class);
    $update = $locator->get(UpdateInstitutionUseCase::class);
    $get = $locator->get(GetInstitutionUseCase::class);
    $list = $locator->get(ListInstitutionsUseCase::class);
    $repository = $locator->get(InstitutionRepository::class);
    $assert($create === $locator->get(CreateInstitutionUseCase::class), 'DI singleton resolved');
    $stage = 'crud';
    $empty = $list->execute(new ListInstitutionsInputDto());
    $assert([] === $empty->items && 0 === $empty->total, 'genuine empty list and total');
    $first = $create->execute(new InstitutionDetails('  Сад № 1  ', '  улица Первая  '));
    $id = new InstitutionId($first->id);
    $assert(1 === $first->revision && 'Сад № 1' === $first->name && 'улица Первая' === $first->address, 'creation normalizes fields and starts revision at one');
    $assert($first == $get->execute(new InstitutionId(strtoupper($first->id))), 'D7 read canonicalizes public UUID');
    $saved = $update->execute($id, new InstitutionDetails('Сад № 2', ''), 1);
    $assert(2 === $saved->revision && '' === $get->execute($id)->address, 'atomic update can clear address');
    $expect(static fn() => $update->execute($id, new InstitutionDetails('Stale edit', 'old'), 1), InstitutionVersionConflictException::class, 'stale revision is rejected');
    $assert('Сад № 2' === $get->execute($id)->name && 2 === $get->execute($id)->revision, 'rejected update leaves data and version intact');
    $missing = new InstitutionId('12345678-abcd-4abc-8abc-123456789abc');
    $expect(static fn() => $get->execute($missing), InstitutionNotFoundException::class, 'missing UUID is not found');
    $expect(static fn() => $update->execute($missing, new InstitutionDetails('Missing', ''), 1), InstitutionNotFoundException::class, 'update cannot create missing institution');
    $stage = 'pagination-and-search';
    $records = [];
    foreach (['Сад № 3', 'Сад № 4', '100% школа', '100X школа', 'A_школа', 'ABшкола', "O'Reilly", 'Путь\Школа'] as $name) {
        $records[] = $create->execute(new InstitutionDetails($name, ''));
    }
    // Force equal timestamps to exercise the stable internal-ID tie breaker.
    $sql->query("UPDATE b_hlbd_mf_institution SET UF_CREATED_AT='2026-01-01 00:00:00'");
    $page1 = $list->execute(new ListInstitutionsInputDto('', 1, 3));
    $page2 = $list->execute(new ListInstitutionsInputDto('', 2, 3));
    $assert(9 === $page1->total && 3 === count($page1->items) && 3 === count($page2->items), 'page bounds and total');
    $assert($records[7]->id === $page1->items[0]->id && $records[4]->id === $page2->items[0]->id, 'stable ordering for identical timestamps');
    $outside = $list->execute(new ListInstitutionsInputDto('', 100, 3));
    $assert([] === $outside->items && 9 === $outside->total, 'out-of-range page keeps true total');
    foreach (['100%' => '100% школа', 'A_' => 'A_школа', "O'" => "O'Reilly", 'Путь\\' => 'Путь\Школа'] as $query => $name) {
        $found = $list->execute(new ListInstitutionsInputDto($query));
        $assert(1 === $found->total && $name === $found->items[0]->name, 'literal escaped prefix: ' . $query);
    }
    $assert(3 === $list->execute(new ListInstitutionsInputDto('сад'))->total, 'Cyrillic prefix is case insensitive');
    $assert(0 === $list->execute(new ListInstitutionsInputDto("' OR 1=1 --"))->total, 'search input cannot inject SQL');
    $tracker = Application::getConnection()->startTracker();
    $list->execute(new ListInstitutionsInputDto('', 1, 3));
    Application::getConnection()->stopTracker();
    $assert(1 === count($tracker->getQueries()), 'items and total use a single snapshot query, no N+1');
    $stage = 'database-constraints';
    $rejectSql = static function(string $query, int $code, string $label) use ($sql, $assert): void {
        try {
            $result = $sql->query($query);
        } catch (mysqli_sql_exception $exception) {
            $assert($code === $exception->getCode(), $label);

            return;
        }
        $assert(false === $result && $code === $sql->errno, $label);
    };
    $rejectSql("UPDATE b_hlbd_mf_institution SET UF_REVISION=0 WHERE UF_PUBLIC_ID='{$id->value}'", 3819, 'positive revision enforced by DB');
    $rejectSql("UPDATE b_hlbd_mf_institution SET UF_NAME=' ' WHERE UF_PUBLIC_ID='{$id->value}'", 3819, 'empty name rejected by DB');
    $rejectSql("UPDATE b_hlbd_mf_institution SET UF_PUBLIC_ID=UPPER(UF_PUBLIC_ID) WHERE UF_PUBLIC_ID='{$id->value}'", 3819, 'canonical UUID enforced by binary collation check');
    $rejectSql("UPDATE b_hlbd_mf_institution SET UF_PUBLIC_ID='{$id->value}' WHERE UF_NAME='100% школа'", 1062, 'unique public UUID enforced');
    $indexes = $sql->query("SHOW INDEX FROM b_hlbd_mf_institution WHERE Key_name IN ('ux_mf_institution_public','ix_mf_institution_created','ix_mf_institution_name')");
    $assert(5 === $indexes->num_rows, 'three application indexes installed');
    $expect(static fn() => $migration->down(), RuntimeException::class, 'down preserves populated table');
    $assert(9 === $list->execute(new ListInstitutionsInputDto())->total, 'failed down preserves every record');
    $assert('InnoDB' === $sql->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='b_hlbd_mf_institution'")->fetch_assoc()['ENGINE'], 'transactional storage engine');
    echo json_encode([
        'status' => 'PASS', 'wave' => 'C1', 'checksPassed' => count($checks), 'checks' => $checks,
        'php' => PHP_VERSION, 'bitrix' => $fixture['kernelVersion'], 'mysql' => $sql->server_info,
        'nativeModuleAutoload' => true, 'accessInstalled' => false, 'httpEndpointsAdded' => false,
        'externalNetwork' => false, 'hostPorts' => false,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'status' => 'FAIL', 'stage' => $stage, 'checksPassed' => count($checks),
        'error' => $exception->getMessage(), 'cause' => $exception->getPrevious()?->getMessage(),
        'file' => basename($exception->getFile()), 'line' => $exception->getLine(),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
