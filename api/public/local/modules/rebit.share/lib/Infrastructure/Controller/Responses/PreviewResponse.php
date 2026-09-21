<?php

declare(strict_types=1);

namespace Rebit\Share\Infrastructure\Controller\Responses;

use Bitrix\Main\HttpResponse;
use Rebit\Share\Application\Contract\File\Dto\PreviewContentOutputDto;

final class PreviewResponse extends HttpResponse
{
    public function __construct(PreviewContentOutputDto $content)
    {
        parent::__construct();
        $this->setContent($content->content);
        $this->addHeader('Content-Type', 'image/webp');
        $this->addHeader('Cache-Control', 'no-store');
        $this->addHeader('X-Content-Type-Options', 'nosniff');
        $this->addHeader('Referrer-Policy', 'no-referrer');
    }
}
