<?php

declare(strict_types=1);

namespace Morefoto\Support\Tests\Unit;

use Morefoto\Support\Application\Question\Dto\GalleryQuestionContextDto;
use Morefoto\Support\Application\Question\Dto\StaffQuestionContextDto;
use Morefoto\Support\Application\Question\Service\MaxQuestionTextBuilder;
use Morefoto\Support\Domain\Question\Service\QuestionTextPolicy;
use Morefoto\Support\Infrastructure\Crypto\QuestionKeySeal;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class TextPolicyAndSealTest extends TestCase
{
    public function testNamesAndMessagesArePlainBoundedText(): void
    {
        $policy = new QuestionTextPolicy();

        self::assertSame('Мария Иванова', $policy->name(" Мария \n Иванова "));
        self::assertSame("Строка 1\nСтрока 2", $policy->message("\r\nСтрока 1\r\nСтрока 2  "));
        self::assertSame(str_repeat('я', 2000), $policy->message(str_repeat('я', 2000)));
        self::assertSame('<b>html</b> 😀', $policy->message('<b>html</b> 😀'));
        foreach ([['name', ''], ['name', str_repeat('я', 61)], ['message', '   '], ['message', str_repeat('я', 2001)], ['message', "Звонок\u{0007}"], ['message', "\xff"]] as [$method, $value]) {
            try {
                $policy->{$method}($value);
                self::fail($method . ' accepted an invalid value.');
            } catch (HttpException $error) {
                self::assertSame(422, $error->getCode());
            }
        }
        self::assertNull($policy->curatorReply("  \n "));
        self::assertSame(QuestionTextPolicy::CURATOR_MAX, mb_strlen((string)$policy->curatorReply(str_repeat('я', 5000))));
    }

    public function testContextsNameThePlaceAndTheResponsibleCurator(): void
    {
        $texts = new MaxQuestionTextBuilder();

        self::assertSame('Сад «Солнышко», группа «Пчёлки»', $texts->galleryContext(new GalleryQuestionContextDto(1, 'Солнышко', 'Пчёлки', null)));
        self::assertSame('Воспитатель', $texts->staffContext(new StaffQuestionContextDto(1, 'Ольга', 'teacher', [])));
        self::assertSame('Заведующая · «Сад 1», «Сад 2»', $texts->staffContext(new StaffQuestionContextDto(1, 'Ольга', 'head', ['Сад 1', 'Сад 2'])));
        self::assertStringStartsWith('Вопрос №5 · сотрудник «Ольга»', $texts->text(5, 'staff', 'Ольга', 'Воспитатель', 'Текст'));
    }

    public function testQuestionKeyIsRecoverableOnlyWithTheSameIdempotencyKeyAndScope(): void
    {
        $seal = new QuestionKeySeal();
        $key = str_repeat('ab', 32);
        $sealed = $seal->seal($key, str_repeat('1', 32), 'gallery:x');

        self::assertSame($key, $seal->open($sealed, str_repeat('1', 32), 'gallery:x'));
        self::assertStringNotContainsString($key, $sealed);
        foreach ([[str_repeat('2', 32), 'gallery:x'], [str_repeat('1', 32), 'gallery:y']] as [$idempotencyKey, $scope]) {
            try {
                $seal->open($sealed, $idempotencyKey, $scope);
                self::fail('The sealed key opened with another key or scope.');
            } catch (\RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
