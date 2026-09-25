<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request;

use Bitrix\Main\HttpRequest;
use Rebit\Share\Infrastructure\Exception\DtoInterfaceNotImplementException;
use Rebit\Share\Infrastructure\Interface\RequestMapperInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;
use Rebit\Share\Shared\Interface\RequestImageDtoInterface;

/**
 * Мапит multipart-запрос с ровно одним загруженным файлом `file` в DTO с интерфейсом RequestImageDtoInterface.
 * Тело POST и PUT читает MultipartRequestBody.
 * Содержимое файла здесь не проверяется: формат и размеры — дело предметного инспектора.
 */
final readonly class RequestImageToDtoMapper implements RequestMapperInterface
{
    public function __construct(
        private HttpRequest $request,
    ) {}

    public function support(string $className): bool
    {
        return is_subclass_of($className, RequestImageDtoInterface::class);
    }

    /**
     * @throws DtoInterfaceNotImplementException
     * @throws HttpException
     * @throws \ReflectionException
     */
    public function map(string $className): object
    {
        if (!$this->support($className)) {
            throw new DtoInterfaceNotImplementException(sprintf('%s does not implement RequestImageDtoInterface', $className));
        }
        [$fields, $files] = (new MultipartRequestBody($this->request))->read('IMAGE_UPLOAD_FAILED');
        if ([] !== $fields) {
            throw new HttpException('UNKNOWN_FIELD', 422);
        }
        $file = $files['file'] ?? null;
        if (1 !== count($files) || !is_array($file) || !is_string($file['tmp_name'] ?? null)) {
            throw new HttpException('ONE_IMAGE_REQUIRED', 422);
        }
        if (UPLOAD_ERR_OK !== (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) || !is_uploaded_file($file['tmp_name'])) {
            throw new HttpException('IMAGE_UPLOAD_FAILED', 422);
        }
        $data = (new RequestTechnicalValues($this->request))->append(new \ReflectionClass($className), [
            'tmpName' => $file['tmp_name'],
            'bytes' => (int)($file['size'] ?? 0),
        ]);

        return ArrayToDtoMapper::map($data, $className);
    }
}
