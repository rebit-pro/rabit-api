<?php

declare(strict_types=1);

namespace Rebit\Share\Presentation\Controller;

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Engine\ActionFilter\Base;
use Psr\Container\NotFoundExceptionInterface;
use Rebit\Share\Application\UseCase\UploadFileUseCase;
use Rebit\Share\Application\Contract\Auth\TokenResolverInterface;
use Rebit\Share\Domain\File\Dto\Request\UploadRequestFileRequestDto;
use Rebit\Share\Domain\File\Exception\FileUploadFailedException;
use Rebit\Share\Domain\File\Exception\InvalidFileException;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerInterface;
use Rebit\Share\Infrastructure\Controller\Auth\AuthenticatedControllerTrait;
use Rebit\Share\Infrastructure\Controller\BaseJsonController;
use Rebit\Share\Infrastructure\Controller\Filters\BearerTokenFilter;
use Rebit\Share\Infrastructure\Controller\Filters\LoggerFilter;

final class FileController extends BaseJsonController implements AuthenticatedControllerInterface
{
    use AuthenticatedControllerTrait;

    public function __construct(
        private readonly UploadFileUseCase $uploadFile,
    ) {
        parent::__construct();
    }

    /**
     * @return Base[]
     *
     * @throws NotFoundExceptionInterface
     */
    protected function getDefaultPreFilters(): array
    {
        return [
            new BearerTokenFilter(
                ServiceLocator::getInstance()->get(TokenResolverInterface::class),
            ),
            new LoggerFilter(),
        ];
    }

    /**
     * @throws InvalidFileException
     * @throws FileUploadFailedException
     */
    public function uploadAction(UploadRequestFileRequestDto $dto): ControllerJson
    {
        return $this->json($this->uploadFile->handle($dto, $this->getAuthUserId()));
    }
}
