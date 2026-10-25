<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Responses;

use Bitrix\Main\HttpResponse;
use Rebit\Share\Application\Contract\File\Dto\PdfFileOutputDto;

/** PDF-документ, собранный приложением (отчёт): отдаётся вложением и не кешируется. */
final class PdfFileResponse extends HttpResponse
{
    public function __construct(PdfFileOutputDto $file)
    {
        parent::__construct();
        if (1 !== preg_match('/^[A-Za-z0-9._-]+\.pdf$/D', $file->filename) || !str_starts_with($file->content, '%PDF-')) {
            throw new \InvalidArgumentException('Unsafe PDF file reference.');
        }
        $this->setContent($file->content);
        $this->addHeader('Content-Type', 'application/pdf');
        $this->addHeader('Content-Disposition', 'attachment; filename="' . $file->filename . '"');
        $this->addHeader('Cache-Control', 'no-store');
        $this->addHeader('X-Content-Type-Options', 'nosniff');
    }
}
