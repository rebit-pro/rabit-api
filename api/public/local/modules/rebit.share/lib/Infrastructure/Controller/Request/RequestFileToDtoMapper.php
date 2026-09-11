<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request;

use Bitrix\Main\HttpRequest;
use Rebit\Share\Domain\File\Exception\InvalidFileException;
use Rebit\Share\Infrastructure\Exception\DtoInterfaceNotImplementException;
use Rebit\Share\Infrastructure\Exception\RequestParameterException;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Infrastructure\Helpers\RequestHelper;
use Rebit\Share\Infrastructure\Interface\RequestMapperInterface;
use Rebit\Share\Shared\Interface\RequestFileDtoInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Rebit\Share\Infrastructure\File\TechnicalUploadValidator;
use Rebit\Share\Infrastructure\Helpers\ValidationHelper;

/**
 * Мапит multipart/form-data запрос в DTO, помеченные интерфейсом FileDtoInterface.
 *
 * Логика перенесена из BaseFormController:
 * - проверка Content-Type;
 * - извлечение одного файла из поля "file" и проверка ошибок PHP;
 * - проверка обязательного параметра "moduleId";
 * - сбор входных данных и денормализация в указанный класс DTO;
 * - валидация DTO.
 */
final readonly class RequestFileToDtoMapper implements RequestMapperInterface
{
    private DenormalizerInterface $denormalizer;

    public function __construct(
        private HttpRequest $request,
    ) {
        $this->denormalizer = $this->createDenormalizer();
    }

    public function support(string $className): bool
    {
        return is_subclass_of($className, RequestFileDtoInterface::class);
    }

    /**
     * @throws DtoInterfaceNotImplementException
     * @throws ExceptionInterface
     * @throws RequestParameterException
     * @throws ValidationHttpException
     * @throws InvalidFileException
     */
    public function map(string $className): object
    {
        if (!$this->support($className)) {
            throw new DtoInterfaceNotImplementException(sprintf('%s does not implement FileDtoInterface', $className));
        }

        $contentType = (string)$this->request->getHeader('Content-Type');
        if (1 !== preg_match('/^multipart\/form-data(?:\s*;|$)/i', $contentType)) {
            throw new InvalidFileException('Ожидается multipart/form-data.');
        }

        $files = $this->request->getFileList()->getValues();
        if (1 !== count($files) || !is_array($files['file'] ?? null)) {
            throw new InvalidFileException('Ожидается один файл в поле file.');
        }

        $moduleId = $this->request->getPost('moduleId');
        if (!is_string($moduleId) || '' === $moduleId) {
            throw new ValidationHttpException('Параметр moduleId не передан или некорректен.');
        }

        $metadata = (new TechnicalUploadValidator())->validate($files['file']);
        $requestData = RequestHelper::collectRequestValues($this->request);
        $inputData = array_merge($requestData, $metadata, ['moduleId' => $moduleId]);

        if (!$this->denormalizer->supportsDenormalization($inputData, $className)) {
            throw new RequestParameterException("Cannot denormalize into {$className}");
        }

        try {
            /** @var RequestFileDtoInterface $dto */
            $dto = $this->denormalizer->denormalize(
                $inputData,
                $className,
                null,
                ['allow_extra_attributes' => false],
            );
        } catch (ExceptionInterface $exception) {
            throw new ValidationHttpException(
                'Некорректные параметры загрузки.',
                previous: $exception instanceof \Exception ? $exception : null,
            );
        }

        ValidationHelper::validate($dto);

        return $dto;
    }

    private function createDenormalizer(): DenormalizerInterface
    {
        $encoders = [new JsonEncoder()];
        $normalizers = [
            new ArrayDenormalizer(),
            new BackedEnumNormalizer(),
            new ObjectNormalizer(
                classMetadataFactory: new ClassMetadataFactory(
                    new AttributeLoader(),
                ),
                propertyTypeExtractor: new PhpDocExtractor(),
            ),
        ];

        return new Serializer($normalizers, $encoders);
    }
}
