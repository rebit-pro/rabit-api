<?php

declare(strict_types=1);

namespace Morefoto\Files\Application\Files\UseCase;

use Morefoto\Files\Application\Files\Dto\DownloadOutputDto;
use Morefoto\Files\Application\Files\Service\DownloadView;
use Morefoto\Files\Application\Files\Service\OrderFileAccess;
use Morefoto\Files\Domain\Download\Repository\DownloadRepositoryInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Shared\Exception\HttpException;

/** FIL-03: сообщает покупателю, готова ли его загрузка, и выдаёт свежую короткую ссылку на содержимое готовой загрузки. */
final readonly class GetDownloadUseCase
{
    public function __construct(
        private OrderFileAccess $access,
        private DownloadRepositoryInterface $downloads,
        private DownloadView $view,
        private ClockInterface $clock,
    ) {}

    public function execute(?string $orderKey, string $downloadId): DownloadOutputDto
    {
        $access = $this->access->byKey($orderKey);
        $download = $this->downloads->find($downloadId);
        if (null === $download || $download->orderId !== $access->orderId) {
            throw new HttpException('DOWNLOAD_NOT_FOUND', 404);
        }

        return $this->view->output($download, $access, $this->clock->now());
    }
}
