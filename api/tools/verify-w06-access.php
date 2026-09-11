<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Loader;
use Bitrix\Main\Server;
use Bitrix\Main\Type\DateTime;
use Morefoto\Access\Application\Bootstrap\UseCase\BootstrapOrganizerUseCase;
use Morefoto\Access\Application\Profile\UseCase\GetProfileUseCase;
use Morefoto\Access\Domain\Staff\Repository\AccessStateRepository;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Morefoto\Access\Presentation\Controller\ProfileController;
use Rebit\Auth\Domain\User\Repository\UserRepository;
use Rebit\Share\Application\Contract\Auth\Dto\IdentityOutputDto;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Bitrix\Main\Routing\Router;
use Bitrix\Main\Routing\RoutingConfigurator;
use Sprint\Migration\Version20260911200001;

$checks = [];
$stage = 'bootstrap';
$assert = static function(bool $condition, string $name) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Check failed: ' . $name);
    }
    $checks[] = $name;
};
$reject = static function(callable $operation, string $name) use ($assert): void {
    try {
        $operation();
    } catch (Throwable) {
        $assert(true, $name);

        return;
    }
    throw new RuntimeException('Expected rejection: ' . $name);
};
$httpReject = static function(callable $operation, int $code, string $name) use ($assert): void {
    try {
        $operation();
    } catch (HttpException $exception) {
        $assert($code === $exception->getCode(), $name);

        return;
    }
    throw new RuntimeException('Expected HTTP rejection: ' . $name);
};
try {
    $fixture = require __DIR__ . '/fixtures/w02/bootstrap.php';
    $sql = $fixture['connection'];
    $autoload = require '/app/vendor/autoload.php';
    $autoload->addPsr4('Morefoto\Access\\', '/app/public/local/modules/morefoto.access/lib/', true);
    symlink('/app/public/local/modules/morefoto.access', $fixture['documentRoot'] . '/local/modules/morefoto.access');
    // Native HL installation in the disposable database, with real UF event handlers.
    require_once '/kernel/modules/highloadblock/install/index.php';
    $assert((new highloadblock())->InstallDB(), 'native highloadblock installation');
    $stage = 'migrations';
    ob_start();
    try {
        foreach (['Version20260323120001', 'Version20260326120008', 'Version20260911120001', 'Version20260911200001'] as $version) {
            require_once '/app/public/local/php_interface/migrations.foundation/' . $version . '.php';
            $class = 'Sprint\Migration\\' . $version;
            (new $class())->up();
        }
        $migration = new Version20260911200001();
        $migration->up();
    } finally {
        ob_end_clean();
    }
    $assert(Loader::includeModule('morefoto.access'), 'native MoreFoto module bootstrap');
    $assert(1 === (int)$sql->query("SELECT COUNT(*) C FROM b_hlblock_entity WHERE NAME='MfStaffProfile'")->fetch_assoc()['C'], 'idempotent HL metadata');
    $assert(7 === (int)$sql->query("SELECT COUNT(*) C FROM b_user_field WHERE ENTITY_ID=CONCAT('HLBLOCK_', (SELECT ID FROM b_hlblock_entity WHERE NAME='MfStaffProfile'))")->fetch_assoc()['C'], 'seven native HL fields');
    $assert('1' === $sql->query('SELECT assignments_revision FROM mf_access_state WHERE id=1')->fetch_assoc()['assignments_revision'], 'initial AccessState revision');
    foreach (['b_user', 'b_uts_user', 'b_hlbd_mf_staff_profile', 'mf_access_state'] as $table) {
        $engine = $sql->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}'")->fetch_assoc()['ENGINE'];
        $assert('InnoDB' === $engine, $table . ' participates in local transaction');
    }
    ob_start();
    try {
        $migration->down();
        $assert(!Application::getConnection()->isTableExists('b_hlbd_mf_staff_profile'), 'empty migration rollback removes staff schema');
        $migration->up();
    } finally {
        ob_end_clean();
    }
    $stage = 'di-and-routes';
    $locator = ServiceLocator::getInstance();
    // Native Loader registers module settings in the locator; validate actual exported services.
    $bootstrap = $locator->get(BootstrapOrganizerUseCase::class);
    $identities = $locator->get(IdentityGatewayInterface::class);
    $profiles = $locator->get(StaffProfileRepository::class);
    $tokens = $locator->get(TokenResolverInterface::class);
    $guard = $locator->get(AccessGuardInterface::class);
    $profileUseCase = $locator->get(GetProfileUseCase::class);
    $assert($bootstrap === $locator->get(BootstrapOrganizerUseCase::class), 'stateless DI singleton');
    $router = new Router();
    $configurator = new RoutingConfigurator();
    $configurator->setRouter($router);
    (require '/app/public/local/modules/morefoto.access/routes.php')($configurator);
    $router->releaseRoutes();
    $paths = [];
    foreach ($router->getRoutes() as $route) {
        $route->compile();
        $paths[] = $route->getUri();
    }
    $assert(['/api/v1/me'] === $paths, 'only ACC-01 is exposed; no public bootstrap');
    $users = new UserRepository();
    $newUser = static function(string $name, bool $active = true): int {
        $writer = new CUser();
        $password = bin2hex(random_bytes(24));
        $id = $writer->Add([
            'LOGIN' => $name . '@example.invalid', 'EMAIL' => $name . '@example.invalid', 'NAME' => $name,
            'PASSWORD' => $password, 'CONFIRM_PASSWORD' => $password, 'ACTIVE' => $active ? 'Y' : 'N',
        ]);
        if (false === $id || 0 >= (int)$id) {
            throw new RuntimeException('Cannot create fixture identity.');
        }

        return (int)$id;
    };
    $id = $newUser('organizer');
    $otherId = $newUser('other');
    $inactiveId = $newUser('inactive', false);
    $users->updateToken($id, 'W06BeforeGrant', DateTime::createFromTimestamp(time() + 3600));
    $stage = 'bootstrap-transaction';
    $reject(static fn(): bool => $bootstrap->execute($inactiveId), 'inactive Auth identity cannot become organizer');
    $reject(static fn(): bool => $bootstrap->execute(99999999), 'missing Auth identity cannot become organizer');
    $httpReject(static fn() => $profileUseCase->execute($id), 403, 'active public identity has no staff rights');
    $fault = new class($identities) implements IdentityGatewayInterface {
        public function __construct(private readonly IdentityGatewayInterface $inner) {}

        public function findActive(int $userId): ?IdentityOutputDto
        {
            return $this->inner->findActive($userId);
        }

        public function lockActive(int $userId): ?IdentityOutputDto
        {
            return $this->inner->lockActive($userId);
        }

        public function revokeSessions(int $userId): void
        {
            $this->inner->revokeSessions($userId);
            throw new RuntimeException('Controlled failure after real token revocation.');
        }
    };
    $failingBootstrap = new BootstrapOrganizerUseCase(new AccessStateRepository(), $profiles, $fault);
    $reject(static fn(): bool => $failingBootstrap->execute($id), 'failure after real Auth write rejects grant');
    $assert(false === $profiles->findByUserId($id)->fetch(), 'failed grant leaves no profile');
    $assert($id === $tokens->resolveUserId('W06BeforeGrant'), 'failed grant restores previous token through transaction rollback');
    $assert('1' === $sql->query('SELECT assignments_revision FROM mf_access_state WHERE id=1')->fetch_assoc()['assignments_revision'], 'failed grant preserves AccessState revision');
    $assert($bootstrap->execute($id), 'first organizer created');
    $assert('2' === $sql->query('SELECT assignments_revision FROM mf_access_state WHERE id=1')->fetch_assoc()['assignments_revision'], 'successful grant increments AccessState once');
    $httpReject(static fn(): int => $tokens->resolveUserId('W06BeforeGrant'), 401, 'pre-grant token is revoked');
    $users->updateToken($id, 'W06AfterGrant', DateTime::createFromTimestamp(time() + 3600));
    $assert(!$bootstrap->execute($id), 'repeat bootstrap is a no-op');
    $assert($id === $tokens->resolveUserId('W06AfterGrant'), 'no-op does not revoke fresh session');
    $reject(static fn(): bool => $bootstrap->execute($otherId), 'bootstrap cannot create a second organizer');
    $reject(static fn() => $migration->down(), 'rollback cannot delete populated staff data');
    $stage = 'controller';
    $request = static function(?string $token) use ($profileUseCase, $tokens): array {
        $serverValues = $_SERVER;
        $serverValues['REQUEST_URI'] = '/api/v1/me';
        $serverValues['REQUEST_METHOD'] = 'GET';
        if (null !== $token) {
            $serverValues['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $server = new Server($serverValues);
        $httpRequest = new HttpRequest($server, [], [], [], []);
        Application::getInstance()->getContext()->initialize($httpRequest, new HttpResponse(), $server);
        $controller = new ProfileController($profileUseCase, $tokens);
        $response = $controller->run('me', []);
        if (!$response instanceof HttpResponse) {
            $response = new HttpResponse();
        }
        $controller->finalizeResponse($response);

        // Bitrix uses status 0 when PHP's default HTTP 200 is retained (HttpResponse::flushStatus).
        $status = (int)$response->getStatus();

        return [0 === $status ? 200 : $status, json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)];
    };
    [$status, $body] = $request('W06AfterGrant');
    if (200 !== $status) {
        throw new RuntimeException('Controller response ' . $status . ': ' . json_encode($body, JSON_THROW_ON_ERROR));
    }
    $assert(200 === $status, 'real GET /me controller succeeds');
    $assert([
        'id' => $id, 'name' => 'organizer', 'email' => 'organizer@example.invalid', 'role' => 'organizer',
        'active' => true, 'accessRevision' => 1,
        'permissions' => ['profile.read', 'staff.manage', 'institution.read', 'shoot.read', 'group.read', 'organization.manage'],
    ] === $body['data'], 'real serializer emits exact ACC-01 payload without secrets');
    $assert(401 === $request(null)[0], 'missing Bearer is rejected by actual filter');
    $assert(401 === $request('W06BeforeGrant')[0], 'revoked Bearer is rejected by actual filter');
    $users->updateToken($otherId, 'W06NoStaff', DateTime::createFromTimestamp(time() + 3600));
    $assert(403 === $request('W06NoStaff')[0], 'valid Bearer without staff profile is forbidden');
    $sql->query("UPDATE b_hlbd_mf_staff_profile SET UF_ACTIVE=0 WHERE UF_USER_ID={$id}");
    $assert(403 === $request('W06AfterGrant')[0], 'staff block is immediately enforced with a still-valid Auth token');
    $sql->query("UPDATE b_hlbd_mf_staff_profile SET UF_ACTIVE=1 WHERE UF_USER_ID={$id}");
    $rejectSql = static function(string $query, int $expectedCode, string $name) use ($sql, $assert): void {
        try {
            $result = $sql->query($query);
        } catch (mysqli_sql_exception $exception) {
            $assert($expectedCode === $exception->getCode(), $name);

            return;
        }
        // The native Bitrix driver can disable mysqli exceptions for this process.
        $assert(false === $result && $expectedCode === $sql->errno, $name);
    };
    $stage = 'authorization-and-constraints';
    $guard->assertCan($id, 'organization.manage');
    $assert(true, 'organizer passes server guard');
    $httpReject(static fn() => $guard->assertCan($id, 'unknown.action'), 403, 'unknown actions deny even organizer');
    foreach (['curator', 'head', 'teacher'] as $role) {
        $sql->query("UPDATE b_hlbd_mf_staff_profile SET UF_ROLE='{$role}' WHERE UF_USER_ID={$id}");
        $assert(['profile.read'] === $profileUseCase->execute($id)->permissions, $role . ' gets valid profile with empty scope');
        $httpReject(static fn() => $guard->assertCan($id, 'institution.read'), 403, $role . ' cannot list outside empty scope');
        $httpReject(static fn() => $guard->assertCan($id, 'group.read', 11, 21), 404, $role . ' cannot access a foreign group directly');
        $httpReject(static fn() => $guard->assertCan($id, 'staff.manage'), 403, $role . ' cannot administer staff');
    }
    $rejectSql("UPDATE b_hlbd_mf_staff_profile SET UF_ROLE='superuser' WHERE UF_USER_ID={$id}", 3819, 'database rejects unknown role');
    $rejectSql("UPDATE b_hlbd_mf_staff_profile SET UF_ACCESS_REVISION=0 WHERE UF_USER_ID={$id}", 3819, 'database rejects invalid access revision');
    $rejectSql("INSERT INTO b_hlbd_mf_staff_profile (UF_USER_ID,UF_ROLE,UF_ACTIVE,UF_REVISION,UF_ACCESS_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES ({$id},'organizer',1,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())", 1062, 'database rejects duplicate user profile');
    $sql->query('DELETE FROM mf_access_state');
    $reject(static fn(): bool => $bootstrap->execute($otherId), 'missing AccessState fails closed');
    echo json_encode([
        'status' => 'PASS', 'phpVersion' => PHP_VERSION, 'kernelVersion' => $fixture['kernelVersion'], 'mysqlVersion' => $sql->server_info,
        'checksPassed' => count($checks), 'checks' => $checks,
        'bootstrapSeam' => 'Isolated CLI context with real Bitrix HTTP request/controller/filter/serializer, native HL install, migrations, DI, CUser and MySQL. No full prolog or nginx/FPM request.',
        'testDoubles' => ['controlled failure after real Auth token revocation'],
        'database' => 'fresh disposable rabit_w02 shared fixture', 'externalNetwork' => false, 'hostPorts' => false,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'status' => 'FAIL', 'stage' => $stage, 'checksPassed' => count($checks), 'class' => $exception::class,
        'error' => $exception->getMessage(), 'cause' => $exception->getPrevious()?->getMessage(), 'file' => basename($exception->getFile()), 'line' => $exception->getLine(),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}
