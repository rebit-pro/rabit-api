<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsMutationOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\PaymentCostsInputDto;
use Morefoto\Commerce\Application\Conditions\Dto\ProductConditionInputDto;
use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsInputValidator;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsPayloadHash;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsProducts;
use Morefoto\Commerce\Application\Conditions\Service\PublishedPrices;
use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;
use Morefoto\Commerce\Domain\Conditions\ValueObject\PaymentCostPolicy;
use Morefoto\Commerce\Presentation\Conditions\ConditionsInputMapper;
use Morefoto\Commerce\Presentation\Conditions\ConditionsResultMapper;
use Morefoto\Commerce\Presentation\Conditions\Dto\SaveGlobalConditionsRequestDto;
use Morefoto\Commerce\Presentation\Conditions\Dto\SaveGroupConditionsRequestDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Request\StrictRequestValues;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * @internal
 */
final class ConditionsContractTest extends TestCase
{
    private const string KEY = '12345678123456781234567812345678';
    private const string FIRST_ID = '11111111-1111-4111-8111-111111111111';
    private const string SECOND_ID = '22222222-2222-4222-8222-222222222222';
    private const string GROUP_ID = '33333333-3333-4333-8333-333333333333';

    /** @param array<string, mixed> $change */
    #[DataProvider('invalidPayloads')]
    public function testStrictJsonRejectsCoercionAndUnknownFields(array $change, string $code): void
    {
        $payload = $this->payload();
        foreach ($change as $field => $value) {
            if (null === $value) {
                unset($payload[$field]);
            } else {
                $payload[$field] = $value;
            }
        }

        $this->assertCode($code, fn(): object => $this->request(SaveGlobalConditionsRequestDto::class, $payload));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'numeric revision string' => [['revision' => '1'], 'VALIDATION_FAILED'];
        yield 'float threshold' => [['giftThreshold' => 100.5], 'VALIDATION_FAILED'];
        yield 'boolean string' => [['giftEnabled' => 'true'], 'VALIDATION_FAILED'];
        yield 'unknown field' => [['actorId' => 12], 'UNKNOWN_FIELD'];
        yield 'products object' => [['products' => ['a' => 1]], 'VALIDATION_FAILED'];
        yield 'missing payment costs' => [['paymentCosts' => null], 'UNKNOWN_FIELD'];
        yield 'extra payment cost field' => [['paymentCosts' => ['enabled' => true, 'rateBps' => 380, 'roundingStep' => 5000]], 'UNKNOWN_FIELD'];
        yield 'payment cost rate string' => [['paymentCosts' => ['enabled' => true, 'rateBps' => '380']], 'VALIDATION_FAILED'];
    }

    public function testStrictJsonChecksNestedProductsAndGroupShape(): void
    {
        $payload = $this->payload();
        $payload['products'][0]['price'] = '100';
        $this->assertCode('VALIDATION_FAILED', fn(): object => $this->request(SaveGlobalConditionsRequestDto::class, $payload));
        $payload = $this->payload();
        $payload['products'][0]['unexpected'] = true;
        $this->assertCode('UNKNOWN_FIELD', fn(): object => $this->request(SaveGlobalConditionsRequestDto::class, $payload));
        $this->assertCode('UNKNOWN_FIELD', fn(): object => $this->request(SaveGroupConditionsRequestDto::class, $this->groupPayload() + ['paymentCosts' => ['enabled' => false, 'rateBps' => 380]]));
    }

    public function testMapsGlobalAndGroupRequestsToPureInput(): void
    {
        $mapper = new ConditionsInputMapper();
        $request = $this->request(SaveGlobalConditionsRequestDto::class, $this->payload());
        self::assertInstanceOf(SaveGlobalConditionsRequestDto::class, $request);
        $global = $mapper->saveGlobal($request);
        self::assertEquals(new PaymentCostsInputDto(true, 380), $global->paymentCosts);
        self::assertEquals([new ProductConditionInputDto(self::FIRST_ID, 100, true, false), new ProductConditionInputDto(self::SECOND_ID, 200, true, true)], $global->products);
        self::assertNull($global->conditionsRevision);
        self::assertSame(self::KEY, $request->idempotencyKey);

        $groupRequest = $this->request(SaveGroupConditionsRequestDto::class, $this->groupPayload());
        self::assertInstanceOf(SaveGroupConditionsRequestDto::class, $groupRequest);
        $group = $mapper->saveGroup($groupRequest);
        self::assertSame([3, true, [], null], [$group->conditionsRevision, $group->inherit, $group->products, $group->paymentCosts]);
        self::assertSame(self::GROUP_ID, $groupRequest->groupId);
        self::assertSame('session-token', $mapper->token('Bearer session-token'));
        self::assertSame('', $mapper->token('Basic session-token'));
    }

