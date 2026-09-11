<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Interface;

/**
 * Интерфейс для компонентов, которые поддерживают ViewModel
 *
 * Компоненты, реализующие этот интерфейс, предоставляют ViewModel - класс, в котором собраны методы для работы с шаблоном.
 * Это те самые куски кода, которые мы раньше писали в самом шаблоне и в result_modifier.
 * Шаблон должен стать максимально тупым: всё форматирование, переборы данных и пр. логика для подготовки шаблона должна быть тут.
 *
 * ВНИМАНИЕ! Здесь нельзя писать бизнес-логику и логику уровня компонента.
 * Никаких внешних зависимостей! Только чистые функции и чистые хэлперы типа форматеров.
 *
 * Пример реализации метода в class.php:
 * ```
 * public function getViewModel(): SmartFilterViewModel
 * {
 *     return new SmartFilterViewModel($this->getParamsDto(), $this->getResultDto());
 * }
 * ```
 *
 * Пример получения в шаблонах
 * ```
 * /**
 * @ var CatalogSmartFilterFast $component
 * * /
 * $component = $this->__component;
 * $viewModel = $component->getViewModel();
 * ```
 */
interface HasViewModelInterface
{
    public function getViewModel(): object;
}
