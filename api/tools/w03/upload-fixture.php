<?php

declare(strict_types=1);

/** Isolated real HTTP multipart boundary. Never saves files or sends notifications. */
use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Data\Cache as BitrixCache;
use Bitrix\Main\Data\CacheEngineNone;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Loader;
use Bitrix\Main\Server;
use Rebit\Notification\Application\Lead\Dto\Request\SubmitLeadRequestDto;
use Rebit\Notification\Infrastructure\Lead\UploadedFileValidator;
use Rebit\Share\Domain\File\Dto\Request\UploadRequestFileRequestDto;
use Rebit\Share\Domain\File\Exception\InvalidFileException;
use Rebit\Share\Infrastructure\Controller\Request\RequestFileToDtoMapper;
use Rebit\Share\Infrastructure\Controller\Request\RequestToDtoMapper;
use Rebit\Share\Infrastructure\Controller\Responses\JsonExceptionResponse;
use Rebit\Share\Shared\Facade\Cache;
use Bitrix\Main\Config\Configuration;

require '/app/vendor/autoload.php';
require '/kernel/modules/main/lib/loader.php';
Loader::registerNamespace('Bitrix\Main', '/kernel/modules/main/lib');
spl_autoload_register([Loader::class, 'autoLoad']);
Cache::setDataCache(new BitrixCache(new CacheEngineNone()));
$configuration = Configuration::getInstance();
(new ReflectionProperty($configuration, 'isLoaded'))->setValue($configuration, true);

header('Content-Type: application/json; charset=utf-8');
try {
    $server = new Server($_SERVER);
    $request = new class($server, $_GET, $_POST, $_FILES, []) extends HttpRequest {
        protected function prepareCookie(array $cookies): array
        {
            return [];
        }
    };
    $application = (new ReflectionClass(HttpApplication::class))->newInstanceWithoutConstructor();
    $context = new Context($application);
    $context->initialize($request, new HttpResponse(), $server);
    $application->setContext($context);
    (new ReflectionProperty(Application::class, 'instance'))->setValue(null, $application);

    if ('/lead-fixture' === parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
        $dto = (new RequestToDtoMapper($request))->map(SubmitLeadRequestDto::class);
        $attachment = (new UploadedFileValidator(15 * 1024 * 1024))->validate($request->getFile('file'));
        $result = ['name' => $dto->name, 'source' => $dto->source, 'attachment' => null !== $attachment];
    } elseif ('/invalid-file-fixture' === parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
        throw new InvalidFileException('/private/w03-secret-path secret-token', previous: new RuntimeException('secret-previous'));
    } else {
        $dto = (new RequestFileToDtoMapper($request))->map(UploadRequestFileRequestDto::class);
        $result = [
            'moduleId' => $dto->moduleId, 'name' => $dto->name, 'type' => $dto->type,
            'size' => $dto->size, 'uploadedByPhp' => is_uploaded_file($dto->tmpName),
        ];
    }
    echo json_encode(['data' => $result, 'error' => null], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    $response = (new JsonExceptionResponse($exception, '1' === ($_GET['debug'] ?? '')))->getResponse();
    http_response_code((int)$response->getStatus());
    echo $response->getContent();
}
