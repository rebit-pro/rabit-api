<?php

declare(strict_types=1);

namespace Morefoto\Files\Presentation\Controller;

use Morefoto\Files\Application\Files\UseCase\GetDownloadUseCase;
use Morefoto\Files\Application\Files\UseCase\GetOrderFilesUseCase;
use Morefoto\Files\Application\Files\UseCase\OpenDownloadContentUseCase;
use Morefoto\Files\Application\Files\UseCase\RequestDownloadUseCase;
use Morefoto\Files\Presentation\Files\FilesInputMapper;
use Morefoto\Files\Presentation\Files\FilesResultMapper;
use Morefoto\Files\Presentation\Files\Request\Dto\CreateDownloadRequestDto;
use Morefoto\Files\Presentation\Files\Request\Dto\DownloadContentRequestDto;
use Morefoto\Files\Presentation\Files\Request\Dto\DownloadRequestDto;
use Morefoto\Files\Presentation\Files\Request\Dto\OrderFilesRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\PrivateApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\ProtectedFileResponse;

final class PublicFileController extends PrivateApiJsonController
{
    public function __construct(
        private readonly GetOrderFilesUseCase $files,
        private readonly RequestDownloadUseCase $requestDownload,
        private readonly GetDownloadUseCase $download,
        private readonly OpenDownloadContentUseCase $content,
        private readonly FilesInputMapper $input,
        private readonly FilesResultMapper $result,
    ) {
        parent::__construct();
    }

    public function filesAction(OrderFilesRequestDto $request): ControllerJson
    {
        return $this->json($this->result->files($this->files->execute($request->orderKey)));
    }

    public function createAction(CreateDownloadRequestDto $request): ControllerJson
    {
        $output = $this->requestDownload->execute($request->orderKey, $this->input->create($request));

        return $this->json($this->result->download($output))->setStatus(self::HTTP_ACCEPTED_CODE);
    }

    public function downloadAction(DownloadRequestDto $request): ControllerJson
    {
        return $this->json($this->result->download($this->download->execute($request->orderKey, $request->downloadId)));
    }

    public function contentAction(DownloadContentRequestDto $request): ProtectedFileResponse
    {
        return $this->protectedFile($this->content->execute($request->downloadId, $request->orderKey, $request->token));
    }
}
