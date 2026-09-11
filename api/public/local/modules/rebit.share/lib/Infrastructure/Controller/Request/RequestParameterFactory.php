<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request;

use Bitrix\Main\Engine\AutoWire\Parameter;
use Bitrix\Main\HttpRequest;
use Rebit\Share\Infrastructure\Controller\AbstractController;
use Rebit\Share\Shared\Exception\RebitException;
use Rebit\Share\Infrastructure\Exception\RequestParameterException;
use Rebit\Share\Infrastructure\Interface\RequestMapperInterface;

/**
 * Фабрика создает параметры для автомаппинга DTO, EntityObject в контроллерах
 *
 * @internal
 *
 * @see AbstractController::getPrimaryAutoWiredParameter
 */
final readonly class RequestParameterFactory
{
    /**
     * Список поддерживаемых автомапперов.
     *
     * @var array<class-string<RequestMapperInterface>>
     */
    private const array MAPPER_CLASSES = [
        RequestToCollectionMapper::class,
        RequestToDtoMapper::class,
        RequestToEntityMapper::class,
        RequestFileToDtoMapper::class,
    ];

    /**
     * @var RequestMapperInterface[]
     */
    private array $mappers;

    public function __construct(
        private HttpRequest $request,
    ) {
        $mappers = [];
        foreach (self::MAPPER_CLASSES as $mapperClass) {
            $mappers[] = new $mapperClass($this->request);
        }

        $this->mappers = $mappers;
    }

    /**
     * @param class-string $className
     *
     * @throws RebitException
     * @throws RequestParameterException
     */
    public function createParameter(string $className): Parameter
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->support($className)) {
                return new Parameter(
                    $className,
                    fn() => $mapper->map($className),
                );
            }
        }

        throw new RequestParameterException("Нет подходящего маппера для класса {$className}");
    }

    /**
     * Проверяет поддерживается ли класс фабрикой, т.е. можем ли мы его автомаппить.
     *
     * @param class-string $className
     */
    public function support(string $className): bool
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->support($className)) {
                return true;
            }
        }

        return false;
    }
}
