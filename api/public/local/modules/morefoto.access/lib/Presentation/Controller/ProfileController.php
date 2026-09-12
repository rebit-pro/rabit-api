<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Controller;

use Morefoto\Access\Application\Profile\UseCase\GetProfileUseCase;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerInterface;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerTrait;
use Rebit\Share\Infrastructure\Controller\BaseJsonController;
use Rebit\Share\Infrastructure\Controller\Filters\BearerTokenFilter;
use Rebit\Share\Infrastructure\Controller\Filters\LoggerFilter;

final class ProfileController extends BaseJsonController implements AuthenticatedControllerInterface
{
    use AuthenticatedControllerTrait;

    public function __construct(
        private readonly GetProfileUseCase $profile,
        private readonly TokenResolverInterface $tokens,
    ) {
        parent::__construct();
    }

    public function meAction(): ControllerJson
    {
        return $this->json($this->profile->execute($this->getAuthUserId()));
    }

    public function configureActions(): array
    {
        return ['me' => ['prefilters' => [new BearerTokenFilter($this->tokens), new LoggerFilter()]]];
    }
}
