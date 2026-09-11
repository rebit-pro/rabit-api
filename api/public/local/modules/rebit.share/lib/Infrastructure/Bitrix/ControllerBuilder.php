<?php

namespace Rebit\Share\Infrastructure\Bitrix;

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\ObjectException;
use Rebit\Share\Infrastructure\Controller\AbstractController;
use Rebit\Share\Shared\Facade\Log;

/**
 * Класс добавляет поддержку DI для конструктора контроллера битрикса.
 *
 * @see \Bitrix\Main\Engine\ControllerBuilder
 *
 * Этот класс вызывается ядром, если контроллер битрикса передан как ['class', 'method'].
 * Инжектируемый сервис должен быть зарегистрирован в DI контейнере любым способом.
 *
 * @example Например, в .settings.php проекта (приоритетен) или модуля:
 * 'services' => [
 *      'value' => [
 *          MyService::class => [
 *              'className' => MyService::class || 'constructor' => new MyService(),
 *              'constructorParams' => ['param1', 'param2'],  // не обязательное
 *          ],
 *      ],
 * ],
 */
final class ControllerBuilder
{
    /**
     * @template T of Controller
     *
     * @param class-string<T> $controllerClass
     * @param array{
     *      currentUser?: CurrentUser,
     *      scope?: string,
     * } $options
     *
     * @throws ObjectException
     */
    public static function build(string $controllerClass, array $options): Controller
    {
        try {
            $scope = $options['scope'] ?? Controller::SCOPE_AJAX;
            $currentUser = $options['currentUser'] ?? CurrentUser::get();

            $reflectionClass = self::getReflectionControllerClass($controllerClass);
            $constructorArgs = self::resolveConstructorArguments($reflectionClass);
            /** @var Controller $controller */
            $controller = $reflectionClass->newInstanceArgs($constructorArgs);
            if (!$controller instanceof Controller) {
                throw new \ReflectionException("Can't construct controller {$controllerClass}.");
            }

            $controller->setScope($scope);
            $controller->setCurrentUser($currentUser ?? CurrentUser::get());

            return $controller;
        } catch (\ReflectionException $exception) {
            throw new ObjectException("Unable to construct controller {$controllerClass}.", $exception);
        }
    }

    /**
     * Проверяет, что класс существует, не абстрактный и является потомком Controller
     *
     * @throws ObjectException
     */
    private static function getReflectionControllerClass(string $controllerClass): \ReflectionClass
    {
        $reflectionClass = new \ReflectionClass($controllerClass);

        if ($reflectionClass->isAbstract()) {
            throw new ObjectException('Controller class should be non abstract.');
        }

        if (!$reflectionClass->isSubclassOf(Controller::class)) {
            throw new ObjectException('Controller class should be subclass of \Bitrix\Main\Engine\Controller.');
        }

        return $reflectionClass;
    }

    /**
     * Разрешает аргументы конструктора через контейнер Битрикса.
     * Бросает исключение, если зависимость не найдена в DI.
     *
     * @throws ObjectException
     */
    private static function resolveConstructorArguments(\ReflectionClass $reflectionClass): array
    {
        $constructor = $reflectionClass->getConstructor();
        // внедряем зависимости только в наших контроллерах
        $isOurController = self::isOurController($reflectionClass);

        if (!$constructor || !$isOurController) {
            return [];
        }

        $args = [];
        $serviceLocator = ServiceLocator::getInstance();

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            // Параметры без типа или со скалярным типом не могут быть в конструкторе
            if (!$type || ($type instanceof \ReflectionNamedType && $type->isBuiltin())) {
                throw new ObjectException(
                    sprintf(
                        'Cannot resolve scalar parameter "%s" in constructor of %s. '
                        . 'Only DI-resolvable dependencies are allowed.',
                        $parameter->getName(),
                        $reflectionClass->getName(),
                    ),
                );
            }

            // Ищем наши типы в DI
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();

                try {
                    $args[] = $serviceLocator->get($className);
                } catch (\Throwable $e) {
                    Log::error('Failed to resolve dependency for controller constructor', [
                        'controller' => $reflectionClass->getName(),
                        'parameter' => $parameter->getName(),
                        'dependency' => $className,
                        'error' => $e->getMessage(),
                    ]);
                    throw new ObjectException(
                        sprintf(
                            'Dependency "%s" required by parameter "%s" in %s not found in DI container',
                            $className,
                            $parameter->getName(),
                            $reflectionClass->getName(),
                        ),
                        $e instanceof \Exception ? $e : null,
                    );
                }
            }
        }

        return $args;
    }

    private static function isOurController(\ReflectionClass $reflectionClass): bool
    {
        return $reflectionClass->isSubclassOf(AbstractController::class);
    }
}
