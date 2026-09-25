<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request;

use Bitrix\Main\HttpRequest;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\FormField;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\MultipartFile;
use Rebit\Share\Infrastructure\Exception\DtoInterfaceNotImplementException;
use Rebit\Share\Infrastructure\Interface\RequestMapperInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;
use Rebit\Share\Shared\Interface\RequestUploadDtoInterface;

/**
 * Мапит multipart-запрос с ровно одним файлом `file` и текстовыми полями формы в DTO с интерфейсом
 * RequestUploadDtoInterface. Проверяет файл, затем поля формы в порядке параметров, затем маршрут и заголовки;
 * незаявленные поля формы не читает. Содержимое файла — дело предметного инспектора.
 */
final readonly class RequestUploadToDtoMapper implements RequestMapperInterface
{
    public function __construct(
        private HttpRequest $request,
    ) {}

    public function support(string $className): bool
    {
        return is_subclass_of($className, RequestUploadDtoInterface::class);
    }

    /**
     * @throws DtoInterfaceNotImplementException
     * @throws HttpException
     * @throws \ReflectionException
     */
    public function map(string $className): object
    {
        if (!$this->support($className)) {
            throw new DtoInterfaceNotImplementException(sprintf('%s does not implement RequestUploadDtoInterface', $className));
        }
        $reflection = new \ReflectionClass($className);
        $codes = ($reflection->getAttributes(MultipartFile::class)[0] ?? null)?->newInstance()
            ?? throw new \LogicException(sprintf('%s must declare the MultipartFile attribute', $className));
        [$fields, $files] = (new MultipartRequestBody($this->request))->read($codes->failedCode);
        $file = $files['file'] ?? null;
        if (1 !== count($files) || !is_array($file)) {
            throw new HttpException($codes->missingCode, 422);
        }
        if (UPLOAD_ERR_OK !== (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) || !is_string($file['tmp_name'] ?? null)) {
            throw new HttpException($codes->failedCode, 422);
        }
        $data = [
            'tmpName' => $file['tmp_name'],
            'filename' => is_string($file['name'] ?? null) ? $file['name'] : '',
            'bytes' => (int)($file['size'] ?? 0),
        ];
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $field = $parameter->getAttributes(FormField::class)[0] ?? null;
            if (null !== $field) {
                $data[$parameter->getName()] = $this->field($field->newInstance(), $parameter, $fields[$parameter->getName()] ?? null);
            }
        }

        return ArrayToDtoMapper::map((new RequestTechnicalValues($this->request))->append($reflection, $data), $className);
    }

    /** @throws HttpException */
    private function field(FormField $field, \ReflectionParameter $parameter, mixed $value): ?string
    {
        if (null === $value && $parameter->allowsNull()) {
            return null;
        }
        if (!is_string($value) || (null !== $field->pattern && 1 !== preg_match($field->pattern, $value))) {
            throw new HttpException($field->errorCode, 422);
        }

        return $value;
    }
}
