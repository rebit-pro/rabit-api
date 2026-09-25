<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Infrastructure\Controller\Request;

use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Request\StrictRequestValues;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

/**
 * @internal
 */
final class MixedArrayRequestTest extends TestCase
{
    public function testMixedListKeepsItemsForThePresentationMapper(): void
    {
        $values = StrictRequestValues::normalize(['ids' => ['a', 7, null]], MixedListFixtureRequestDto::class, true);
        $dto = ArrayToDtoMapper::map($values, MixedListFixtureRequestDto::class);

        self::assertSame(['a', 7, null], $dto->ids);
    }

    public function testMixedListStillRequiresAList(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('VALIDATION_FAILED');

        StrictRequestValues::normalize(['ids' => new \stdClass()], MixedListFixtureRequestDto::class, true);
    }
}

/** @internal */
final readonly class MixedListFixtureRequestDto
{
    public function __construct(
        /** @var mixed[] */
        public array $ids,
    ) {}
}
