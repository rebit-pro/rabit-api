<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Application\Conditions\Service\ConditionsPayloadHash;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;
use Morefoto\Commerce\Presentation\Request\ConditionsRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * @internal
 */
final class ConditionsApiTest extends TestCase
{
    private const string KEY = '12345678123456781234567812345678';
    private const string FIRST_ID = '11111111-1111-4111-8111-111111111111';
    private const string SECOND_ID = '22222222-2222-4222-8222-222222222222';

    #[DataProvider('invalidPayloads')]
    public function testRejectsUnknownFieldsAndWrongJsonTypes(array $change): void
    {
        $payload = $this->payload();
        foreach ($change as $field => $value) {
            $payload[$field] = $value;
        }

        $this->expectException(InvalidConditionsException::class);
        (new ConditionsRequestFactory())->save(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), 'application/json', self::KEY, [], false);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidPayloads(): iterable
    {
        yield 'numeric revision string' => [['revision' => '1']];
        yield 'float threshold' => [['giftThreshold' => 100.0]];
        yield 'boolean string' => [['giftEnabled' => 'true']];
        yield 'unknown field' => [['actorId' => 12]];
        yield 'products object' => [['products' => new \stdClass()]];
        yield 'disabled gift with threshold' => [['giftEnabled' => false]];
    }

    public function testRejectsUnknownProductFieldsAndWrongProductTypes(): void
    {
        $payload = $this->payload();
        $payload['products'][0]['price'] = '100';
        $payload['products'][0]['unexpected'] = true;

        $this->expectException(InvalidConditionsException::class);
        (new ConditionsRequestFactory())->save(json_encode($payload, JSON_THROW_ON_ERROR), 'application/json', self::KEY, [], false);
    }

    public function testProductAndJsonFieldOrderDoNotChangeIdempotencyHash(): void
    {
        $payload = $this->payload();
        $reordered = array_reverse($payload, true);
        $reordered['products'] = array_reverse(array_map(static fn(array $product): array => array_reverse($product, true), $payload['products']));
        $factory = new ConditionsRequestFactory();
        $first = $factory->save(json_encode($payload, JSON_THROW_ON_ERROR), 'application/json', self::KEY, [], false);
        $second = $factory->save(json_encode($reordered, JSON_THROW_ON_ERROR), 'application/json; charset=UTF-8', self::KEY, [], false);

        self::assertSame((new ConditionsPayloadHash())->create($first->input), (new ConditionsPayloadHash())->create($second->input));
    }

    public function testGroupInheritanceAcceptsCanonicalEmptyOverridePayload(): void
    {
        $payload = $this->payload();
        $payload['conditionsRevision'] = 3;
        $payload['inherit'] = true;
        $payload['products'] = [];
        $payload['giftEnabled'] = false;
        $payload['giftThreshold'] = 0;
        $payload['giftForStaff'] = false;
        $request = (new ConditionsRequestFactory())->save(json_encode($payload, JSON_THROW_ON_ERROR), 'application/json', self::KEY, [], true);

        self::assertSame(3, $request->input->conditionsRevision);
        self::assertTrue($request->input->inherit);
        self::assertSame([], $request->input->products);
    }

    public function testReadRejectsBodyAndQuery(): void
    {
        $this->expectException(InvalidConditionsException::class);
        (new ConditionsRequestFactory())->read('{}', ['revision' => '1']);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'revision' => 1,
            'catalogRevision' => 2,
            'products' => [
                ['id' => self::FIRST_ID, 'price' => 100, 'active' => true, 'staffDiscount' => false],
                ['id' => self::SECOND_ID, 'price' => 200, 'active' => true, 'staffDiscount' => true],
            ],
            'giftEnabled' => true,
            'giftThreshold' => 300,
            'giftForStaff' => false,
        ];
    }
}
