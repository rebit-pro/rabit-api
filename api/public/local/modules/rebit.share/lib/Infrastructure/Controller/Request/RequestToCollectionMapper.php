<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Request;

use Bitrix\Main\HttpRequest;
use Rebit\Share\Application\Collection\AbstractRequestCollection;
use Rebit\Share\Infrastructure\Exception\ValidationHttpException;
use Rebit\Share\Infrastructure\Interface\RequestMapperInterface;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

/**
 * Маппит корневой JSON-массив в коллекцию, унаследованную от AbstractRequestCollection.
 *
 * @see AbstractRequestCollection
 */
final readonly class RequestToCollectionMapper implements RequestMapperInterface
{
    public function __construct(
        private HttpRequest $request,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function support(string $className): bool
    {
        return is_subclass_of($className, AbstractRequestCollection::class);
    }

    /**
     * {@inheritdoc}
     *
     * @throws ValidationHttpException
     */
    public function map(string $className): object
    {
        $requestData = $this->request->getJsonList()->getValues();
        $itemClass = $className::getItemClass();
        $items = ArrayToDtoMapper::mapList($requestData, $itemClass);

        return new $className($items);
    }
}
