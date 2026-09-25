<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Context;

use Morefoto\Support\Application\Question\Contract\StaffQuestionContextInterface;
use Morefoto\Support\Application\Question\Dto\StaffQuestionContextDto;
use Rebit\Share\Contracts\Access\StaffRequestAccessInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** Активный сотрудник учреждения из Access и названия учреждений его области из каталога групп Organization. */
final readonly class StaffQuestionContext implements StaffQuestionContextInterface
{
    private const array ROLES = ['head', 'teacher'];

    public function __construct(
        private StaffRequestAccessInterface $access,
        private GroupDirectoryInterface $directory,
    ) {}

    public function resolve(int $userId): StaffQuestionContextDto
    {
        $actor = $this->access->actor($userId);
        if (!in_array($actor->role, self::ROLES, true)) {
            throw new HttpException('FORBIDDEN', 403);
        }
        $head = 'head' === $actor->role;
        $scope = $head ? $actor->institutionIds : $actor->groupIds;
        $names = [];
        if ([] !== $scope) {
            $page = $this->directory->page(new GroupDirectoryQueryInputDto($head ? $scope : null, $head ? null : $scope, null, null, null, 1, 100));
            foreach ($page->items as $item) {
                $names[$item->institutionName] = $item->institutionName;
            }
        }

        return new StaffQuestionContextDto($actor->id, $actor->name, $actor->role, array_values($names));
    }
}
