<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit;

use Morefoto\Files\Presentation\Files\Request\Dto\CreateDownloadRequestDto;
use Morefoto\Files\Presentation\Files\Request\Dto\DownloadContentRequestDto;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Request\StrictRequestValues;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

require_once __DIR__ . '/../bootstrap.php';

/**
 * FIL-02/04 bodies through the same strict decoding and hydration as the HTTP request (E2E gate of PR #143).
 *
 * @internal
 */
final class FilesRequestContractTest extends TestCase
{
    private const array TECHNICAL = ['idempotencyKey' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'orderKey' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'];

    public function testArchiveWithoutPhotosAndSingleFileBodiesAreAccepted(): void
    {
        $zip = $this->create(['kind' => 'zip']);
        $file = $this->create(['kind' => 'file', 'photoIds' => ['11111111-1111-4111-8111-111111111111']]);

        self::assertSame(['zip', null], [$zip->kind, $zip->photoIds]);
        self::assertSame(['file', ['11111111-1111-4111-8111-111111111111']], [$file->kind, $file->photoIds]);
        self::assertSame(self::TECHNICAL['idempotencyKey'], $zip->idempotencyKey);
    }

    public function testContentTokenComesFromTheQuery(): void
    {
        $values = StrictRequestValues::normalize(['downloadId' => '11111111-1111-4111-8111-111111111111', 'token' => '1790000000.ab'], DownloadContentRequestDto::class, false);

        self::assertSame('1790000000.ab', ArrayToDtoMapper::map($values, DownloadContentRequestDto::class)->token);
    }

    /** @param array<string, mixed> $body */
    private function create(array $body): CreateDownloadRequestDto
    {
        $values = StrictRequestValues::normalize($body + self::TECHNICAL, CreateDownloadRequestDto::class, true);

        return ArrayToDtoMapper::map($values, CreateDownloadRequestDto::class);
    }
}
