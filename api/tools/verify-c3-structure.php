<?php

declare(strict_types=1);

use Bitrix\Main\Type\DateTime;
use Morefoto\Organization\Application\Institution\Dto\InstitutionMutationInputDto;
use Morefoto\Organization\Application\Institution\UseCase\SaveInstitutionUseCase;
use Morefoto\Organization\Application\Structure\Dto\GroupMutationInputDto;
use Morefoto\Organization\Application\Structure\Dto\ShootMutationInputDto;
use Morefoto\Organization\Application\Structure\Dto\StructurePageInputDto;
use Morefoto\Organization\Application\Structure\UseCase\GetShootUseCase;
use Morefoto\Organization\Application\Structure\UseCase\ListShootsUseCase;
use Morefoto\Organization\Application\Structure\UseCase\SaveGroupUseCase;
use Morefoto\Organization\Application\Structure\UseCase\SaveShootUseCase;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionId;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Rebit\Share\Contracts\Access\InstitutionAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Ramsey\Uuid\Uuid;
use Morefoto\Organization\Domain\Structure\Exception\StructureVersionConflictException;
use Sprint\Migration\Version20260912210001;
use Sprint\Migration\Version20260913010001;
use Sprint\Migration\Version20260913010002;

$checks = [];
$stage = 'bootstrap';
$assert = static function(bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Check failed: ' . $label);
    }
    $checks[] = $label;
};
$expect = static function(callable $operation, string $label) use ($assert): void {
    try {
        $operation();
    } catch (Throwable) {
        $assert(true, $label);

        return;
    }
    throw new RuntimeException('Expected rejection: ' . $label);
};
$expectCode = static function(callable $operation, int $code, string $label) use ($assert): void {
    try {
        $operation();
    } catch (Throwable $error) {
        $actual = match (true) {
            $error instanceof HttpException => $error->getCode(),
            $error instanceof StructureVersionConflictException => 409,
            default => 0,
        };
        if (0 === $actual) {
            throw $error;
        }
        $assert($code === $actual, $label);

        return;
    }
    throw new RuntimeException('Expected HTTP rejection: ' . $label);
};
try {
    $context = require __DIR__ . '/fixtures/c3/bootstrap.php';
    $connection = $context['connection'];
    $sql = $context['sql'];
    $locator = $context['locator'];
    $users = $context['users'];
    $staff = $context['staff'];
    $actor = $staff['organizer'];
    $bearer = $context['actorBearer'];
    $context['assert'] = $assert;
    $context['expect'] = $expect;
    $stage = 'schema';
    foreach (['b_hlbd_mf_shoot', 'b_hlbd_mf_group', 'b_hlbd_mf_group_assignment'] as $table) {
        $row = $connection->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}'")->fetch();
        $assert('InnoDB' === $row['ENGINE'], $table . ' native InnoDB');
    }
    $assert(3 === (int)$connection->query("SELECT COUNT(*) C FROM b_hlblock_entity WHERE NAME IN ('MfShoot','MfGroup','MfGroupAssignment')")->fetch()['C'], 'three native HL records after repeated migration up');
    $columns = [];
    $indexes = $connection->query("SHOW INDEX FROM b_hlbd_mf_organization_change WHERE Key_name='ux_mf_org_change_version'");
    while (false !== ($row = $indexes->fetch())) {
        $columns[(int)$row['Seq_in_index']] = $row['Column_name'];
    }
    ksort($columns);
    $assert(['UF_AGGREGATE_TYPE', 'UF_AGGREGATE_ID', 'UF_TO_REVISION'] === array_values($columns), 'history unique index includes aggregate type');
    ob_start();
    try {
        (new Version20260913010002())->down();
        (new Version20260913010001())->down();
        $assert(!$connection->isTableExists('b_hlbd_mf_group') && $connection->isTableExists('b_hlbd_mf_institution'), 'empty rollback preserves earlier C2 institutions');
        (new Version20260913010001())->up();
        (new Version20260913010002())->up();
        (new Version20260912210001())->up();
    } finally {
        ob_end_clean();
    }
    $stage = 'structure';
    $institutions = $locator->get(SaveInstitutionUseCase::class);
    $shoots = $locator->get(SaveShootUseCase::class);
    $groups = $locator->get(SaveGroupUseCase::class);
    $list = $locator->get(ListShootsUseCase::class);
    $detail = $locator->get(GetShootUseCase::class);
    $key = static fn(): string => bin2hex(random_bytes(16));
    $institution = $institutions->execute($actor, $bearer, null, new InstitutionMutationInputDto($key(), 'Учреждение C3', 'Тестовый адрес', null, false, null, false, null, null, false));
    $otherInstitution = $institutions->execute($actor, $bearer, null, new InstitutionMutationInputDto($key(), 'Другое учреждение', 'Другой адрес', null, false, null, false, null, null, false));
    $parent = new StructureId($institution->id);
    $createInput = new ShootMutationInputDto($key(), 'Осень', true, '2026-10-01', null);
    $first = $shoots->execute($actor, $bearer, $parent, true, $createInput);
    $replay = $shoots->execute($actor, $bearer, $parent, true, $createInput);
    $assert($first == $replay, 'shoot create replays identical persisted result');
    $expectCode(static fn() => $shoots->execute($actor, $bearer, $parent, true, new ShootMutationInputDto($createInput->key, 'Другой payload', true, '2026-10-01', null)), 409, 'same shoot idempotency key with another payload conflicts');
    $second = $shoots->execute($actor, $bearer, $parent, true, new ShootMutationInputDto($key(), 'Зима', false, null, null));
    $firstId = new StructureId($first->id);
    $secondId = new StructureId($second->id);
    $page = $list->execute($actor, $bearer, $parent, new StructurePageInputDto(1, 1));
    $page2 = $list->execute($actor, $bearer, $parent, new StructurePageInputDto(2, 1));
    $assert(2 === $page->meta['total'] && 1 === count($page->items) && $page->items[0]->id !== $page2->items[0]->id, 'stable shoot pagination includes two independent shoots');
    $beyond = $list->execute($actor, $bearer, $parent, new StructurePageInputDto(5, 1));
    $assert([] === $beyond->items && 2 === $beyond->meta['total'], 'page past end preserves total');
    $assert(null === $detail->execute($actor, $bearer, $secondId, new StructurePageInputDto())->date, 'shoot date may be unassigned');
    $renamed = $shoots->execute($actor, $bearer, $firstId, false, new ShootMutationInputDto($key(), 'Осень обновлена', false, null, 1));
    $assert('2026-10-01' === $detail->execute($actor, $bearer, $firstId, new StructurePageInputDto())->date, 'omitted PATCH date preserves calendar date');
    $cleared = $shoots->execute($actor, $bearer, $firstId, false, new ShootMutationInputDto($key(), null, true, null, $renamed->revision));
    $assert(null === $detail->execute($actor, $bearer, $firstId, new StructurePageInputDto())->date, 'explicit null clears shoot date');
    $expectCode(static fn() => $shoots->execute($actor, $bearer, $firstId, false, new ShootMutationInputDto($key(), 'Stale', false, null, 1)), 409, 'stale shoot revision rejected');
    $expectCode(static fn() => $shoots->execute($actor, $bearer, new StructureId(Uuid::uuid4()->toString()), true, $createInput), 404, 'unknown institution is rejected');
    $expectCode(static fn() => $shoots->execute($staff['curator'], 'C3Token' . $staff['curator'], $parent, true, $createInput), 403, 'curator cannot mutate shoots');
    $expectCode(static fn() => $detail->execute($staff['curator'], 'C3Token' . $staff['curator'], $firstId, new StructurePageInputDto()), 403, 'ORG08 editor is organizer-only');
    $expectCode(static fn() => $list->execute($staff['emptycurator'], 'C3Token' . $staff['emptycurator'], $parent, new StructurePageInputDto()), 404, 'unassigned curator cannot identify foreign institution');
    $access = $locator->get(InstitutionAccessInterface::class);
    $institutions->execute($actor, $bearer, new InstitutionId($institution->id), new InstitutionMutationInputDto($key(), null, null, 1, true, $staff['curator'], true, $staff['head'], $access->signature(), false));
    foreach (['curator', 'head'] as $role) {
        $users->updateToken($staff[$role], 'C3Token' . $staff[$role], DateTime::createFromTimestamp(time() + 3600));
        $assert(2 === $list->execute($staff[$role], 'C3Token' . $staff[$role], $parent, new StructurePageInputDto())->meta['total'], $role . ' reads own institution shoots');
        $expectCode(static fn() => $list->execute($staff[$role], 'C3Token' . $staff[$role], new StructureId($otherInstitution->id), new StructurePageInputDto()), 404, $role . ' cannot read another institution');
    }
    $createGroupInput = new GroupMutationInputDto($key(), 'Ромашки', 'regular', null, false, null, null, false);
    $group = $groups->execute($actor, $bearer, $firstId, true, $createGroupInput);
    $assert($group == $groups->execute($actor, $bearer, $firstId, true, $createGroupInput), 'group create replays without duplicate');
    $otherGroup = $groups->execute($actor, $bearer, $secondId, true, new GroupMutationInputDto($key(), 'Ромашки', 'regular', null, false, null, null, false));
    $assert($group->id !== $otherGroup->id, 'same group name in different shoots has separate UUID');
    $one = $detail->execute($actor, $bearer, $firstId, new StructurePageInputDto());
    $two = $detail->execute($actor, $bearer, $secondId, new StructurePageInputDto());
    $assert(1 === $one->groups->meta['total'] && $group->id === $one->groups->items[0]->id && $otherGroup->id === $two->groups->items[0]->id, 'groups stay inside their parent shoot');
    $projection = $one->groups->items[0];
    $assert('preparing' === $projection->status && null === $projection->sentAt && null === $projection->closesAt && 'Europe/Moscow' === $projection->timezone, 'new group calendar is unstarted, without invented dates');
    $assert(3 === (int)$connection->query('SELECT COUNT(DISTINCT UF_AGGREGATE_TYPE) C FROM b_hlbd_mf_organization_change WHERE UF_AGGREGATE_ID=1 AND UF_TO_REVISION=1')->fetch()['C'], 'native ID 1 has independent institution, shoot and group history');
    $renamedGroup = $groups->execute($actor, $bearer, new StructureId($group->id), false, new GroupMutationInputDto($key(), 'Новое имя', null, 1, false, null, null, false));
    $assert(2 === $renamedGroup->revision, 'group rename advances revision');
    $expectCode(static fn() => $groups->execute($actor, $bearer, new StructureId($group->id), false, new GroupMutationInputDto($key(), 'Stale', null, 1, false, null, null, false)), 409, 'stale group revision rejected');
    $expectCode(static fn() => $groups->execute($actor, $bearer, new StructureId($group->id), false, new GroupMutationInputDto($key(), 'Changed kind', 'staff', 2, false, null, null, false)), 422, 'ordinary group update cannot change kind');
    $expectCode(static fn() => $groups->execute($actor, $bearer, new StructureId(Uuid::uuid4()->toString()), true, $createGroupInput), 404, 'unknown shoot cannot receive group');
    $nativeShoot = (int)$connection->query("SELECT ID FROM b_hlbd_mf_shoot WHERE UF_PUBLIC_ID='{$first->id}'")->fetch()['ID'];
    $context['createGroup'] = static function() use ($groups, $actor, $bearer, $firstId, $key, $connection): int {
        $created = $groups->execute($actor, $bearer, $firstId, true, new GroupMutationInputDto($key(), 'Изолированная группа', 'regular', null, false, null, null, false));

        return (int)$connection->query("SELECT ID FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID='{$created->id}'")->fetch()['ID'];
    };
    $expect(static fn() => $connection->queryExecute("DELETE FROM b_hlbd_mf_shoot WHERE ID={$nativeShoot}"), 'FK refuses to orphan groups');
    $expect(static fn() => (new Version20260913010001())->down(), 'migration refuses destructive rollback with group data');
    $stage = 'http';
    (require __DIR__ . '/fixtures/c3/http-checks.php')($context);
    $stage = 'access';
    (require __DIR__ . '/fixtures/c3/access-checks.php')($context);
    $stage = 'calendar';
    (require __DIR__ . '/fixtures/c3/calendar-checks.php')($context);
    $stage = 'concurrency';
    (require __DIR__ . '/fixtures/c3/concurrency-checks.php')($context);
    echo json_encode(['status' => 'passed', 'checks' => count($checks), 'details' => $checks, 'runtime' => ['php' => PHP_VERSION, 'bitrix' => $context['fixture']['kernelVersion'], 'mysql' => $sql->server_info]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . ' at ' . $error->getFile() . ':' . $error->getLine() . PHP_EOL);
    for ($previous = $error->getPrevious(); null !== $previous; $previous = $previous->getPrevious()) {
        fwrite(STDERR, 'Caused by ' . $previous::class . ': ' . $previous->getMessage() . PHP_EOL);
    }
    echo json_encode(['status' => 'failed', 'stage' => $stage, 'checks' => count($checks), 'details' => $checks], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
    exit(1);
}
