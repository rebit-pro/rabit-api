<?php

declare(strict_types=1);

/**
 * Real Bitrix exception-log smoke: no Composer, application bootstrap, database or HTTP.
 * Usage: php tools/verify-w01-exception-log.php [bitrix-root]
 * The licensed kernel may be mounted separately from this repository.
 */

use Bitrix\Main\Application;
use Bitrix\Main\Diag\ExceptionHandler;
use Bitrix\Main\Diag\ExceptionHandlerLog;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Rebit\Share\Infrastructure\Logger\RequestIdGenerator;
use Rebit\Share\Infrastructure\Logger\SafeExceptionHandlerLog;

$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$temporaryRoot = null;
$exitCode = 0;

try {
    $check(80400 <= PHP_VERSION_ID, 'PHP 8.4 or newer is required.');
    $apiRoot = dirname(__DIR__);
    $kernelRoot = realpath($argv[1] ?? $apiRoot . '/public/bitrix');
    $check(false !== $kernelRoot, 'Bitrix source root does not exist.');
    $mainRoot = $kernelRoot . '/modules/main';
    $check(is_file($mainRoot . '/lib/application.php'), 'Missing real Bitrix Application source.');

    $settings = require $apiRoot . '/public/local/.settings_extra.php';
    $exceptionSettings = $settings['exception_handling']['value'];
    $check(
        SafeExceptionHandlerLog::class === $exceptionSettings['log']['class_name'],
        'Effective exception logger must be SafeExceptionHandlerLog.',
    );
    $check(
        'php_interface/safe-exception-log.php' === $exceptionSettings['log']['required_file'],
        'The logger must have a local bootstrap required_file.',
    );
    $check(
        !class_exists(SafeExceptionHandlerLog::class, false),
        'The adapter must not be loaded when settings are read.',
    );

    $temporaryRoot = sys_get_temp_dir() . '/rabit-w01-exception-' . bin2hex(random_bytes(8));
    $check(mkdir($temporaryRoot . '/local/php_interface', 0700, true), 'Cannot create isolated document root.');
    $bootstrap = $apiRoot . '/public/local/php_interface/safe-exception-log.php';
    $check(symlink($bootstrap, $temporaryRoot . '/local/php_interface/safe-exception-log.php'), 'Cannot link early bootstrap.');
    $logPath = $temporaryRoot . '/bx_error.log';
    $exceptionSettings['log']['settings']['file'] = $logPath;
    $exceptionSettings['log']['settings']['log_size'] = 1;
    $check(
        false !== file_put_contents(
            $temporaryRoot . '/local/.settings.php',
            '<?php return ' . var_export(['exception_handling' => ['value' => $exceptionSettings]], true) . ';',
        ),
        'Cannot write isolated configuration.',
    );

    $_SERVER['DOCUMENT_ROOT'] = $temporaryRoot;
    $_SERVER['HTTP_HOST'] = 'SENTINEL_HOST_SECRET.example';
    $_SERVER['REQUEST_URI'] = '/orders/SENTINEL_URL_KEY?token=SENTINEL_QUERY_TOKEN';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer SENTINEL_BEARER';
    $_POST = ['password' => 'SENTINEL_PASSWORD', 'code' => 'SENTINEL_OTP'];

    // Register only the real kernel autoloaders needed by its factory and file logger.
    require_once $mainRoot . '/lib/loader.php';
    Loader::registerNamespace('Bitrix\Main', $mainRoot . '/lib');
    Loader::registerNamespace('Psr\Log', $mainRoot . '/vendor/psr/log/src');
    spl_autoload_register([Loader::class, 'autoLoad']);

    // Exercise the real factory without executing Application's cache/DB constructor.
    $application = (new ReflectionClass(HttpApplication::class))->newInstanceWithoutConstructor();
    $log = $application->createExceptionHandlerLog();
    $check($log instanceof SafeExceptionHandlerLog, 'Real kernel factory did not create the safe adapter.');
    $check(
        in_array(realpath($bootstrap), get_included_files(), true),
        'Real kernel factory did not load the early bootstrap.',
    );
    $check(!class_exists('Monolog\Logger', false), 'Monolog must not be loaded.');
    $check(!Application::hasInstance(), 'Application must not be initialized.');

    $requestId = RequestIdGenerator::getRequestId();
    $handler = new ExceptionHandler();
    $handler->setHandlerLog($log);
    $exception = (static function(string $password): ErrorException {
        return new ErrorException(
            'SENTINEL_EXCEPTION_MESSAGE {host} {exception} ' . $password,
            17,
            E_USER_WARNING,
            '/SENTINEL_PRIVATE_DIRECTORY/SafeFixture.php',
            42,
            new RuntimeException('SENTINEL_PREVIOUS_MESSAGE'),
        );
    })('SENTINEL_TRACE_ARGUMENT');

    $handler->writeToLog($exception, ExceptionHandlerLog::UNCAUGHT_EXCEPTION);
    $firstBytes = file_get_contents($logPath);
    $check(is_string($firstBytes) && '' !== $firstBytes, 'First exception record was not written.');
    $handler->writeToLog($exception, ExceptionHandlerLog::FATAL);
    $check(is_file($logPath . '.old'), 'Kernel log rotation did not run.');
    $check($firstBytes === file_get_contents($logPath . '.old'), 'Rotated record changed.');

    foreach ([$logPath . '.old' => 'UNCAUGHT_EXCEPTION', $logPath => 'FATAL'] as $path => $type) {
        $bytes = file_get_contents($path);
        $check(is_string($bytes), 'Cannot read exception log.');
        $check(!str_contains($bytes, 'SENTINEL_'), 'A private value leaked into exception log.');
        $check(1 === substr_count($bytes, PHP_EOL), 'Expected exactly one JSON line per exception.');
        $record = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        $check('PHP exception' === $record['message'], 'Expected a fixed diagnostic message.');
        $check($type === $record['type'], 'Kernel exception type was lost.');
        $check($requestId === $record['requestId'], 'Request correlation changed.');
        $check(ErrorException::class === $record['exception']['class'], 'Exception class was lost.');
        $check('SafeFixture.php' === $record['exception']['file'], 'Expected a safe file basename.');
        $check(42 === $record['exception']['line'], 'Exception line was lost.');
        $check(17 === $record['exception']['exceptionCode'], 'Exception code was lost.');
        $check(
            !array_key_exists('message', $record['exception'])
            && !array_key_exists('trace', $record['exception'])
            && !array_key_exists('previous', $record['exception']),
            'Unsafe exception fields were logged.',
        );
        $check(
            (is_int($record['durationMs']) || is_float($record['durationMs']))
            && 0 <= $record['durationMs'],
            'Request duration was lost.',
        );
    }

    $check(!class_exists('Bitrix\Main\Diag\LogFormatter', false), 'Default placeholder formatter must not be loaded.');
    $check(!class_exists('Bitrix\Main\Type\DateTime', false), 'Logger must not initialize the Bitrix date context.');
    $check(
        !in_array($apiRoot . '/vendor/autoload.php', get_included_files(), true)
        && !in_array($apiRoot . '/public/local/php_interface/init.php', get_included_files(), true),
        'Composer or application bootstrap was executed.',
    );

    echo json_encode(
        [
            'status' => 'PASS',
            'phpVersion' => PHP_VERSION,
            'realKernelFactory' => true,
            'earlyBootstrap' => true,
            'safeFileBytes' => true,
            'rotation' => true,
            'requestCorrelation' => true,
            'composerLoaded' => false,
            'databaseUsed' => false,
            'networkUsed' => false,
        ],
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        json_encode(
            ['status' => 'FAIL', 'error' => $exception->getMessage()],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ) . PHP_EOL,
    );
    $exitCode = 1;
} finally {
    if (null !== $temporaryRoot) {
        foreach (['bx_error.log', 'bx_error.log.old', 'local/.settings.php', 'local/php_interface/safe-exception-log.php'] as $file) {
            $path = $temporaryRoot . '/' . $file;
            if (is_file($path) || is_link($path)) {
                unlink($path);
            }
        }
        foreach (['/local/php_interface', '/local', ''] as $directory) {
            if (is_dir($temporaryRoot . $directory)) {
                rmdir($temporaryRoot . $directory);
            }
        }
    }
}

exit($exitCode);
