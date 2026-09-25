<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Responses\ApiJsonExceptionResponse;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Exception\ValidationHttpException;

/**
 * @internal
 */
final class ApiJsonExceptionResponseTest extends TestCase
{
    #[DataProvider('refusals')]
    public function testAccessRefusalKeepsStatusAndCode(\Throwable $exception, int $status, string $code): void
    {
        $response = (new ApiJsonExceptionResponse($exception))->getResponse();
        /** @var array{
         *     error: array{code: string, message: string, details?: array<string, mixed>},
         *     meta: array{requestId: string},
         * } $body */
        $body = json_decode((string)$response->getContent(), true, 8, JSON_THROW_ON_ERROR);

        self::assertSame($status, $response->getStatus());
        self::assertSame(['code' => $code, 'message' => $code], $body['error']);
        self::assertNotSame('', $body['meta']['requestId']);
        self::assertArrayNotHasKey('data', $body);
    }

    /** @return iterable<string, array{\Throwable, int, string}> */
    public static function refusals(): iterable
    {
        yield 'missing bearer' => [new HttpException('UNAUTHORIZED', 401), 401, 'UNAUTHORIZED'];
        yield 'forbidden action' => [new HttpException('FORBIDDEN', 403), 403, 'FORBIDDEN'];
        yield 'hidden resource' => [new HttpException('NOT_FOUND', 404), 404, 'NOT_FOUND'];
        yield 'domain not found' => [new HttpException('STAFF_NOT_FOUND', 404), 404, 'STAFF_NOT_FOUND'];
        yield 'text message is never exposed' => [new HttpException('Action is forbidden.', 403), 403, 'SERVICE_UNAVAILABLE'];
        yield 'validation without code' => [new ValidationHttpException('Поле обязательно.'), 422, 'VALIDATION_FAILED'];
        yield 'status outside the contract' => [new HttpException('TEAPOT', 418), 503, 'TEAPOT'];
        yield 'foreign exception' => [new \RuntimeException('SQL failed'), 503, 'SERVICE_UNAVAILABLE'];
    }

    public function testDetailsAreSentOnlyWithCodeMessage(): void
    {
        $coded = (new ApiJsonExceptionResponse(new HttpException('PRICE_CHANGED', 409, details: ['total' => 100])))->getResponse();
        $text = (new ApiJsonExceptionResponse(new HttpException('Price changed.', 409, details: ['total' => 100])))->getResponse();

        self::assertSame(['total' => 100], json_decode((string)$coded->getContent(), true, 8, JSON_THROW_ON_ERROR)['error']['details']);
        self::assertArrayNotHasKey('details', json_decode((string)$text->getContent(), true, 8, JSON_THROW_ON_ERROR)['error']);
    }
}