    public function testProductAndJsonFieldOrderDoNotChangeIdempotencyHash(): void
    {
        $payload = $this->payload();
        $reordered = array_reverse($payload, true);
        $reordered['products'] = array_reverse(array_map(static fn(array $product): array => array_reverse($product, true), $payload['products']));
        $mapper = new ConditionsInputMapper();
        $first = $this->request(SaveGlobalConditionsRequestDto::class, $payload);
        $second = $this->request(SaveGlobalConditionsRequestDto::class, $reordered);
        self::assertInstanceOf(SaveGlobalConditionsRequestDto::class, $first);
        self::assertInstanceOf(SaveGlobalConditionsRequestDto::class, $second);

        self::assertSame((new ConditionsPayloadHash())->create($mapper->saveGlobal($first)), (new ConditionsPayloadHash())->create($mapper->saveGlobal($second)));
    }

    public function testGroupHashKeepsItsShapeAndGlobalHashIncludesPolicy(): void
    {
        $hashes = new ConditionsPayloadHash();
        $group = new SaveConditionsInputDto(1, 2, [new ProductConditionInputDto(self::FIRST_ID, 100, true, false)], true, 300, false, 3, false);
        $legacy = hash('sha256', json_encode([
            'revision' => 1, 'catalogRevision' => 2, 'conditionsRevision' => 3, 'inherit' => false,
            'products' => [[self::FIRST_ID, 100, true, false]], 'giftEnabled' => true, 'giftThreshold' => 300, 'giftForStaff' => false,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        self::assertSame($legacy, $hashes->create($group));

        $first = new SaveConditionsInputDto(1, 2, [], false, 0, false, paymentCosts: new PaymentCostsInputDto(true, 380));
        $second = new SaveConditionsInputDto(1, 2, [], false, 0, false, paymentCosts: new PaymentCostsInputDto(true, 390));
        self::assertNotSame($hashes->create($first), $hashes->create($second));
    }

    #[DataProvider('invalidInputs')]
    public function testValidatorRejectsInconsistentInput(SaveConditionsInputDto $input): void
    {
        $this->expectException(InvalidConditionsException::class);
        (new ConditionsInputValidator())->validate($input);
    }

    /** @return iterable<string, array{SaveConditionsInputDto}> */
    public static function invalidInputs(): iterable
    {
        yield 'negative revision' => [new SaveConditionsInputDto(-1, 1, [], false, 0, false)];
        yield 'zero catalogue revision' => [new SaveConditionsInputDto(0, 0, [], false, 0, false)];
        yield 'zero conditions revision' => [new SaveConditionsInputDto(0, 1, [], false, 0, false, 0)];
        yield 'threshold above INT' => [new SaveConditionsInputDto(0, 1, [], true, 2147483648, false)];
        yield 'enabled gift without threshold' => [new SaveConditionsInputDto(0, 1, [], true, 0, false)];
        yield 'disabled gift with threshold' => [new SaveConditionsInputDto(0, 1, [], false, 300, false)];
        yield 'disabled gift for staff' => [new SaveConditionsInputDto(0, 1, [], false, 0, true)];
    }

    public function testValidatorAcceptsConsistentInputAndReportsEffectiveThreshold(): void
    {
        $validator = new ConditionsInputValidator();
        $enabled = new SaveConditionsInputDto(0, 1, [], true, 300, true, 2);
        $validator->validate($enabled);
        self::assertSame(300, $validator->giftThreshold($enabled));
        self::assertSame(0, $validator->giftThreshold(new SaveConditionsInputDto(0, 1, [], false, 0, false)));
    }

    /** @param list<ProductConditionInputDto> $products */
    #[DataProvider('invalidProductSets')]
    public function testProductSetMustMatchTheCatalogue(array $products, bool $group, bool $gift): void
    {
        $this->expectException(InvalidConditionsException::class);
        (new ConditionsProducts())->validate(new SaveConditionsInputDto(0, 1, $products, $gift, $gift ? 300 : 0, false), $this->catalogue(), $group);
    }

    /** @return iterable<string, array{list<ProductConditionInputDto>, bool, bool}> */
    public static function invalidProductSets(): iterable
    {
        $first = new ProductConditionInputDto(self::FIRST_ID, 100, true, false);
        yield 'duplicate product' => [[$first, $first], false, false];
        yield 'missing product' => [[$first], false, false];
        yield 'unknown product' => [[$first, new ProductConditionInputDto('44444444-4444-4444-8444-444444444444', 1, false, false)], false, false];
        yield 'invalid product ID' => [[$first, new ProductConditionInputDto('not-a-uuid', 1, false, false)], false, false];
        yield 'price above 1 000 000 RUB' => [[$first, new ProductConditionInputDto(self::SECOND_ID, 100000001, false, false)], false, false];
        yield 'group enables a globally disabled product' => [[$first, new ProductConditionInputDto(self::SECOND_ID, 200, true, false)], true, false];
        yield 'gift without an active bundle' => [[$first, new ProductConditionInputDto(self::SECOND_ID, 200, false, false)], false, true];
    }

    public function testValidProductSetKeepsCatalogueOrderAndPriceCap(): void
    {
        $products = (new ConditionsProducts())->validate(new SaveConditionsInputDto(0, 1, [
            new ProductConditionInputDto(self::SECOND_ID, 100000000, true, false),
            new ProductConditionInputDto(self::FIRST_ID, 100, true, true),
        ], true, 300, false), $this->catalogue(), false);

        self::assertSame([self::FIRST_ID, self::SECOND_ID], [$products[0]->id->value, $products[1]->id->value]);
        self::assertSame(100000000, $products[1]->price);
    }

    public function testPublishReplacesOnlyThePrice(): void
    {
        $prices = new PublishedPrices();
        $policy = new PaymentCostPolicy(true, 380);
        $products = $this->catalogue();
        $published = $prices->publish(new ConditionsOutputDto(1, 1, 1, false, $products, 0, false, $prices->output($policy), $prices->salePrices($products, $policy)));

        self::assertSame([30000, 105000], [$published[0]->price, $published[1]->price]);
        self::assertSame([25000, 100000], [$products[0]->price, $products[1]->price]);
        self::assertSame([ProductKind::PHYSICAL, false], [$published[0]->kind, $published[1]->active]);
    }

    public function testResponsesExposeBaseAndSalePriceWithPolicy(): void
    {
        $prices = new PublishedPrices();
        $policy = new PaymentCostPolicy(true, 380);
        $products = [$this->catalogue()[0]];
        $output = new ConditionsOutputDto(4, 5, 4, false, $products, 0, false, $prices->output($policy), $prices->salePrices($products, $policy));
        $serializer = CommonSerializer::createDefault();
        $mapper = new ConditionsResultMapper();
        $expected = [
            'revision' => 4,
            'catalogRevision' => 5,
            'products' => [[
                'id' => self::FIRST_ID, 'name' => 'Фото 10×15', 'description' => '', 'kind' => 'physical', 'price' => 25000, 'salePrice' => 30000,
                'printCount' => 1, 'format' => '10x15', 'unit' => 'шт', 'staffDiscount' => true, 'active' => true,
            ]],
            'giftThreshold' => 0,
            'giftForStaff' => false,
            'paymentCosts' => ['enabled' => true, 'rateBps' => 380, 'roundingStep' => 5000, 'maxRateBps' => 1000],
        ];

        self::assertSame($expected, json_decode($serializer->serialize($mapper->global($output)), true, 16, JSON_THROW_ON_ERROR));
        self::assertSame($expected + ['conditionsRevision' => 4, 'inherit' => false], json_decode($serializer->serialize($mapper->group($output)), true, 16, JSON_THROW_ON_ERROR));
        self::assertSame(['revision' => 7], json_decode($serializer->serialize($mapper->savedGlobal(new ConditionsMutationOutputDto(7, 8, 7))), true, 4, JSON_THROW_ON_ERROR));
        self::assertSame(['revision' => 2, 'conditionsRevision' => 7], json_decode($serializer->serialize($mapper->savedGroup(new ConditionsMutationOutputDto(2, 8, 7))), true, 4, JSON_THROW_ON_ERROR));
    }

    /** @return list<ProductOutputDto> */
    private function catalogue(): array
    {
        return [
            new ProductOutputDto(self::FIRST_ID, 'Фото 10×15', '', ProductKind::PHYSICAL, 25000, 1, '10x15', 'шт', true, true),
            new ProductOutputDto(self::SECOND_ID, 'Комплект', '', ProductKind::BUNDLE, 100000, 0, '', '', false, false),
        ];
    }

    /**
     * @param class-string         $class
     * @param array<string, mixed> $payload
     */
    private function request(string $class, array $payload): object
    {
        $decoded = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), false, 16, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(\stdClass::class, $decoded);
        $values = get_object_vars($decoded) + ['idempotencyKey' => self::KEY, 'authorization' => 'Bearer session-token'];
        if (SaveGroupConditionsRequestDto::class === $class) {
            $values += ['groupId' => self::GROUP_ID];
        }

        return ArrayToDtoMapper::map(StrictRequestValues::normalize($values, $class, true), $class);
    }

    /** @param callable(): mixed $operation */
    private function assertCode(string $code, callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected ' . $code);
        } catch (HttpException $exception) {
            self::assertSame($code, $exception->getMessage());
        }
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
            'paymentCosts' => ['enabled' => true, 'rateBps' => 380],
        ];
    }

    /** @return array<string, mixed> */
    private function groupPayload(): array
    {
        return [
            'revision' => 0,
            'catalogRevision' => 2,
            'conditionsRevision' => 3,
            'inherit' => true,
            'products' => [],
            'giftEnabled' => false,
            'giftThreshold' => 0,
            'giftForStaff' => false,
        ];
    }
}
