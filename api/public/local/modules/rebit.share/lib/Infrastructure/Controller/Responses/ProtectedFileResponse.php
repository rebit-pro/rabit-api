<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Responses;

use Bitrix\Main\HttpResponse;
use Rebit\Share\Application\Contract\File\Dto\ProtectedFileOutputDto;

/** Передаёт отдачу приватного файла nginx через X-Accel-Redirect: PHP не держит поток, работает докачка Range. */
final class ProtectedFileResponse extends HttpResponse
{
    public function __construct(ProtectedFileOutputDto $file)
    {
        parent::__construct();
        if (!str_starts_with($file->internalUri, '/_protected/') || str_contains($file->internalUri, '..')
            || 1 !== preg_match('/^[A-Za-z0-9._-]+$/D', $file->filename)) {
            throw new \InvalidArgumentException('Unsafe protected file reference.');
        }
        $this->addHeader('X-Accel-Redirect', $file->internalUri);
        $this->addHeader('Content-Type', $file->mimeType);
        $this->addHeader('Content-Disposition', 'attachment; filename="' . $file->filename . '"');
        $this->addHeader('Cache-Control', 'no-store');
        $this->addHeader('X-Content-Type-Options', 'nosniff');
        $this->addHeader('Referrer-Policy', 'no-referrer');
    }
}
