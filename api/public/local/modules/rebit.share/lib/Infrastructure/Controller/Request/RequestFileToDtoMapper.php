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
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

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
        if ('' === $contentType || false === stripos($contentType, 'multipart/form-data')) {
            throw new InvalidFileException('Ожидается заголовок Content-Type: multipart/form-data.');
        }

        $file = $this->request->getFile('file') ?? [];
        if ([] === $file) {
            throw new InvalidFileException('Файл не передан.');
        }

        if (is_array($file['name'] ?? null)) {
            throw new InvalidFileException('Ожидается один файл в поле file.');
        }

        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (UPLOAD_ERR_OK !== $error) {
            throw new InvalidFileException('Ошибка загрузки файла (error=' . $error . ').');
        }

        $moduleId = (string)$this->request->getPost('moduleId');
        if ('' === $moduleId) {
            throw new InvalidFileException('Параметр moduleId не передан.');
        }

        $requestData = RequestHelper::collectRequestValues($this->request);
        $inputData = array_merge(
            $requestData,
            [
                'moduleId' => $moduleId,
                'name' => (string)($file['name'] ?? ''),
                'type' => (string)($file['type'] ?? ''),
                'tmpName' => (string)($file['tmp_name'] ?? ''),
                'size' => (int)($file['size'] ?? 0),
            ],
        );

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
        } catch (MissingConstructorArgumentsException $e) {
            $missingFields = implode(', ', $e->getMissingConstructorArguments());
            throw new ValidationHttpException('В запросе не были переданы поля: ' . $missingFields);
        } catch (ExtraAttributesException $e) {
            $extraFields = implode(', ', $e->getExtraAttributes());
            throw new ValidationHttpException(
                sprintf(
                    'В запросе переданы поля, отсутствующие в DTO (%s):  %s',
                    $className,
                    $extraFields,
                ),
            );
        }

        $this->validate($dto);

        return $dto;
    }

    /**
     * Валидация DTO.
     *
     * @throws ValidationHttpException
     */
    private function validate(RequestFileDtoInterface $dto): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
            ->getValidator()
        ;

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidationHttpException($errors);
        }
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
