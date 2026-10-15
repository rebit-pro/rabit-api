<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Responses;

use Bitrix\Main\HttpResponse;
use Rebit\Share\Application\Contract\File\Dto\TextFileOutputDto;

/** Небольшой текстовый файл, собранный приложением (выгрузка CSV): отдаётся вложением и не кешируется. */
final class TextFileResponse extends HttpResponse
{
    public function __construct(TextFileOutputDto $file)
    {
        parent::__construct();
        if (1 !== preg_match('/^[A-Za-z0-9._-]+$/D', $file->filename) || 1 !== preg_match('~^text/[a-z.+-]+(; charset=utf-8)?$~D', $file->mimeType)) {
            throw new \InvalidArgumentException('Unsafe text file reference.');
        }
        $this->setContent($file->content);
        $this->addHeader('Content-Type', $file->mimeType);
        $this->addHeader('Content-Disposition', 'attachment; filename="' . $file->filename . '"');
        $this->addHeader('Cache-Control', 'no-store');
        $this->addHeader('X-Content-Type-Options', 'nosniff');
    }
}
