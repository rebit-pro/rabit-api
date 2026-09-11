<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller;

use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Response;
use Bitrix\Main\SystemException;
use Rebit\Share\Infrastructure\Controller\Filters\LoggerFilter;
use Rebit\Share\Infrastructure\Controller\Request\RequestParameterFactory;
use Rebit\Share\Infrastructure\Controller\Responses\AbstractResponse;
use Bitrix\Main\Engine\Action;
use Bitrix\Main\Engine\AutoWire\Parameter;
use Bitrix\Main\Engine\FallbackAction;
use Bitrix\Main\Engine\InlineAction;
use Bitrix\Main\Engine\ActionFilter\Base;
use Rebit\Share\Shared\Exception\RebitException;
use Rebit\Share\Infrastructure\Exception\RequestParameterException;

/**
 * @internal
 *
 * Внутренний абстрактный класс для любых контроллеров
 *
 * @template TResponse of HttpResponse
 */
abstract class AbstractController extends Controller
{
    public const int HTTP_CREATED_CODE = 201;

    public const int HTTP_NO_CONTENT_CODE = 204;

    /**
     * Выброшенное при работе контроллера исключение
     */
    protected ?\Throwable $thrownException = null;

    /** Имя текущего выполняющегося Action-метода контролера */
    private string $actionMethodName;

    /**
     * Основной метод возвращающий ответ контроллера.
     * При реализации использовать наследников AbstractResponse
     *
     * @see AbstractResponse
     */
    abstract protected function getResponse(array $data, array $meta = []): HttpResponse;

    /**
     * Метод возвращает ответ, если произошло исключение.
     */
    abstract protected function getExceptionResponse(): HttpResponse;

    /**
     * В этом методе мы замапим request на DTO либо сущность, используя AutoWire Битрикса
     *
     * ВАЖНО: DTO или сущность (ее primaryId) должны быть единственным параметром экшена.
     *
     * @throws RebitException
     * @throws RequestParameterException
     * @throws \ReflectionException
     */
    final public function getPrimaryAutoWiredParameter(): ?Parameter
    {
        $reflectionMethod = new \ReflectionMethod($this, $this->actionMethodName);
        $parameterFactory = new RequestParameterFactory($this->request);

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $className = $type->getName();
            if (!$parameterFactory->support($className)) {
                continue;
            }

            return $parameterFactory->createParameter($className);
        }

        return null;
    }

    /**
     * Возвращает пустой ответ с кодом 204, используется после удаления
     *
     * @throws ArgumentTypeException
     */
    final public function noContent(): HttpResponse
    {
        return (new HttpResponse())
            ->setContent(null)
            ->setStatus(self::HTTP_NO_CONTENT_CODE)
        ;
    }

    /**
     * Возвращает пустой ответ с кодом 201, используется после создания
     *
     * @throws ArgumentTypeException
     */
    final public function created(): HttpResponse
    {
        return (new HttpResponse())
            ->setContent(null)
            ->setStatus(self::HTTP_CREATED_CODE)
        ;
    }

    /**
     * Действия перед отдачей готового ответа.
     * Используем для формирования нашего формата ответа на исключение и для логирования.
     *
     * @throws ArgumentTypeException
     */
    public function finalizeResponse(HttpResponse|Response $response): void
    {
        // Если было исключение, то перекрываем ответ нашим форматом
        if (null !== $this->thrownException) {
            $responseException = $this->getExceptionResponse();

            $response->setContent($responseException->getContent());
            $response->setStatus($responseException->getStatus());
        }
    }

    /**
     * Сохраняем название Action-метода при создании контроллера и генерируем уникальный id для логов.
     *
     * @throws SystemException
     */
    protected function create($actionName): Action|FallbackAction|InlineAction|null
    {
        $this->actionMethodName = $this->generateActionMethodName($actionName);

        return parent::create($actionName);
    }

    /**
     * Сохраняем исключение, если оно было в процессе работы.
     */
    protected function runProcessingThrowable(\Throwable $throwable): void
    {
        parent::runProcessingThrowable($throwable);

        $this->thrownException = $throwable;
    }

    /**
     * Очищаем дефолтные префильтры (что-то типа middleware) по умолчанию и устанавливаем наши.
     *
     * @return Base[]
     */
    protected function getDefaultPreFilters(): array
    {
        return [
            new LoggerFilter(),
        ];
    }

    /**
     * @return Base[]
     */
    protected function getDefaultPostFilters(): array
    {
        return [
            new LoggerFilter(),
        ];
    }
}
