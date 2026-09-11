<?php

declare(strict_types=1);

namespace Rebit\Share\Tools\W01;

use Bitrix\Main\Application;
use Bitrix\Main\Engine\Action;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Event;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Server;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\HttpHeaders;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Rebit\Share\Infrastructure\Controller\AbstractController;
use Rebit\Share\Infrastructure\Controller\Filters\LoggerFilter;
use Rebit\Share\Infrastructure\HttpClient\Exception\HttpClientException;
use Rebit\Share\Infrastructure\HttpClient\RebitHttpClient;
use Rebit\Share\Infrastructure\Logger\HttpDebugLoggerFactory;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Facade\Log;
use Bitrix\Main\Engine\Controller;

/**
 * Offline smoke using real Bitrix classes, without prolog, DB or HTTP.
 * Usage: php tools/verify-w01-http-logs.php [bitrix-root]
 * Requires Composer dependencies in api/vendor and a separately supplied kernel.
 * Only cookie configuration, controller initialization and network I/O are
 * replaced with explicit fixture seams; no Bitrix class is redefined.
 */
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new \LogicException($message);
    }
};
$stage = 'bootstrap';

try {
    $check(80400 <= PHP_VERSION_ID, 'PHP 8.4 or newer is required.');
    $apiRoot = dirname(__DIR__);
    $kernelRoot = realpath($argv[1] ?? $apiRoot . '/public/bitrix');
    $check(false !== $kernelRoot, 'Bitrix kernel root does not exist.');
    $check(is_file($apiRoot . '/vendor/autoload.php'), 'Composer dependencies are required.');
    require $apiRoot . '/vendor/autoload.php';

    spl_autoload_register(static function(string $class) use ($kernelRoot): void {
        $prefix = 'Bitrix\Main\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $path = $kernelRoot . '/modules/main/lib/'
            . strtolower(str_replace('\\', '/', substr($class, strlen($prefix)))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });

    // Keep real HttpRequest construction and storage, but avoid Option::get/DB.
    class FixtureRequest extends HttpRequest
    {
        protected function prepareCookie(array $cookies): array
        {
            return $cookies;
        }
    }

    final class UnreadableRequest extends FixtureRequest
    {
        public function getJsonList(): never
        {
            throw new \LogicException('Logger read JSON body.');
        }

        public function getPostList(): never
        {
            throw new \LogicException('Logger read POST body.');
        }

        public function getQueryList(): never
        {
            throw new \LogicException('Logger read query fields.');
        }

        public function getFileList(): never
        {
            throw new \LogicException('Logger read uploaded files.');
        }

        public function getCookieList(): never
        {
            throw new \LogicException('Logger read cookies.');
        }

        public function getHeaders(): never
        {
            throw new \LogicException('Logger read request headers.');
        }

        public function getHeader(mixed $name): never
        {
            throw new \LogicException('Logger read a request header.');
        }

        public function getRequestUri(): never
        {
            throw new \LogicException('Logger read request URL.');
        }

        public static function getInput(): never
        {
            throw new \LogicException('Logger read php://input.');
        }
    }

    final class FixtureController extends AbstractController
    {
        // The real Controller constructor still initializes request/error/config objects.
        protected function init(): void {}

        public function fail(\Throwable $exception): void
        {
            $this->thrownException = $exception;
        }

        protected function getResponse(array $data, array $meta = []): HttpResponse
        {
            return (new HttpResponse())->setContent('{}');
        }

        protected function getExceptionResponse(): HttpResponse
        {
            return (new HttpResponse())->setContent('fixture-error-response')->setStatus(422);
        }
    }

    final class UnreadableResponse extends HttpResponse
    {
        public function getContent(): never
        {
            throw new \LogicException('Logger read response content.');
        }
    }

    final class UnreadableJsonResponse extends Json
    {
        public function getContent(): never
        {
            throw new \LogicException('Logger read/decode JSON response content.');
        }
    }

    // Real header handling, deterministic transport boundary, no network methods run.
    final class FixtureHttpClient extends HttpClient
    {
        public int $fixtureStatus = 200;
        public false|string $fixtureResponse = '{}';
        /** @var array<string, string> */
        public array $fixtureErrors = [];
        public string $sentUrl = '';
        public mixed $sentBody = null;

        public function __construct()
        {
            $this->headers = new HttpHeaders();
        }

        public function post(mixed $url, mixed $postData = null, mixed $multipart = false): false|string
        {
            $this->sentUrl = $url;
            $this->sentBody = $postData;

            return $this->fixtureResponse;
        }

        public function get(mixed $url): false|string
        {
            $this->sentUrl = $url;
            $this->sentBody = null;

            return $this->fixtureResponse;
        }

        public function getStatus(): int
        {
            return $this->fixtureStatus;
        }

        public function getError(): array
        {
            return $this->fixtureErrors;
        }

        public function sentHeader(string $name): ?string
        {
            return $this->headers->get($name);
        }
    }

    $stage = 'real-kernel-origin';
    foreach ([Event::class, HttpRequest::class, HttpResponse::class, Action::class,
        Controller::class] as $class) {
        $file = (new \ReflectionClass($class))->getFileName();
        $check(
            is_string($file) && str_starts_with($file, $kernelRoot . '/modules/main/'),
            'A Bitrix class was replaced by a stub.',
        );
    }

    $secret = 'W01_SYNTHETIC_SECRET_7c435d8a';
    $server = new Server([
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/api/v1/lead/' . $secret . '?token=' . $secret,
        'HTTP_AUTHORIZATION' => 'Bearer ' . $secret,
        'HTTP_X_ORDER_KEY' => $secret,
        'HTTP_COOKIE' => 'session=' . $secret,
        'HTTP_USER_AGENT' => $secret,
        'CONTENT_TYPE' => 'application/json',
    ]);
    $json = ['password' => $secret, 'captcha' => ['pass_token' => $secret],
        'lead' => ['email' => $secret . '@example.test', 'phone' => $secret]];
    $request = new UnreadableRequest(
        $server,
        ['token' => $secret],
        ['name' => $secret, 'description' => $secret],
        ['file' => ['name' => $secret, 'tmp_name' => '/tmp/' . $secret]],
        ['session' => $secret],
        $json,
    );
    $controller = new FixtureController($request);
    $action = new Action('submit', $controller);
    $handler = new TestHandler(Logger::DEBUG);
    $logger = new Logger('w01-smoke', [$handler]);
    $channels = new \ReflectionProperty(Log::class, 'channels');
    $previousChannels = $channels->getValue();
    $channelMap = [];
    foreach (LogChannelEnum::cases() as $channel) {
        $channelMap[$channel->value] = $logger;
    }
    $channels->setValue(null, $channelMap);

    $stage = 'request-response-boundary';
    $filter = new LoggerFilter(LogChannelEnum::notification, [
        'password' => $secret, 'headers' => ['Authorization' => $secret],
        'response' => ['token' => $secret], 'nested' => $json,
    ]);
    $before = new Event('main', 'onBeforeAction', ['controller' => $controller, 'action' => $action]);
    $check(null === $filter->onBeforeAction($before), 'Before filter changed execution result.');
    $responses = [
        (new UnreadableResponse())->setContent($secret)->setStatus(201),
        (new UnreadableJsonResponse(['data' => ['token' => $secret]]))
            ->setContent('{invalid-json:' . $secret)->setStatus(200),
        (new HttpResponse())->setContent('')->setStatus(204),
        ['token' => $secret],
        $secret,
        null,
    ];
    foreach ($responses as $response) {
        $event = new Event('main', 'onAfterAction', [
            'controller' => $controller, 'action' => $action, 'result' => $response,
        ]);
        $check(null === $filter->onAfterAction($event), 'After filter changed execution result.');
    }
    $records = $handler->getRecords();
    $check(7 === count($records), 'Expected REQUEST and six RESPONSE records.');
    $correlation = $records[0]['context']['requestId'] ?? null;
    $check(is_string($correlation) && '' !== $correlation, 'Missing requestId.');
    foreach ($records as $index => $record) {
        $context = $record['context'];
        $check((0 === $index ? 'REQUEST' : 'RESPONSE') === $record['message'], 'Wrong event name.');
        $check($correlation === ($context['requestId'] ?? null), 'Correlation was not preserved.');
        $check(
            FixtureController::class . '::submit' === ($context['operation'] ?? null),
            'Missing action operation.',
        );
        $check('POST' === ($context['method'] ?? null), 'Missing HTTP method.');
        $check(
            is_float($context['durationMs'] ?? null) && 0 <= $context['durationMs'],
            'Missing/non-numeric duration.',
        );
        $check(
            !array_key_exists('request', $context) && !array_key_exists('response', $context),
            'Payload survived context projection.',
        );
    }
    foreach ([1 => 201, 2 => 200, 3 => 204] as $index => $status) {
        $check($status === ($records[$index]['context']['httpStatus'] ?? null), 'HTTP status was lost.');
    }
    foreach ([4, 5, 6] as $index) {
        $check(!isset($records[$index]['context']['httpStatus']), 'Invented status for non-HTTP result.');
    }

    $stage = 'exception-finalization';
    $controller->fail(new \RuntimeException($secret, 71, new \RuntimeException($secret)));
    $finalResponse = (new HttpResponse())->setContent('before')->setStatus(200);
    $controller->finalizeResponse($finalResponse);
    $check(
        422 === $finalResponse->getStatus() && 'fixture-error-response' === $finalResponse->getContent(),
        'Exception response behavior changed.',
    );
    $records = $handler->getRecords();
    $exceptionRecord = $records[array_key_last($records)];
    $check('HTTP_EXCEPTION' === $exceptionRecord['message'], 'Missing exception diagnostic.');
    $exceptionContext = $exceptionRecord['context'];
    $check(422 === ($exceptionContext['httpStatus'] ?? null), 'Exception log used a stale status.');
    $check($correlation === ($exceptionContext['requestId'] ?? null), 'Exception correlation was lost.');
    $check(
        FixtureController::class . '::unknown' === ($exceptionContext['operation'] ?? null),
        'Missing safe operation before action initialization.',
    );
    $check(\RuntimeException::class === ($exceptionContext['class'] ?? null)
        && is_int($exceptionContext['line'] ?? null)
        && basename(__FILE__) === ($exceptionContext['file'] ?? null), 'Missing safe exception metadata.');
    $check(
        !str_contains(json_encode($records, JSON_THROW_ON_ERROR), $secret),
        'A request/response/exception secret reached the logger.',
    );

    $stage = 'outbound-boundary';
    $transport = new FixtureHttpClient();
    $client = new RebitHttpClient($logger, $transport);
    $url = 'https://example.invalid/bot' . $secret . '/send?token=' . $secret;
    $headers = ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $secret];
    $transport->fixtureResponse = json_encode(['token' => $secret], JSON_THROW_ON_ERROR);
    $result = $client->post($url, $json, $headers);
    $check(['token' => $secret] === $result, 'Outbound response was changed by logging.');
    $check($url === $transport->sentUrl, 'Outbound URL was redacted before transport.');
    $check(
        $json === json_decode($transport->sentBody, true, flags: JSON_THROW_ON_ERROR),
        'Outbound JSON payload changed.',
    );
    $check('Bearer ' . $secret === $transport->sentHeader('Authorization'), 'Outbound header changed.');
    $client->setAuthorization('fixture-user', $secret);
    $client->post($url, ['password' => $secret]);
    $check(['password' => $secret] === $transport->sentBody, 'Outbound form payload changed.');
    $check('Basic ' . base64_encode('fixture-user:' . $secret)
        === $transport->sentHeader('Authorization'), 'Basic credentials changed before transport.');

    $stage = 'buffered-http-failure';
    $bufferHandler = new TestHandler(Logger::INFO);
    $bufferLogger = HttpDebugLoggerFactory::create(new Logger('w01-buffer', [$bufferHandler]));
    $failingTransport = new FixtureHttpClient();
    $failingTransport->fixtureStatus = 503;
    $failingTransport->fixtureResponse = $secret;
    $bufferedClient = new RebitHttpClient($bufferLogger, $failingTransport);
    $failed = false;
    try {
        $bufferedClient->post($url, $json, $headers);
    } catch (HttpClientException) {
        $failed = true;
    }
    $check($failed, 'HTTP failure stopped throwing the established exception.');
    $bufferRecords = $bufferHandler->getRecords();
    $check(3 === count($bufferRecords) && $bufferHandler->hasDebugRecords()
        && $bufferHandler->hasErrorRecords(), 'Buffered debug/error records were not flushed.');
    $check(
        $url === $failingTransport->sentUrl
        && $json === json_decode($failingTransport->sentBody, true, flags: JSON_THROW_ON_ERROR),
        'Failure-path transport payload changed.',
    );
    $check(
        !str_contains(json_encode([$handler->getRecords(), $bufferRecords], JSON_THROW_ON_ERROR), $secret),
        'An outbound secret reached direct or buffered logging.',
    );
    $check(!Application::hasInstance(), 'Application/DB bootstrap unexpectedly ran.');
    $channels->setValue(null, $previousChannels);

    echo json_encode([
        'status' => 'PASS',
        'phpVersion' => PHP_VERSION,
        'realBitrixClasses' => 5,
        'requestResponseRecords' => 7,
        'exceptionFinalization' => true,
        'outboundPayloadPreserved' => true,
        'bufferedFailureRecords' => count($bufferRecords),
        'requestPayloadReads' => 0,
        'responsePayloadReadsByLogger' => 0,
        'databaseBootstrapExecuted' => false,
        'liveHttpRequestsExecuted' => false,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (\Throwable $exception) {
    fwrite(STDERR, json_encode([
        'status' => 'FAIL',
        'stage' => $stage,
        'exception' => $exception::class,
        'error' => $exception instanceof \LogicException ? $exception->getMessage() : 'Runtime boundary failed.',
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
