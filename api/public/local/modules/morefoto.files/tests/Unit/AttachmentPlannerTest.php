<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit;

use Morefoto\Files\Domain\Delivery\Service\AttachmentPlanner;
use Morefoto\Files\Domain\Delivery\ValueObject\LetterFile;
use Morefoto\Files\Domain\Delivery\ValueObject\LetterPlan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Notification\EmailNotificationInterface;

require_once __DIR__ . '/../bootstrap.php';

/**
 * J5-T01, J5-T02: оригиналы по письмам — до 18 МБ в письме по порядку кадров, кадр крупнее идёт один, кадр выше
 * предела письма остаётся только по ссылке.
 *
 * @internal
 */
final class AttachmentPlannerTest extends TestCase
{
    private const int MB = 1024 * 1024;

    /**
     * @param list<float|int> $sizes    размеры кадров в МБ по порядку
     * @param list<list<int>> $letters  номера кадров по письмам
     * @param list<int>       $linkOnly номера кадров только по ссылке
     */
    #[DataProvider('orders')]
    public function testLettersKeepTheOrderAndTheLimit(array $sizes, array $letters, array $linkOnly): void
    {
        $files = [];
        foreach ($sizes as $index => $size) {
            $files[] = new LetterFile('p' . $index, 'F' . $index . '.jpg', 'image/jpeg', (int)($size * self::MB), '/media/' . $index . '.jpg');
        }

        $plan = new AttachmentPlanner()->plan($files);

        self::assertSame($letters, array_map(static fn(array $letter): array => array_map(self::index(...), $letter), $plan->letters));
        self::assertSame($linkOnly, array_map(self::index(...), $plan->linkOnly));
        foreach ($plan->letters as $letter) {
            $bytes = array_sum(array_map(static fn(LetterFile $file): int => $file->bytes, $letter));
            self::assertTrue(1 === count($letter) || AttachmentPlanner::PART_BYTES >= $bytes, 'Письмо из нескольких кадров не больше 18 МБ.');
        }
    }

    /** @return iterable<string, array{list<float|int>, list<list<int>>, list<int>}> */
    public static function orders(): iterable
    {
        yield 'one frame' => [[8], [[0]], []];
        yield 'two frames fit one letter' => [[8, 8], [[0, 1]], []];
        yield 'three frames' => [[8, 8, 8], [[0, 1], [2]], []];
        yield 'ten frames — five letters' => [array_fill(0, 10, 8), [[0, 1], [2, 3], [4, 5], [6, 7], [8, 9]], []];
        yield 'exactly the limit' => [[9, 9, 1], [[0, 1], [2]], []];
        yield 'a frame above the part limit goes alone' => [[8, 19, 8], [[0], [1], [2]], []];
        yield 'a frame above the letter limit only by the link' => [[8, 25, 8], [[0, 2]], [1]];
        yield 'small frames fill letters in order' => [[0.5, 17.5, 0.5, 3], [[0, 1], [2, 3]], []];
        yield 'nothing to attach' => [[], [[]], []];
        yield 'all frames too large' => [[21, 30], [[]], [0, 1]];
    }

    public function testThePartLimitIsConfigurableUpToTheLetterLimit(): void
    {
        $files = [new LetterFile('a', 'A.jpg', 'image/jpeg', 300, '/a'), new LetterFile('b', 'B.jpg', 'image/jpeg', 300, '/b')];
        self::assertEquals(new LetterPlan([[$files[0]], [$files[1]]], []), new AttachmentPlanner(1)->plan($files));
        self::assertSame(AttachmentPlanner::FILE_BYTES, EmailNotificationInterface::MAX_ATTACHMENT_BYTES, 'Кадр одного письма помещается в предел H1.');

        $this->expectException(\InvalidArgumentException::class);
        new AttachmentPlanner(AttachmentPlanner::FILE_BYTES + 1);
    }

    private static function index(LetterFile $file): int
    {
        return (int)substr($file->photoId, 1);
    }
}
