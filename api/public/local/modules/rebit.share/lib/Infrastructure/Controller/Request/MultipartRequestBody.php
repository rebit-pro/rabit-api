<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request;

use Bitrix\Main\HttpRequest;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Читает поля и файлы multipart/form-data. PHP разбирает multipart сам только для POST,
 * поэтому тело остальных методов читается через request_parse_body().
 *
 * @internal
 */
final readonly class MultipartRequestBody
{
    public function __construct(
        private HttpRequest $request,
    ) {}

    /**
     * @return array{array<array-key, mixed>, array<array-key, mixed>} поля формы и файлы
     *
     * @throws HttpException
     */
    public function read(string $failedCode): array
    {
        if (1 !== preg_match('/^multipart\/form-data(?:\s*;|$)/i', (string)$this->request->getHeader('Content-Type'))) {
            throw new HttpException('MULTIPART_REQUIRED', 400);
        }
        if ('POST' === $this->request->getRequestMethod()) {
            return [$this->request->getPostList()->getValues(), $this->request->getFileList()->getValues()];
        }
        try {
            return request_parse_body();
        } catch (\RequestParseBodyException) {
            throw new HttpException($failedCode, 422);
        }
    }
}
