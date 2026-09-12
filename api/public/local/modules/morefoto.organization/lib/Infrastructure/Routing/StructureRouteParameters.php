<?php

declare(strict_types=1);

namespace Morefoto\Organization\Infrastructure\Routing;

use Bitrix\Main\Application;
use Morefoto\Organization\Domain\Structure\ValueObject\StructureId;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class StructureRouteParameters
{
    public function id(string $name): StructureId
    {
        $application = Application::getInstance();
        if (!$application->hasCurrentRoute()) {
            throw new HttpException('INVALID_ROUTE', 400);
        }
        // The matched route is authoritative: neither query parameters nor JSON can override it.
        $value = $application->getCurrentRoute()->getParameterValue($name);
        if (!is_string($value)) {
            throw new HttpException('INVALID_ROUTE', 400);
        }

        return new StructureId($value);
    }
}
