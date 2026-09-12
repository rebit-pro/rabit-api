<?php

declare(strict_types=1);

use Bitrix\Main\Type\DateTime;
use Morefoto\Organization\Application\Calendar\UseCase\ChangeGroupCalendarUseCase;
use Morefoto\Organization\Application\Structure\Dto\GroupMutationInputDto;
use Morefoto\Organization\Application\Structure\Dto\ShootMutationInputDto;
use Morefoto\Organization\Application\Structure\UseCase\SaveGroupUseCase;
use Morefoto\Organization\Application\Structure\UseCase\SaveShootUseCase;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DI\ServiceLocator;
use Rebit\Auth\Domain\User\Repository\UserRepository;

/**
 * Native multi-process verification; no timing assumption substitutes for MySQL lock evidence.
 *
 * @param array{
 *     connection: Connection,
 *     sql: mysqli,
 *     locator: ServiceLocator,
 *     fixture: array{documentRoot: string},
 *     assert: callable(bool, string): void,
 *     users: UserRepository,
 *     staff: array{organizer: int, teacher: int, teacher2: int},
 *     actorBearer: string,
 *     createGroup: callable(): int,
 * } $context
 */
return static function(array $context): void {
    $connection = $context['connection'];
    $sql = $context['sql'];
    $locator = $context['locator'];
    $assert = $context['assert'];
    $fixture = $context['fixture'];
    $actor = $context['staff']['organizer'];
    $bearer = $context['actorBearer'];
    $key = static fn(): string => bin2hex(random_bytes(16));
    $directory = $fixture['documentRoot'] . '/c3-concurrency-' . bin2hex(random_bytes(4));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Cannot create isolated C3 worker directory.');
    }
    $processes = [];
    $wait = static function(callable $condition, string $label): void {
        $deadline = microtime(true) + 20;
        do {
            clearstatcache();
            if ($condition()) {
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('Timed out: ' . $label);
    };
    $spawn = static function(array $job) use ($directory, $fixture, &$processes): array {
        $prefix = $directory . '/worker-' . count($processes);
        $job['ready'] = $prefix . '.ready';
        $job['result'] = $prefix . '.result.json';
        $job['log'] = $prefix . '.log';
        file_put_contents($prefix . '.json', json_encode($job, JSON_THROW_ON_ERROR));
        $process = proc_open(
            [PHP_BINARY, '-d', 'short_open_tag=1', '-d', 'date.timezone=UTC', __DIR__ . '/worker.php', $fixture['documentRoot'], $prefix . '.json'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $job['log'], 'a'], 2 => ['file', $job['log'], 'a']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start independent C3 worker.');
        }
        $processes[] = $process;
        $job['process'] = $process;

        return $job;
    };
    $ready = static function(array $job) use ($wait): int {
        $wait(static function() use ($job): bool {
            if (is_file($job['ready'])) {
                return true;
            }
            if (!proc_get_status($job['process'])['running']) {
                throw new RuntimeException('C3 worker failed before connection readiness: ' . (string)file_get_contents($job['log']));
            }

            return false;
        }, 'C3 worker opens native MySQL connection');

        return (int)file_get_contents($job['ready']);
    };
    $finish = static function(array $job) use ($wait, $assert): array {
        $wait(static function() use ($job): bool {
            if (is_file($job['result'])) {
                return true;
            }
            if (!proc_get_status($job['process'])['running']) {
                throw new RuntimeException('C3 worker exited without a result: ' . (string)file_get_contents($job['log']));
            }

            return false;
        }, 'C3 worker writes committed result');
        proc_close($job['process']);
        $result = json_decode((string)file_get_contents($job['result']), true, 32, JSON_THROW_ON_ERROR);
        $assert(0 === $result['transactionLevel'], 'C3 concurrency: worker releases its entire native transaction');

        return $result;
    };
    $blocked = static function(int $id) use ($sql): bool {
        return 0 < (int)$sql->query("SELECT COUNT(*) C FROM performance_schema.data_lock_waits w INNER JOIN performance_schema.threads t ON t.THREAD_ID=w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID={$id}")->fetch_assoc()['C'];
    };
    $race = static function(array $first, array $second, string $label) use ($connection, $spawn, $ready, $wait, $blocked, $finish, $assert): array {
        $connection->startTransaction();
        $connection->query('SELECT assignments_revision FROM mf_access_state WHERE id=1 FOR UPDATE')->fetch();
        try {
            $a = $spawn($first);
            $b = $spawn($second);
            $idA = $ready($a);
            $idB = $ready($b);
            $wait(static fn(): bool => $blocked($idA) && $blocked($idB), $label . ': both workers wait for a real AccessState row lock');
            $assert($idA !== $idB, 'C3 concurrency: ' . $label . ' uses separate blocked MySQL connections');
        } finally {
            $connection->commitTransaction();
        }

        return [$finish($a), $finish($b)];
    };
    $single = static function(array $job) use ($spawn, $ready, $finish): array {
        $worker = $spawn($job);
        $ready($worker);

        return $finish($worker);
    };
    $base = ['actor' => $actor, 'bearer' => $bearer];
    try {
        $anchor = $context['createGroup']();
        $ancestry = $connection->query("SELECT i.ID,i.UF_PUBLIC_ID FROM b_hlbd_mf_group g INNER JOIN b_hlbd_mf_shoot s ON s.ID=g.UF_SHOOT_ID INNER JOIN b_hlbd_mf_institution i ON i.ID=s.UF_INSTITUTION_ID WHERE g.ID={$anchor}")->fetch();
        $institution = (string)$ancestry['UF_PUBLIC_ID'];
        $institutionNative = (int)$ancestry['ID'];
        $createdShoot = $locator->get(SaveShootUseCase::class)->execute($actor, $bearer, new StructureId($institution), true, new ShootMutationInputDto($key(), 'Concurrency shoot', true, null, null));
        $shootId = $createdShoot->id;
        $shootNative = (int)$connection->query("SELECT ID FROM b_hlbd_mf_shoot WHERE UF_PUBLIC_ID='{$shootId}'")->fetch()['ID'];
        $createdGroup = $locator->get(SaveGroupUseCase::class)->execute($actor, $bearer, new StructureId($shootId), true, new GroupMutationInputDto($key(), 'Concurrency group', 'regular', null, false, null, null, false));
        $groupId = $createdGroup->id;
        $groupNative = (int)$connection->query("SELECT ID FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID='{$groupId}'")->fetch()['ID'];

        foreach (['shoot' => [$shootId, $shootNative], 'group' => [$groupId, $groupNative]] as $kind => [$id, $native]) {
            $a = $base + ['action' => $kind, 'target' => $id, 'key' => $key(), 'revision' => 1, 'name' => 'Concurrent first'];
            $b = $base + ['action' => $kind, 'target' => $id, 'key' => $key(), 'revision' => 1, 'name' => 'Concurrent second'];
            $outcomes = $race($a, $b, $kind . ' PATCH');
            $statuses = array_column($outcomes, 'status');
            sort($statuses);
            $assert([200, 409] === $statuses, 'C3 concurrency: same ' . $kind . ' revision has exactly one winner and one conflict');
            $revision = (int)$connection->query("SELECT UF_REVISION FROM b_hlbd_mf_{$kind} WHERE ID={$native}")->fetch()['UF_REVISION'];
            $assert(2 === $revision, 'C3 concurrency: competing ' . $kind . ' PATCH advances revision once');
            $history = (int)$connection->query("SELECT COUNT(*) N FROM b_hlbd_mf_organization_change WHERE UF_AGGREGATE_TYPE='{$kind}' AND UF_AGGREGATE_ID={$native} AND UF_TO_REVISION=2")->fetch()['N'];
            $assert(1 === $history, 'C3 concurrency: competing ' . $kind . ' PATCH records exactly one history change');
        }
        foreach (['shoot' => [$institution, 'UF_INSTITUTION_ID', $institutionNative], 'group' => [$shootId, 'UF_SHOOT_ID', $shootNative]] as $kind => [$target, $parentColumn, $parentId]) {
            $before = (int)$connection->query("SELECT COUNT(*) N FROM b_hlbd_mf_{$kind} WHERE {$parentColumn}={$parentId}")->fetch()['N'];
            $job = $base + ['action' => $kind, 'create' => true, 'target' => $target, 'key' => $key(), 'name' => 'Same-key creation', 'groupKind' => 'regular'];
            $outcomes = $race($job, $job, $kind . ' idempotent POST');
            $assert([201, 201] === array_column($outcomes, 'status') && $outcomes[0]['result'] === $outcomes[1]['result'], 'C3 concurrency: same-key ' . $kind . ' creates return the same persisted result');
            $after = (int)$connection->query("SELECT COUNT(*) N FROM b_hlbd_mf_{$kind} WHERE {$parentColumn}={$parentId}")->fetch()['N'];
            $assert($before + 1 === $after, 'C3 concurrency: concurrent duplicate ' . $kind . ' create persists one aggregate');
        }

        $calendarGroup = $context['createGroup']();
        $row = $connection->query("SELECT UF_PUBLIC_ID,UF_REVISION FROM b_hlbd_mf_group WHERE ID={$calendarGroup}")->fetch();
        $calendarId = (string)$row['UF_PUBLIC_ID'];
        $commands = $locator->get(ChangeGroupCalendarUseCase::class);
        $sent = $commands->confirmLinkSent(new CalendarCommandInputDto($calendarId, $actor, (int)$row['UF_REVISION'], $key(), 'Concurrency calendar start'), $bearer);
        $firstClose = (new DateTimeImmutable($sent->calendar->closesAt))->add(new DateInterval('P1D'))->format(DateTimeInterface::ATOM);
        $secondClose = (new DateTimeImmutable($sent->calendar->closesAt))->add(new DateInterval('P2D'))->format(DateTimeInterface::ATOM);
        $common = $base + ['action' => 'calendarExtend', 'target' => $calendarId, 'revision' => $sent->revision, 'reason' => 'Concurrent calendar extension'];
        $outcomes = $race($common + ['key' => $key(), 'closesAt' => $firstClose], $common + ['key' => $key(), 'closesAt' => $secondClose], 'calendar extension');
        $statuses = array_column($outcomes, 'status');
        sort($statuses);
        $assert([200, 409] === $statuses, 'C3 concurrency: competing calendar extensions have one winner and one version conflict');
        $saved = $connection->query("SELECT UF_REVISION,DATE_FORMAT(UF_CLOSES_AT,'%Y-%m-%d %H:%i:%s') CLOSES_AT FROM b_hlbd_mf_group WHERE ID={$calendarGroup}")->fetch();
        $winner = 200 === $outcomes[0]['status'] ? $outcomes[0]['result'] : $outcomes[1]['result'];
        $assert($sent->revision + 1 === (int)$saved['UF_REVISION'] && (new DateTimeImmutable($winner['calendar']['closesAt']))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') === $saved['CLOSES_AT'], 'C3 concurrency: only the winning calendar deadline is stored');

        // The replay runs in a new PHP process after both teachers obtained newer sessions.
        $replayGroup = $context['createGroup']();
        $row = $connection->query("SELECT UF_PUBLIC_ID,UF_REVISION FROM b_hlbd_mf_group WHERE ID={$replayGroup}")->fetch();
        $replayId = (string)$row['UF_PUBLIC_ID'];
        $teacher = $context['staff']['teacher'];
        $teacher2 = $context['staff']['teacher2'];
        $access = $locator->get(GroupAccessInterface::class);
        $assigned = $locator->get(SaveGroupUseCase::class)->execute($actor, $bearer, new StructureId($replayId), false, new GroupMutationInputDto($key(), null, null, (int)$row['UF_REVISION'], true, $teacher, $access->signature(), false));
        $job = $base + ['action' => 'group', 'target' => $replayId, 'key' => $key(), 'revision' => $assigned->revision, 'teacherProvided' => true, 'teacherId' => $teacher2, 'signature' => $access->signature(), 'replaceAssignments' => true, 'reason' => 'Confirmed reassignment for persisted replay'];
        $initial = $single($job);
        $assert(200 === $initial['status'], 'C3 concurrency: confirmed reassignment commits in an independent process');
        $newTokens = [];
        foreach ([$teacher, $teacher2] as $id) {
            $newTokens[$id] = bin2hex(random_bytes(32));
            $context['users']->updateToken($id, $newTokens[$id], DateTime::createFromTimestamp(time() + 3600));
        }
        $snapshot = static function() use ($connection, $replayGroup, $teacher, $teacher2): array {
            return [
                'group' => $connection->query("SELECT UF_REVISION FROM b_hlbd_mf_group WHERE ID={$replayGroup}")->fetch(),
                'slot' => $connection->query("SELECT UF_USER_ID FROM b_hlbd_mf_group_assignment WHERE UF_GROUP_ID={$replayGroup}")->fetch(),
                'profiles' => $connection->query("SELECT UF_USER_ID,UF_REVISION,UF_ACCESS_REVISION FROM b_hlbd_mf_staff_profile WHERE UF_USER_ID IN ({$teacher},{$teacher2}) ORDER BY UF_USER_ID")->fetchAll(),
                'state' => $connection->query('SELECT assignments_revision FROM mf_access_state WHERE id=1')->fetch(),
                'accessHistory' => $connection->query('SELECT COUNT(*) N FROM b_hlbd_mf_access_change')->fetch(),
                'structureHistory' => $connection->query("SELECT COUNT(*) N FROM b_hlbd_mf_organization_change WHERE UF_AGGREGATE_TYPE='group' AND UF_AGGREGATE_ID={$replayGroup}")->fetch(),
                'operations' => $connection->query('SELECT COUNT(*) N FROM mf_institution_operation')->fetch(),
            ];
        };
        $before = $snapshot();
        $replay = $single($job);
        $assert(200 === $replay['status'] && $initial['result'] === $replay['result'], 'C3 concurrency: a fresh process replays the original confirmed result');
        $assert($before === $snapshot(), 'C3 concurrency: process replay preserves aggregate access history and operation counters');
        $tokens = $locator->get(TokenResolverInterface::class);
        foreach ($newTokens as $id => $token) {
            $assert($id === $tokens->resolveUserId($token), 'C3 concurrency: process replay preserves newer session of user ' . $id);
        }
    } finally {
        foreach ($processes as $process) {
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                }
                proc_close($process);
            }
        }
    }
};
