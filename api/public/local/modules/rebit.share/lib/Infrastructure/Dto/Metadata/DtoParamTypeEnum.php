<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Dto\Metadata;

enum DtoParamTypeEnum: string
{
    case INT = 'int';
    case FLOAT = 'float';
    case STRING = 'string';
    case BOOL = 'bool';
    case ENUM = 'enum';
    case OBJECT = 'object';
    case OBJECT_ARRAY = 'objectArray';
    case SCALAR_ARRAY = 'scalarArray';
    case ARRAY = 'array';
}
