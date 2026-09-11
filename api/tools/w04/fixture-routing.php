<?php

declare(strict_types=1);

/**
 * Test-only front controller, mounted at /bitrix/routing_index.php in an isolated FPM.
 * Uses the real kernel request and application mappers, without Bitrix prolog/DB.
 */

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Data\Cache as BitrixCache;
use Bitrix\Main\Data\CacheEngineNone;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Loader;
use Bitrix\Main\Server;
use Rebit\Share\Application\Interface\RequestDtoInterface;
use Rebit\Share\Domain\File\Dto\Request\UploadRequestFileRequestDto;
use Rebit\Share\Domain\File\Exception\InvalidFileException;
use Rebit\Share\Infrastructure\Controller\Request\RequestFileToDtoMapper;
use Rebit\Share\Infrastructure\Controller\Request\RequestToDtoMapper;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Facade\Cache;
use Symfony\Component\Validator\Constraints as Assert;

$autoload = require '/vendor/autoload.php';
$autoload->addPsr4('Rebit\Share\\', '/source/public/local/modules/rebit.share/lib/', true);
require_once '/kernel/modules/main/lib/loader.php';
Loader::registerNamespace('Bitrix\Main', '/kernel/modules/main/lib');
spl_autoload_register([Loader::class, 'autoLoad']);

final readonly class W04PatchRequestDto implements RequestDtoInterface
{
    public function __construct(
        #[Assert\Positive]
        public int $quantity,
    ) {}
}

// Cookie decoding is outside this fixture and otherwise queries Bitrix options.
final class W04HttpRequest extends HttpRequest
{
    protected function prepareCookie(array $cookies): array
    {
        return [];
    }
}

Cache::setDataCache(new BitrixCache(new CacheEngineNone()));
header('Content-Type: application/json; charset=utf-8');

try {
    $body = file_get_contents('php://input');
    $json = [];
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($json)) {
            throw new InvalidArgumentException('Expected JSON object.');
        }
    }
    $server = new Server($_SERVER);
    $request = new W04HttpRequest($server, $_GET, $_POST, $_FILES, [], $json);
    // Supply the real cache API with a context, bypassing Application's DB/session constructor.
    $application = (new ReflectionClass(HttpApplication::class))->newInstanceWithoutConstructor();
    $context = new Context($application);
    $context->initialize($request, new HttpResponse(), $server);
    $application->setContext($context);
    (new ReflectionProperty(Application::class, 'instance'))->setValue(null, $application);

    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if ('/__w04/patch' === $path && 'PATCH' === $_SERVER['REQUEST_METHOD']) {
        $dto = (new RequestToDtoMapper($request))->map(W04PatchRequestDto::class);
        $result = ['quantity' => $dto->quantity];
    } elseif ('/__w04/upload' === $path && 'POST' === $_SERVER['REQUEST_METHOD']) {
        $dto = (new RequestFileToDtoMapper($request))->map(UploadRequestFileRequestDto::class);
        $result = [
            'name' => $dto->name,
            'size' => $dto->size,
            'type' => $dto->type,
            'uploadedByPhp' => is_uploaded_file($dto->tmpName),
        ];
    } else {
        http_response_code(404);
        $result = ['error' => 'fixture_route_not_found'];
    }

    echo json_encode([
        'fixture' => true,
        'result' => $result,
        'headersForwarded' => [
            'authorization' => 'Bearer w04-fixture' === $request->getHeader('Authorization'),
            'idempotencyKey' => 'w04-idempotency-fixture' === $request->getHeader('Idempotency-Key'),
            'orderKey' => 'w04-order-fixture' === $request->getHeader('X-Order-Key'),
        ],
    ], JSON_THROW_ON_ERROR);
} catch (HttpException $exception) {
    http_response_code($exception->getCode());
    echo json_encode(['fixture' => true, 'error' => $exception::class, 'file' => basename($exception->getFile()), 'line' => $exception->getLine()], JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException|InvalidFileException|JsonException $exception) {
    http_response_code(400);
    echo json_encode(['fixture' => true, 'error' => $exception::class, 'file' => basename($exception->getFile()), 'line' => $exception->getLine()], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['fixture' => true, 'error' => $exception::class, 'file' => basename($exception->getFile()), 'line' => $exception->getLine()], JSON_THROW_ON_ERROR);
}
