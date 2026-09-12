<?php

declare(strict_types=1);

use Bitrix\Main\Type\DateTime;
use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Application\Profile\UseCase\GetProfileUseCase;
use Morefoto\Access\Domain\Staff\Enum\PermissionEnum;
use Morefoto\Organization\Application\Structure\Dto\GroupMutationInputDto;
use Morefoto\Organization\Application\Structure\UseCase\SaveGroupUseCase;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\Dto\GroupAssignmentOutputDto;
use Rebit\Share\Contracts\Access\GroupAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Access\Application\Assignment\Service\GroupAccess;
use Rebit\Auth\Domain\User\Repository\UserRepository;

/**
 * Include only from the disposable C3 native fixture after all migrations are applied.
 * Uses the real DI provider, Bitrix transaction, Auth identities/tokens and persisted assignments.
 *
 * @param array{
 *     connection: Connection,
 *     locator: ServiceLocator,
 *     assert: callable(bool, string): void,
 *     users: UserRepository,
 *     staff: array{organizer: int, teacher: int, teacher2: int, inactiveteacher: int, wrongrole: int},
 *     actorBearer: string,
 *     createGroup: callable(): int,
 * } $context
 */
return static function(array $context): void {
    $connection = $context['connection'];
    $locator = $context['locator'];
    $assert = $context['assert'];
    $users = $context['users'];
    $staff = $context['staff'];
    $actor = $staff['organizer'];
    $bearer = $context['actorBearer'];
    $teacher = $staff['teacher'];
    $teacher2 = $staff['teacher2'];
    $group = $context['createGroup']();
    $otherGroup = $context['createGroup']();
    /** @var GroupAccessInterface $access */
    $access = $locator->get(GroupAccessInterface::class);
    /** @var TokenResolverInterface $tokens */
    $tokens = $locator->get(TokenResolverInterface::class);
    $assert($access instanceof GroupAccess, 'C3 Access: native DI resolves real GroupAccess');
    $assert($access === $locator->get(GroupAccessInterface::class), 'C3 Access: GroupAccess is a stateless singleton');
    $issue = static function(int $userId) use ($users): string {
        $token = bin2hex(random_bytes(32));
        $users->updateToken($userId, $token, new DateTime(gmdate('Y-m-d H:i:s', time() + 3600), 'Y-m-d H:i:s'));

        return $token;
    };
    $expect = static function(callable $operation, int $status, string $label) use ($assert): void {
        try {
            $operation();
        } catch (HttpException $exception) {
            $assert($status === $exception->getCode(), $label);

            return;
        }
        $assert(false, $label . ': expected HttpException');
    };
    $snapshot = static function() use ($connection, $group, $teacher, $teacher2): array {
        return [
            'slot' => $connection->query("SELECT UF_USER_ID FROM b_hlbd_mf_group_assignment WHERE UF_GROUP_ID={$group}")->fetch(),
            'group' => $connection->query("SELECT UF_PUBLIC_ID,UF_NAME,UF_REVISION FROM b_hlbd_mf_group WHERE ID={$group}")->fetch(),
            'organizationHistory' => $connection->query("SELECT COUNT(*) N FROM b_hlbd_mf_organization_change WHERE UF_AGGREGATE_TYPE='group' AND UF_AGGREGATE_ID={$group}")->fetch(),
            'operations' => $connection->query('SELECT COUNT(*) N FROM mf_institution_operation')->fetch(),
            'state' => $connection->query('SELECT assignments_revision FROM mf_access_state WHERE id=1')->fetch(),
            'profiles' => $connection->query("SELECT UF_USER_ID,UF_REVISION,UF_ACCESS_REVISION FROM b_hlbd_mf_staff_profile WHERE UF_USER_ID IN ({$teacher},{$teacher2}) ORDER BY UF_USER_ID")->fetchAll(),
            'history' => $connection->query('SELECT COUNT(*) AS N FROM b_hlbd_mf_access_change')->fetch(),
            'auth' => $connection->query("SELECT VALUE_ID,UF_TOKEN,CAST(UF_TOKEN_EXPIRES_AT AS CHAR) AS EXPIRES_AT FROM b_uts_user WHERE VALUE_ID IN ({$teacher},{$teacher2}) ORDER BY VALUE_ID")->fetchAll(),
        ];
    };
    // The caller owns the transaction and Organization ancestry locks, as in the real UseCase.
    $run = static function(int $groupId, array $participants, callable $operation) use ($connection, $access, $actor, $bearer): mixed {
        $connection->startTransaction();
        try {
            $access->lockState();
            $parent = $connection->query("SELECT g.UF_SHOOT_ID,s.UF_INSTITUTION_ID FROM b_hlbd_mf_group g INNER JOIN b_hlbd_mf_shoot s ON s.ID=g.UF_SHOOT_ID WHERE g.ID={$groupId}")->fetch();
            if (false === $parent) {
                throw new RuntimeException('Native Access fixture group is missing.');
            }
            $institutionId = (int)$parent['UF_INSTITUTION_ID'];
            $shootId = (int)$parent['UF_SHOOT_ID'];
            $connection->query("SELECT ID FROM b_hlbd_mf_institution WHERE ID={$institutionId} FOR UPDATE");
            $connection->query("SELECT ID FROM b_hlbd_mf_shoot WHERE ID={$shootId} FOR UPDATE");
            $connection->query("SELECT ID FROM b_hlbd_mf_group WHERE ID={$groupId} FOR UPDATE");
            $access->lockParticipants($actor, $bearer, $participants);
            $result = $operation();
            $connection->commitTransaction();

            return $result;
        } catch (Throwable $exception) {
            $connection->rollbackTransaction();
            throw $exception;
        }
    };
    $replace = static fn(?int $id, ?string $signature, bool $confirmed, ?string $reason): string => $access->replace($group, new GroupAssignmentOutputDto($id), $signature, $confirmed, $actor, Uuid::uuid4()->toString(), $reason);
    $before = $snapshot();
    $expect(static fn(): mixed => $run($group, [$teacher], static fn(): string => $replace($teacher, null, false, null)), 409, 'C3 Access: initial teacher assignment requires current signature');
    $assert($before === $snapshot(), 'C3 Access: rejected initial assignment leaves storage untouched');

    $teacherToken = $issue($teacher);
    $signature = $access->signature();
    $next = $run($group, [$teacher], static fn(): string => $replace($teacher, $signature, false, null));
    $assert($signature !== $next, 'C3 Access: assignment advances global signature');
    $assert($teacher === $access->assignments([$group])[$group]->teacherId, 'C3 Access: teacher assignment persists through real provider');
    $expect(static fn(): int => $tokens->resolveUserId($teacherToken), 401, 'C3 Access: initial assignment revokes prior teacher token');
    $profile = $locator->get(GetProfileUseCase::class)->execute($teacher);
    $assert(in_array('group.read', $profile->permissions, true), 'C3 Access: me permissions read the real teacher group scope');
    $locator->get(StaffAuthorization::class)->assertCan($teacher, PermissionEnum::GROUP_READ, groupId: $group);
    $assert(true, 'C3 Access: teacher reads assigned group');
    $expect(static fn(): mixed => $locator->get(StaffAuthorization::class)->assertCan($teacher, PermissionEnum::GROUP_READ, groupId: $otherGroup), 404, 'C3 Access: teacher cannot read foreign group');

    $teacherToken = $issue($teacher);
    $teacher2Token = $issue($teacher2);
    $before = $snapshot();
    $run($group, [$teacher], static fn(): string => $replace($teacher, 'obsolete', false, null));
    $assert($before === $snapshot(), 'C3 Access: same teacher is a storage no-op');
    $assert($teacher === $tokens->resolveUserId($teacherToken), 'C3 Access: same teacher does not revoke a later session');
    $expect(static fn(): mixed => $run($group, [$teacher, $teacher2], static fn(): string => $replace($teacher2, $access->signature(), false, 'Replacement requested')), 409, 'C3 Access: occupied replacement requires confirmation');
    $expect(static fn(): mixed => $run($group, [$teacher, $teacher2], static fn(): string => $replace($teacher2, $access->signature(), true, '   ')), 422, 'C3 Access: occupied replacement requires an explicit reason');
    $expect(static fn(): mixed => $run($group, [$teacher, $teacher2], static fn(): string => $replace($teacher2, 'obsolete', true, 'Replacement requested')), 409, 'C3 Access: confirmation cannot bypass signature comparison');
    $assert($before === $snapshot(), 'C3 Access: invalid replacements leave assignment versions and history intact');
    foreach (['inactiveteacher', 'wrongrole'] as $role) {
        $invalid = $staff[$role];
        $expect(static fn(): mixed => $run($group, [$teacher, $invalid], static fn(): string => $replace($invalid, $access->signature(), true, 'Invalid assignee check')), 422, 'C3 Access: rejects ' . $role);
    }
    $assert($before === $snapshot(), 'C3 Access: invalid assignees do not partially change assignments');

    try {
        $run($group, [$teacher, $teacher2], static function() use ($replace, $access): void {
            $replace($teacher2, $access->signature(), true, 'Rollback check');
            throw new RuntimeException('c3-access-rollback');
        });
        $assert(false, 'C3 Access: injected rollback must throw');
    } catch (RuntimeException $exception) {
        $assert('c3-access-rollback' === $exception->getMessage(), 'C3 Access: injected failure exits the transaction');
    }
    $assert($before === $snapshot(), 'C3 Access: outer rollback restores assignment versions and audit history');
    $assert($teacher === $tokens->resolveUserId($teacherToken) && $teacher2 === $tokens->resolveUserId($teacher2Token), 'C3 Access: outer rollback restores both Auth sessions');

    // Force a failure inside the real Auth revocation, after Access has started changing data.
    $trigger = 'mf_c3_access_revoke_failure';
    $connection->queryExecute('SET @mf_c3_auth_revoke_triggered=0');
    $connection->queryExecute("CREATE TRIGGER {$trigger} BEFORE UPDATE ON b_uts_user FOR EACH ROW BEGIN IF NEW.VALUE_ID={$teacher} AND COALESCE(NEW.UF_TOKEN,'')='' THEN SET @mf_c3_auth_revoke_triggered=1; SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='C3 injected Auth revoke failure'; END IF; END");
    try {
        $fault = null;
        try {
            $locator->get(SaveGroupUseCase::class)->execute($actor, $bearer, new StructureId((string)$before['group']['UF_PUBLIC_ID']), false, new GroupMutationInputDto(
                key: bin2hex(random_bytes(16)),
                name: 'This group rename must roll back',
                groupKind: null,
                revision: (int)$before['group']['UF_REVISION'],
                teacherProvided: true,
                teacherId: $teacher2,
                assignmentSignature: $access->signature(),
                replaceAssignments: true,
                reason: 'Auth revoke failure must roll back',
            ));
        } catch (Throwable $exception) {
            $fault = $exception;
        }
        // A connection-local marker proves the real trigger ran even if nested rollback masks its SIGNAL.
        $triggered = null !== $fault && 1 === (int)$connection->query('SELECT COALESCE(@mf_c3_auth_revoke_triggered,0) AS TRIGGERED')->fetch()['TRIGGERED'];
        if (!$triggered) {
            fwrite(STDERR, 'C3 Auth revoke fault diagnostic: ' . (null === $fault ? 'no exception was thrown' : $fault::class) . PHP_EOL);
            for ($error = $fault; null !== $error; $error = $error->getPrevious()) {
                fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
            }
        }
        $assert($triggered, 'C3 Access: native trigger fails inside actual Auth session revocation');
        $level = (new ReflectionProperty($connection, 'transactionLevel'))->getValue($connection);
        if (0 !== $level && null !== $fault) {
            fwrite(STDERR, 'C3 Auth revoke left transaction level ' . $level . ': ' . $fault::class . ': ' . $fault->getMessage() . PHP_EOL);
        }
        $assert(0 === $level, 'C3 Access: failed Auth revocation leaves no nested or outer transaction');
        $assert($before === $snapshot(), 'C3 Access: failure inside Auth restores assignment state profiles history and tokens');
        $assert($teacher === $tokens->resolveUserId($teacherToken) && $teacher2 === $tokens->resolveUserId($teacher2Token), 'C3 Access: both former tokens stay valid after failed revocation');
    } finally {
        // Cleanup a failed implementation as well, before DROP TRIGGER could implicitly commit it.
        $transactionLevel = new ReflectionProperty($connection, 'transactionLevel');
        while (0 < $transactionLevel->getValue($connection)) {
            $connection->rollbackTransaction();
        }
        $connection->queryExecute("DROP TRIGGER {$trigger}");
    }

    $operationId = Uuid::uuid4()->toString();
    $run($group, [$teacher, $teacher2], static fn(): string => $access->replace($group, new GroupAssignmentOutputDto($teacher2), $access->signature(), true, $actor, $operationId, 'Teaching staff changed'));
    $expect(static fn(): int => $tokens->resolveUserId($teacherToken), 401, 'C3 Access: replacement revokes former teacher session');
    $expect(static fn(): int => $tokens->resolveUserId($teacher2Token), 401, 'C3 Access: replacement revokes new teacher session');
    $after = $snapshot();
    $assert($teacher2 === (int)$after['slot']['UF_USER_ID'], 'C3 Access: confirmed replacement persists new teacher');
    foreach ($after['profiles'] as $index => $row) {
        $assert((int)$row['UF_REVISION'] === (int)$before['profiles'][$index]['UF_REVISION'] + 1 && (int)$row['UF_ACCESS_REVISION'] === (int)$before['profiles'][$index]['UF_ACCESS_REVISION'] + 1, 'C3 Access: replacement advances both versions of user ' . $row['UF_USER_ID']);
    }
    $history = $connection->query("SELECT UF_DELTA FROM b_hlbd_mf_access_change WHERE UF_OPERATION_ID='{$operationId}' ORDER BY UF_AGGREGATE_ID")->fetchAll();
    $assert(2 === count($history), 'C3 Access: one audit change per affected teacher');
    foreach ($history as $row) {
        $delta = json_decode((string)$row['UF_DELTA'], true, 512, JSON_THROW_ON_ERROR);
        $assert('Teaching staff changed' === $delta['reason'] && $teacher === $delta['fromUserId'] && $teacher2 === $delta['toUserId'], 'C3 Access: audit preserves explicit replacement reason and both teachers');
    }
    $expect(static fn(): mixed => $locator->get(StaffAuthorization::class)->assertCan($teacher, PermissionEnum::GROUP_READ, groupId: $group), 404, 'C3 Access: former teacher loses this group immediately');
    $teacher2Token = $issue($teacher2);
    $run($group, [$teacher2], static fn(): string => $replace(null, $access->signature(), true, 'Group teacher removed'));
    $assert([] === $access->assignments([$group]), 'C3 Access: removal leaves no placeholder assignment row');
    $expect(static fn(): int => $tokens->resolveUserId($teacher2Token), 401, 'C3 Access: removal revokes former teacher session');
};
