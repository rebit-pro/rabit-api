<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Morefoto\Commerce\Application\Catalog\Dto\ProductOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsOutputDto;
use Morefoto\Commerce\Application\Conditions\Service\PublishedPrices;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGroupConditionsUseCase;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;
use Morefoto\Commerce\Application\Storefront\Service\StorefrontQuote;
use Morefoto\Commerce\Domain\Catalog\Enum\ProductKind;
use Morefoto\Commerce\Domain\Conditions\ValueObject\PaymentCostPolicy;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Handoff\StaffEligibilityInterface;
use Rebit\Share\Contracts\Media\Dto\GalleryAccessOutputDto;
use Rebit\Share\Contracts\Media\Dto\GalleryAssignmentOutputDto;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Contracts\Organization\Dto\GalleryGroupOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class StorefrontQuoteTest extends TestCase
{
    private const string PRINT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const string BUNDLE = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const string A = '11111111-1111-4111-8111-111111111111';
    private const string B = '22222222-2222-4222-8222-222222222222';

    public function testSharedPhotoDoesNotCombineGiftThresholdsOfTwoChildren(): void
    {
        $calculator = $this->calculator();
        $result = $calculator->calculate(str_repeat('a', 64), [
            new QuoteLineInputDto(self::A, self::PRINT, 1),
            new QuoteLineInputDto(self::B, self::PRINT, 1),
            new QuoteLineInputDto(self::A, self::BUNDLE, 1),
        ]);
        self::assertSame([], $result['quote']['gifts']);
        self::assertSame(70000, $result['quote']['total']);
        self::assertSame('A', $result['quote']['lines'][0]['childCode']);
        self::assertSame('B', $result['quote']['lines'][1]['childCode']);
        self::assertSame($result['quote']['lines'][0]['photoId'], $result['quote']['lines'][1]['photoId']);
        $gift = $calculator->calculate(str_repeat('a', 64), [
            new QuoteLineInputDto(self::A, self::PRINT, 2),
            new QuoteLineInputDto(self::A, self::BUNDLE, 1),
        ]);
        self::assertSame(['A'], $gift['quote']['gifts']);
        self::assertSame(20000, $gift['quote']['total']);
        self::assertSame(50000, $gift['quote']['giftSaving']);
    }

    public function testUnknownAssignmentCannotBeReplacedByPhotoOrChildId(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('INVALID_CART');
        $this->calculator()->calculate(str_repeat('a', 64), [new QuoteLineInputDto('photo-id', self::PRINT, 1)]);
    }

    public function testStaffGalleryRequiresTrustedEligibility(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('STAFF_ELIGIBILITY_REQUIRED');
        $this->calculator(staff: true)->calculate(str_repeat('a', 64), [new QuoteLineInputDto(self::A, self::PRINT, 1)]);
    }

    public function testConfirmedStaffUsesHalfPriceRoundedUp(): void
    {
        $result = $this->calculator(staff: true, eligible: [1 => true], price: 10001)
            ->calculate(str_repeat('a', 64), [new QuoteLineInputDto(self::A, self::PRINT, 1)])
        ;
        self::assertSame(5001, $result['quote']['total']);
        self::assertSame(5000, $result['quote']['discount']);
    }

    public function testClosedGalleryCannotCreateQuote(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GALLERY_CLOSED');
        $this->calculator(state: 'closed')->calculate(str_repeat('a', 64), []);
    }

    public function testFingerprintChangesWhenPhotoRevisionOrConditionsChange(): void
    {
        $lines = [new QuoteLineInputDto(self::A, self::PRINT, 1)];
        $base = $this->calculator()->calculate(str_repeat('a', 64), $lines);
        self::assertNotSame($base['fingerprint'], $this->calculator(photoRevision: 2)->calculate(str_repeat('a', 64), $lines)['fingerprint']);
        self::assertNotSame($base['fingerprint'], $this->calculator(price: 11000)->calculate(str_repeat('a', 64), $lines)['fingerprint']);
    }

    public function testQuoteChargesPublishedPriceAndStaffHalfIsNotRoundedAgain(): void
    {
        $result = $this->calculator(staff: true, eligible: [1 => true], price: 50000, policy: new PaymentCostPolicy(true, 380))
            ->calculate(str_repeat('a', 64), [new QuoteLineInputDto(self::A, self::PRINT, 1)])
        ;
        self::assertSame(55000, $result['quote']['lines'][0]['product']['price']);
        self::assertSame(27500, $result['quote']['lines'][0]['unitPrice']);
        self::assertSame(55000, $result['quote']['subtotal']);
        self::assertSame(27500, $result['quote']['total']);
    }

    public function testGiftThresholdCountsPublishedPrintPrices(): void
    {
        $lines = [new QuoteLineInputDto(self::A, self::PRINT, 1), new QuoteLineInputDto(self::A, self::BUNDLE, 1)];
        $plain = $this->calculator(price: 19000)->calculate(str_repeat('a', 64), $lines)['quote'];
        $published = $this->calculator(price: 19000, policy: new PaymentCostPolicy(true, 380))->calculate(str_repeat('a', 64), $lines)['quote'];

        self::assertSame([], $plain['gifts']);
        self::assertSame(69000, $plain['total']);
        self::assertSame(['A'], $published['gifts']);
        self::assertSame(55000, $published['giftSaving']);
        self::assertSame(20000, $published['total']);
    }

    public function testFingerprintChangesWithPaymentCostPolicyEvenWhenPricesMatch(): void
    {
        $lines = [new QuoteLineInputDto(self::A, self::PRINT, 1)];
        $first = $this->calculator(policy: new PaymentCostPolicy(true, 380))->calculate(str_repeat('a', 64), $lines);
        $second = $this->calculator(policy: new PaymentCostPolicy(true, 390))->calculate(str_repeat('a', 64), $lines);
        $disabled = $this->calculator()->calculate(str_repeat('a', 64), $lines);

        self::assertSame($first['quote']['total'], $second['quote']['total']);
        self::assertNotSame($first['fingerprint'], $second['fingerprint']);
        self::assertNotSame($first['fingerprint'], $disabled['fingerprint']);
    }

    public function testDuplicateLineIsRejectedInsteadOfSilentlyMultiplyingPrice(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('DUPLICATE_CART_LINE');
        $line = new QuoteLineInputDto(self::A, self::PRINT, 1);
        $this->calculator()->calculate(str_repeat('a', 64), [$line, $line]);
    }

    public function testExplicitlyDeniedStaffCannotReceiveDiscount(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('STAFF_ELIGIBILITY_REQUIRED');
        $this->calculator(staff: true, eligible: [1 => false])->calculate(str_repeat('a', 64), [new QuoteLineInputDto(self::A, self::PRINT, 1)]);
    }

    public function testDigitalPhotoCannotBeChargedAlongsideItsBundle(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('DIGITAL_ALREADY_IN_BUNDLE');
        $this->calculator()->calculate(str_repeat('a', 64), [
            new QuoteLineInputDto(self::A, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 1),
            new QuoteLineInputDto(self::A, self::BUNDLE, 1),
        ]);
    }

    public function testDigitalQuantityIsStrict(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('INVALID_CART');
        $this->calculator()->calculate(str_repeat('a', 64), [new QuoteLineInputDto(self::A, self::BUNDLE, 2)]);
    }

    /** @param array<int,bool> $eligible */
    private function calculator(bool $staff = false, array $eligible = [], string $state = 'open', int $price = 10000, int $photoRevision = 1, ?PaymentCostPolicy $policy = null): StorefrontQuote
    {
        $access = $this->createStub(GalleryAccessInterface::class);
        $access->method('resolve')->willReturn(new GalleryAccessOutputDto(
            new GalleryGroupOutputDto(1, 2, self::A, 'Institution', 'Shoot', 'Group', $staff ? 'staff' : 'regular', '2026-09-21 00:00:00', '2026-09-28 00:00:00', 1),
            $state,
            '2026-09-21T10:00:00Z',
            1,
            [
                new GalleryAssignmentOutputDto(self::A, 'photo-id', 'child-a', 1, 'A', 'A001', 100, 100, $photoRevision),
                new GalleryAssignmentOutputDto(self::B, 'photo-id', 'child-b', 2, 'B', 'B001', 100, 100, $photoRevision),
            ],
        ));
        $products = [
            new ProductOutputDto(self::PRINT, 'Print', '', ProductKind::PHYSICAL, $price, 1, '', '', true, true),
            new ProductOutputDto('cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'Digital', '', ProductKind::DIGITAL, 10000, 0, '', '', true, true),
            new ProductOutputDto(self::BUNDLE, 'Bundle', '', ProductKind::BUNDLE, 50000, 0, '', '', true, true),
        ];
        $prices = new PublishedPrices();
        $policy ??= new PaymentCostPolicy(false, 380);
        $conditions = $this->createStub(GetGroupConditionsUseCase::class);
        $conditions->method('executeWithinTransaction')->willReturn(
            new ConditionsOutputDto(0, 1, 1, true, $products, 20000, false, $prices->output($policy), $prices->salePrices($products, $policy)),
        );
        $eligibility = $this->createStub(StaffEligibilityInterface::class);
        $eligibility->method('confirmed')->willReturn($eligible);

        return new StorefrontQuote($access, $conditions, $eligibility, $prices);
    }
}
