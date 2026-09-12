<?php

declare(strict_types=1);

namespace Morefoto\Organization\Application\Structure\Dto;

use Rebit\Share\Shared\Interface\ResponseDtoInterface;

final readonly class GroupOutputDto implements ResponseDtoInterface
{
    public function __construct(public string $id, public string $shootId, public string $name, public string $groupKind, public int $revision, public ?int $teacherId, public string $status, public string $timezone, public ?string $sentAt, public ?string $closesAt, public ?string $deliveryDueAt) {}
}
