<?php

declare(strict_types=1);

/**
 * Run through run-w02-auth-integration.sh. The database must be fresh and isolated.
 * Uses real Bitrix, MySQL, CUser, UF, ORM, migrations and production repositories.
 * Test doubles: deterministic Clock and CAPTCHA/mail/token-generation fault adapters.
 * No network delivery, full prolog, frontend or production configuration is exercised.
 */

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Rebit\Auth\Application\Auth\Contract\CaptchaVerifierInterface;
use Rebit\Auth\Application\Auth\Contract\ClockInterface;
use Rebit\Auth\Application\Auth\Contract\RegistrationConfirmationMailerInterface;
use Rebit\Auth\Application\Auth\Dto\Request\ConfirmRegistrationRequestDto;
use Rebit\Auth\Application\Auth\Dto\Request\LoginCaptchaRequestDto;
use Rebit\Auth\Application\Auth\Dto\Request\LoginRequestDto;
use Rebit\Auth\Application\Auth\Dto\Request\RequestRegistrationCodeRequestDto;
use Rebit\Auth\Application\Auth\UseCase\ConfirmRegistrationUseCase;
use Rebit\Auth\Application\Auth\UseCase\LoginUseCase;
use Rebit\Auth\Application\Auth\UseCase\LogoutUseCase;
use Rebit\Auth\Application\Auth\UseCase\RequestRegistrationCodeUseCase;
use Rebit\Auth\Domain\Registration\Repository\RegistrationConfirmationRepository;
use Rebit\Auth\Domain\Registration\Service\RegistrationCodeGenerator;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Auth\Infrastructure\Adapter\BitrixAuthTransaction;
use Rebit\Auth\Infrastructure\Adapter\TokenGenerator;
use Rebit\Auth\Infrastructure\Adapter\TokenResolver;
use Rebit\Share\Shared\Exception\HttpException;
use Bitrix\Main\EventManager;
use Sprint\Migration\Version20260323120001;
use Sprint\Migration\Version20260326120008;
use Sprint\Migration\Version20260911120001;
use Bitrix\Main\Event;

$stage = 'bootstrap';
$checks = [];
$assert = static function(bool $condition, string $name) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Check failed: ' . $name);
    }
    $checks[] = $name;
};
$expectHttp = static function(callable $operation, int $status, string $name) use ($assert): void {
    try {
        $operation();
    } catch (HttpException $exception) {
        $assert($status === $exception->getCode(), $name);

        return;
    }
    throw new RuntimeException('Expected HTTP rejection: ' . $name);
};

try {
    $fixture = require __DIR__ . '/fixtures/w02/bootstrap.php';
    $sql = $fixture['connection'];
    $stage = 'migrations';
    require_once '/app/public/local/php_interface/migrations.foundation/Version20260323120001.php';
    require_once '/app/public/local/php_interface/migrations.foundation/Version20260326120008.php';
    require_once '/app/public/local/php_interface/migrations.foundation/Version20260911120001.php';
    // Sprint prints migration diagnostics; the shell runner routes these to stderr.
    ob_start();
    try {
        (new Version20260323120001())->up();
        (new Version20260326120008())->up();
        // Existing disabled identity predates W02. Only synthetic fixture data is inserted.
        $sql->query("INSERT INTO b_user (LOGIN, PASSWORD, EMAIL, ACTIVE, DATE_REGISTER) VALUES ('legacy@example.invalid', 'fixture-unused', 'legacy@example.invalid', 'N', NOW())");
        $legacyId = (int)$sql->insert_id;
        $migration = new Version20260911120001();
        $migration->up();
        $migration->up();
    } finally {
        ob_end_clean();
    }
    $type = $sql->query("SELECT USER_TYPE_ID FROM b_user_field WHERE ENTITY_ID = 'USER' AND FIELD_NAME = 'UF_TOKEN_EXPIRES_AT'")->fetch_assoc();
    $assert('string' === $type['USER_TYPE_ID'], 'legacy expiry UF remains string');
    $count = $sql->query("SELECT COUNT(*) AS C FROM b_user_field WHERE ENTITY_ID = 'USER' AND FIELD_NAME = 'UF_AUTH_REGISTRATION_PENDING' AND USER_TYPE_ID = 'boolean'")->fetch_assoc();
    $assert(1 === (int)$count['C'], 'pending migration up is idempotent');
    $count = $sql->query("SELECT COUNT(*) AS C FROM b_module_to_module WHERE FROM_MODULE_ID = 'main' AND MESSAGE_ID = 'OnBeforeUserUpdate' AND TO_MODULE_ID = 'rebit.auth'")->fetch_assoc();
    $assert(1 === (int)$count['C'], 'persistent compatible hook registered once');

    $clock = new class implements ClockInterface {
        public int $timestamp = 1900000000;

        public function now(): int
        {
            return $this->timestamp;
        }
    };
    $repository = new UserRepository();
    $resolver = new TokenResolver($repository, $clock);
    $logout = new LogoutUseCase($repository);
    $transaction = new BitrixAuthTransaction();
    $confirmationRepository = new RegistrationConfirmationRepository($clock);
    $legacy = $repository->findById($legacyId);
    $assert(null !== $legacy && !$legacy->isActive && !$legacy->isPendingRegistration, 'legacy inactive identity is never backfilled to pending');

    $stage = 'real-cuser-uf-roundtrip';
    $writer = new CUser();
    $userId = $writer->Add([
        'LOGIN' => 'w02@example.invalid', 'EMAIL' => 'w02@example.invalid', 'NAME' => 'W02 fixture',
        'PASSWORD' => 'W02-fixture-password!42', 'CONFIRM_PASSWORD' => 'W02-fixture-password!42', 'ACTIVE' => 'Y',
    ]);
    $assert(false !== $userId, 'real CUser Add');
    $userId = (int)$userId;
    $token = 'W02AbCd-FirstToken';
    $repository->updateToken($userId, $token, DateTime::createFromTimestamp($clock->now() + 60));
    $row = UserTable::query()->setSelect(['ID', 'UF_TOKEN', 'UF_TOKEN_EXPIRES_AT'])->where('ID', $userId)->exec()->fetch();
    $assert(false !== $row && is_string($row['UF_TOKEN_EXPIRES_AT']), 'real UserTable ORM returns the string UF');
    $assert(gmdate('Y-m-d\TH:i:s\Z', $clock->now() + 60) === $row['UF_TOKEN_EXPIRES_AT'], 'CUser writes canonical UTC expiration');
    $assert($userId === $resolver->resolveUserId($token), 'valid token resolves through real repository');
    $expectHttp(static fn(): int => $resolver->resolveUserId(strtolower($token)), 401, 'token lookup is case sensitive despite database collation');

    $stage = 'persisted-expiration-boundaries';
    $expiryWrite = $sql->prepare('UPDATE b_uts_user SET UF_TOKEN_EXPIRES_AT = ? WHERE VALUE_ID = ?');
    foreach ([
        'legacy SQL' => date('Y-m-d H:i:s', $clock->now() + 60),
        'legacy localized' => date('d.m.Y H:i:s', $clock->now() + 60),
    ] as $label => $expiry) {
        $expiryWrite->bind_param('si', $expiry, $userId);
        $expiryWrite->execute();
        $assert($userId === $resolver->resolveUserId($token), $label . ' stored expiration accepted');
    }
    foreach ([
        'null' => null, 'empty' => '', 'malformed' => 'not-a-date',
        'normalized invalid date' => '2030-02-30 12:00:00',
        'expired' => gmdate('Y-m-d\TH:i:s\Z', $clock->now() - 1),
        'exactly now' => gmdate('Y-m-d\TH:i:s\Z', $clock->now()),
    ] as $label => $expiry) {
        $expiryWrite->bind_param('si', $expiry, $userId);
        $expiryWrite->execute();
        $expectHttp(static fn(): int => $resolver->resolveUserId($token), 401, $label . ' stored expiration rejected');
    }
    $repository->updateToken($userId, $token, DateTime::createFromTimestamp($clock->now() + 1));
    $assert($userId === $resolver->resolveUserId($token), 'expiration one second after now accepted');

    $stage = 'login-logout-and-replacement';
    $captcha = new class implements CaptchaVerifierInterface {
        public function verify(?LoginCaptchaRequestDto $captcha): void {}
    };
    $login = new LoginUseCase($repository, new TokenGenerator(), $captcha, 24, $clock);
    $credentials = new LoginRequestDto('w02@example.invalid', 'W02-fixture-password!42');
    $first = $login->execute($credentials);
    $assert($userId === $resolver->resolveUserId($first->token), 'real login token resolves');
    $assert($clock->now() + 86400 === (new DateTimeImmutable($first->expiresAt))->getTimestamp(), 'login expiration uses injected clock');
    $second = $login->execute($credentials);
    $expectHttp(static fn(): int => $resolver->resolveUserId($first->token), 401, 'new login invalidates previous token');
    $logout->execute($userId, $first->token);
    $assert($userId === $resolver->resolveUserId($second->token), 'stale logout cannot revoke replacement token');
    $logout->execute($userId, strtoupper($second->token));
    $assert($userId === $resolver->resolveUserId($second->token), 'case-altered logout cannot revoke current token');
    $logout->execute($userId, $second->token);
    $expectHttp(static fn(): int => $resolver->resolveUserId($second->token), 401, 'logout revokes current token');
    $row = UserTable::query()->setSelect(['UF_TOKEN', 'UF_TOKEN_EXPIRES_AT'])->where('ID', $userId)->exec()->fetch();
    $assert('' === $row['UF_TOKEN'] && null === $row['UF_TOKEN_EXPIRES_AT'], 'logout clears token and expiration storage');

    $stage = 'pending-and-administrative-disable';
    $pendingId = $repository->createInactiveUser('pending@example.invalid', 'W02-fixture-password!42', 'Pending');
    $pending = $repository->findById($pendingId);
    $assert(null !== $pending && !$pending->isActive && $pending->isPendingRegistration, 'new pending user is inactive and explicitly marked');
    $repository->updateToken($pendingId, 'W02-pending-token', DateTime::createFromTimestamp($clock->now() + 60));
    $expectHttp(static fn(): int => $resolver->resolveUserId('W02-pending-token'), 401, 'inactive pending token rejected');
    $repository->updateInactiveCredentials($pendingId, 'W02-new-password!42', 'Resent');
    $assert(true === $repository->findById($pendingId)?->isPendingRegistration, 'resend credential update preserves pending marker');
    $assert($writer->Update($pendingId, ['ACTIVE' => 'N']), 'real CUser administrative disable');
    $pending = $repository->findById($pendingId);
    $assert(null !== $pending && !$pending->isActive && !$pending->isPendingRegistration, 'persisted OnBeforeUserUpdate hook revokes pending');
    $row = UserTable::query()->setSelect(['UF_TOKEN', 'UF_TOKEN_EXPIRES_AT'])->where('ID', $pendingId)->exec()->fetch();
    $assert(in_array($row['UF_TOKEN'], [null, ''], true) && in_array($row['UF_TOKEN_EXPIRES_AT'], [null, ''], true), 'administrative disable revokes stored token and expiration');
    $repository->updateToken($userId, 'W02-active-pending-token', DateTime::createFromTimestamp($clock->now() + 60));
    $assert($writer->Update($userId, ['UF_AUTH_REGISTRATION_PENDING' => 1]), 'mark synthetic active pending identity');
    $expectHttp(static fn(): int => $resolver->resolveUserId('W02-active-pending-token'), 401, 'active but pending token rejected');
    $expectHttp(static fn() => $login->execute($credentials), 401, 'active but pending login rejected');
    $assert($writer->Update($userId, ['ACTIVE' => 'N']), 'disable active user through real hook');
    // Simulate a stale token remaining in external/legacy data; ACTIVE must still deny it.
    $repository->updateToken($userId, 'W02-disabled-token', DateTime::createFromTimestamp($clock->now() + 60));
    $expectHttp(static fn(): int => $resolver->resolveUserId('W02-disabled-token'), 401, 'disabled user rejected even with a valid persisted token');

    $activationId = $repository->createInactiveUser('admin-activation@example.invalid', 'W02-fixture-password!42', 'Admin activation');
    $assert($writer->Update($activationId, ['ACTIVE' => 'Y']), 'real CUser explicit administrative activation');
    $activated = $repository->findById($activationId);
    $assert(null !== $activated && $activated->isActive && !$activated->isPendingRegistration, 'explicit administrative activation clears pending marker');
    $assert(null !== $repository->findActiveByEmail('admin-activation@example.invalid'), 'administratively activated user is eligible for API login');

    $stage = 'registration-usecases-and-rollback';
    $mailer = new class implements RegistrationConfirmationMailerInterface {
        public string $lastCode = '';

        public function sendConfirmationCode(string $email, string $code, DateTime $expiresAt): void
        {
            $this->lastCode = $code;
        }
    };
    $requestCode = new RequestRegistrationCodeUseCase($repository, $confirmationRepository, new RegistrationCodeGenerator(), $mailer, 15, 60, $clock, $transaction);
    $confirm = new ConfirmRegistrationUseCase($repository, $confirmationRepository, new TokenGenerator(), 24, 5, $clock, $transaction);
    $expectHttp(static fn() => $requestCode->execute(new RequestRegistrationCodeRequestDto('legacy@example.invalid', 'W02-attempt-password!42')), 409, 'legacy disabled re-registration rejected');
    $unchanged = $sql->query("SELECT PASSWORD FROM b_user WHERE ID = {$legacyId}")->fetch_assoc();
    $assert('fixture-unused' === $unchanged['PASSWORD'], 'legacy disabled password unchanged');
    $requestCode->execute(new RequestRegistrationCodeRequestDto('register@example.invalid', 'W02-fixture-password!42'));
    $registration = $confirmationRepository->findByEmail('register@example.invalid');
    $assert(null !== $registration && $clock->now() === $registration->createdAt->getTimestamp(), 'registration timestamp persisted from clock');
    $assert($clock->now() + 900 === $registration->codeExpiresAt->getTimestamp(), 'registration expiration survives real SQL DATETIME roundtrip');
    $expectHttp(static fn() => $requestCode->execute(new RequestRegistrationCodeRequestDto('register@example.invalid', 'W02-replacement-password!42')), 429, 'resend cooldown is enforced');
    $wrongCode = '000000' === $mailer->lastCode ? '111111' : '000000';
    $expectHttp(static fn() => $confirm->execute(new ConfirmRegistrationRequestDto('register@example.invalid', $wrongCode)), 400, 'wrong confirmation code rejected');
    $assert(1 === $confirmationRepository->findByEmail('register@example.invalid')?->attempts, 'failed attempt is committed despite public error');
    $failureHandler = EventManager::getInstance()->addEventHandlerCompatible(
        'main',
        'OnAfterUserUpdate',
        static function(array &$fields) use ($registration, $assert): void {
            if ($registration->userId !== (int)($fields['ID'] ?? 0) || !isset($fields['UF_TOKEN'])) {
                return;
            }
            $stored = UserTable::query()->setSelect(['UF_TOKEN'])->where('ID', $registration->userId)->exec()->fetch();
            $assert(false !== $stored && hash_equals($fields['UF_TOKEN'], (string)$stored['UF_TOKEN']), 'token really persisted inside the open transaction before injected fault');

            throw new RuntimeException('W02 controlled failure after token write');
        },
    );
    try {
        $confirm->execute(new ConfirmRegistrationRequestDto('register@example.invalid', $mailer->lastCode));
        throw new LogicException('Expected controlled failure after token write.');
    } catch (RuntimeException $exception) {
        $assert('W02 controlled failure after token write' === $exception->getMessage(), 'controlled failure occurs after activation confirmation and token writes');
    } finally {
        EventManager::getInstance()->removeEventHandler('main', 'OnAfterUserUpdate', $failureHandler);
    }
    $afterFailure = $repository->findById($registration->userId);
    $assert(null !== $afterFailure && !$afterFailure->isActive && $afterFailure->isPendingRegistration, 'transaction rolls back activation and pending marker');
    $assert(null === $confirmationRepository->findByEmail('register@example.invalid')?->confirmedAt, 'transaction rolls back confirmation timestamp');
    $afterRollback = UserTable::query()->setSelect(['UF_TOKEN', 'UF_TOKEN_EXPIRES_AT'])->where('ID', $registration->userId)->exec()->fetch();
    $assert(in_array($afterRollback['UF_TOKEN'], [null, ''], true) && in_array($afterRollback['UF_TOKEN_EXPIRES_AT'], [null, ''], true), 'transaction rolls back already written token and expiration');
    $result = $confirm->execute(new ConfirmRegistrationRequestDto('register@example.invalid', $mailer->lastCode));
    $assert($registration->userId === $resolver->resolveUserId($result->token), 'registration confirmation activates and issues usable token');
    $expectHttp(static fn() => $confirm->execute(new ConfirmRegistrationRequestDto('register@example.invalid', $mailer->lastCode)), 404, 'confirmation replay cannot issue another token');
    $requestCode->execute(new RequestRegistrationCodeRequestDto('blocked-pending@example.invalid', 'W02-fixture-password!42'));
    $blocked = $confirmationRepository->findByEmail('blocked-pending@example.invalid');
    $assert($writer->Update($blocked->userId, ['ACTIVE' => 'N']), 'administratively disable unconfirmed registration');
    $expectHttp(static fn() => $confirm->execute(new ConfirmRegistrationRequestDto('blocked-pending@example.invalid', $mailer->lastCode)), 409, 'valid confirmation cannot reactivate administratively disabled pending user');

    $stage = 'native-administrator-confirmation-interleaving';
    $requestCode->execute(new RequestRegistrationCodeRequestDto('interleaving@example.invalid', 'W02-fixture-password!42'));
    $interleavingRegistration = $confirmationRepository->findByEmail('interleaving@example.invalid');
    $interleavingCode = $mailer->lastCode;
    $interleavingRan = false;
    // This native event runs after CUser writes b_user and before it writes user fields.
    // Calling the real confirmation here deterministically reproduces that SQL interleaving.
    $interleavingHandler = EventManager::getInstance()->addEventHandler(
        'main',
        'onUpdateUserFieldValues',
        static function(Event $event) use ($interleavingRegistration, $interleavingCode, &$interleavingRan, $confirm, $assert, $expectHttp, $sql): void {
            $fields = $event->getParameter('fields');
            if ('USER' !== $event->getParameter('entityId') || $interleavingRegistration->userId !== $event->getParameter('id') || 'N' !== ($fields['ACTIVE'] ?? null)) {
                return;
            }
            $interleavingRan = true;
            $visible = $sql->query('SELECT ACTIVE FROM b_user WHERE ID = ' . $interleavingRegistration->userId)->fetch_assoc();
            $assert('N' === $visible['ACTIVE'], 'native administrator b_user write is already committed before user-field write');
            $expectHttp(
                static fn() => $confirm->execute(new ConfirmRegistrationRequestDto('interleaving@example.invalid', $interleavingCode)),
                409,
                'confirmation is denied between native administrator b_user and user-field writes',
            );
        },
    );
    try {
        $assert($writer->Update($interleavingRegistration->userId, ['ACTIVE' => 'N']), 'native administrator disable completes after interleaved confirmation');
    } finally {
        EventManager::getInstance()->removeEventHandler('main', 'onUpdateUserFieldValues', $interleavingHandler);
    }
    $assert($interleavingRan, 'native user-field event exercised the exact interleaving');
    $interleavingState = $repository->findById($interleavingRegistration->userId);
    $assert(null !== $interleavingState && !$interleavingState->isActive && !$interleavingState->isPendingRegistration, 'interleaved confirmation cannot undo administrative disable');

    $stage = 'repeatable-read-current-row-lock';
    $lockedId = $repository->createInactiveUser('locking@example.invalid', 'W02-fixture-password!42', 'Lock fixture');
    $db = Application::getConnection();
    $db->queryExecute('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->startTransaction();
    try {
        $assert(true === $repository->findById($lockedId)?->isPendingRegistration, 'repeatable-read snapshot initially sees pending');
        // Independent SQL connection represents already committed administrator state.
        $sql->query("UPDATE b_uts_user SET UF_AUTH_REGISTRATION_PENDING = 0 WHERE VALUE_ID = {$lockedId}");
        $assert(true === $repository->findById($lockedId)?->isPendingRegistration, 'ordinary ORM read retains old repeatable-read snapshot');
        $current = $repository->findByIdForUpdate($lockedId);
        $assert(null !== $current && !$current->isPendingRegistration, 'FOR UPDATE re-reads latest committed state instead of stale snapshot');
    } finally {
        $db->rollbackTransaction();
    }

    $stage = 'migration-down';
    ob_start();
    try {
        $migration->down();
    } finally {
        ob_end_clean();
    }
    $count = $sql->query("SELECT COUNT(*) AS C FROM b_user_field WHERE ENTITY_ID = 'USER' AND FIELD_NAME = 'UF_AUTH_REGISTRATION_PENDING'")->fetch_assoc();
    $assert(0 === (int)$count['C'], 'migration down removes only pending UF');
    $count = $sql->query("SELECT COUNT(*) AS C FROM b_user_field WHERE ENTITY_ID = 'USER' AND FIELD_NAME IN ('UF_TOKEN', 'UF_TOKEN_EXPIRES_AT')")->fetch_assoc();
    $assert(2 === (int)$count['C'], 'migration down preserves existing token fields');
    $count = $sql->query("SELECT COUNT(*) AS C FROM b_module_to_module WHERE FROM_MODULE_ID = 'main' AND MESSAGE_ID = 'OnBeforeUserUpdate' AND TO_MODULE_ID = 'rebit.auth'")->fetch_assoc();
    $assert(0 === (int)$count['C'], 'migration down unregisters administrative hook');

    echo json_encode([
        'status' => 'PASS', 'phpVersion' => PHP_VERSION, 'kernelVersion' => $fixture['kernelVersion'], 'mysqlVersion' => $sql->server_info,
        'checksPassed' => count($checks), 'checks' => $checks,
        'bootstrapSeam' => 'Isolated CLI context; no full prolog or application init. Real CUser, UF, ORM and production repositories.',
        'testDoubles' => ['deterministic clock', 'CAPTCHA acceptance', 'mail capture', 'controlled OnAfterUserUpdate failure after token write'],
        'database' => 'fresh disposable rabit_w02', 'externalNetwork' => false, 'hostPorts' => false,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'status' => 'FAIL', 'stage' => $stage, 'checksPassed' => count($checks), 'class' => $exception::class,
        'error' => $exception->getMessage(), 'file' => basename($exception->getFile()), 'line' => $exception->getLine(),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}
