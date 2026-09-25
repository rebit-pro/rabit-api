<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Conditions\Dto\PaymentCostsInputDto;
use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsPayloadHash;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGlobalConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\GetGroupConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\ManageConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\SaveGlobalConditionsUseCase;
use Morefoto\Commerce\Application\Conditions\UseCase\SaveGroupConditionsUseCase;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogRevisionConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogStorageException;
use Morefoto\Commerce\Domain\Catalog\Exception\IdempotencyConflictException;
use Morefoto\Commerce\Domain\Catalog\Exception\InvalidProductException;
use Morefoto\Commerce\Domain\Conditions\Exception\ConditionsRevisionConflictException;
use Morefoto\Commerce\Domain\Conditions\Exception\ConditionsStorageException;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;
use Morefoto\Commerce\Domain\Conditions\Repository\ConditionsIdempotencyRepository;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\CatalogAccessException;
use Rebit\Share\Contracts\Access\CatalogAccessGuardInterface;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * @internal
 */
final class ManageConditionsUseCaseTest extends TestCase
{
    private const string KEY = '12345678123456781234567812345678';

    public function testDomainFailuresBecomeConditionsApiCodes(): void
    {
        $cases = [
            [new InvalidConditionsException('x'), 'VALIDATION_FAILED', 422],
            [new InvalidProductException('x'), 'VALIDATION_FAILED', 422],
            [new ConditionsRevisionConflictException('x'), 'REVISION_CONFLICT', 409],
            [new CatalogRevisionConflictException('x'), 'REVISION_CONFLICT', 409],
            [new IdempotencyConflictException('x'), 'IDEMPOTENCY_CONFLICT', 409],
            [new ConditionsStorageException('x'), 'CONDITIONS_UNAVAILABLE', 503],
            [new CatalogStorageException('x'), 'CONDITIONS_UNAVAILABLE', 503],
            [new CatalogAccessException('Unauthorized', 401), 'UNAUTHORIZED', 401],
            [new CatalogAccessException('Forbidden', 403), 'FORBIDDEN', 403],
            [new CatalogAccessException('Unavailable', 503), 'ACCESS_UNAVAILABLE', 503],
        ];
        foreach ($cases as [$failure, $code, $status]) {
            $access = $this->createStub(CatalogAccessGuardInterface::class);
            $access->method('lockOrganizer')->willThrowException($failure);
            $this->assertHttp($code, $status, fn(): mixed => $this->useCase($access, $this->createStub(ConditionsIdempotencyRepository::class))->getGlobal(11, 'token'));
        }
    }

    public function testDeniedSaveNeverReadsReplayOrValidatesBody(): void
    {
        $access = $this->createMock(CatalogAccessGuardInterface::class);
        $access->expects(self::once())->method('lockOrganizer')->with(11, 'teacher-token')->willThrowException(new CatalogAccessException('Forbidden', 403));
        $keys = $this->createMock(ConditionsIdempotencyRepository::class);
        $keys->expects(self::never())->method('find');
        $invalid = new SaveConditionsInputDto(-1, 0, [], true, 0, true, paymentCosts: new PaymentCostsInputDto(true, 5000));

        $this->assertHttp('FORBIDDEN', 403, fn(): mixed => $this->useCase($access, $keys)->saveGlobal(11, 'teacher-token', self::KEY, $invalid));
    }

    public function testMalformedIdempotencyKeyIsRejectedBeforeAccess(): void
    {
        $access = $this->createMock(CatalogAccessGuardInterface::class);
        $access->expects(self::never())->method('lockOrganizer');

        $this->assertHttp('VALIDATION_FAILED', 422, fn(): mixed => $this->useCase($access, $this->createStub(ConditionsIdempotencyRepository::class))
            ->saveGlobal(11, 'token', 'short', new SaveConditionsInputDto(1, 1, [], false, 0, false, paymentCosts: new PaymentCostsInputDto(false, 380))));
    }

    public function testReplayWithDifferentPolicyIsIdempotencyConflict(): void
    {
        $access = $this->createStub(CatalogAccessGuardInterface::class);
        $keys = $this->createMock(ConditionsIdempotencyRepository::class);
        $stored = new SaveConditionsInputDto(1, 1, [], false, 0, false, paymentCosts: new PaymentCostsInputDto(true, 380));
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn(['PAYLOAD_HASH' => (new ConditionsPayloadHash())->create($stored), 'RESULT_REVISION' => '2', 'RESULT_CATALOG_REVISION' => '3', 'RESULT_CONDITIONS_REVISION' => '2']);
        $keys->method('find')->willReturn($result);
        $keys->expects(self::never())->method('save');
        $useCase = $this->useCase($access, $keys);

        self::assertSame(2, $useCase->saveGlobal(11, 'token', self::KEY, $stored)->revision);
        $this->assertHttp('IDEMPOTENCY_CONFLICT', 409, fn(): mixed => $useCase->saveGlobal(11, 'token', self::KEY, new SaveConditionsInputDto(1, 1, [], false, 0, false, paymentCosts: new PaymentCostsInputDto(true, 390))));
    }

    private function useCase(CatalogAccessGuardInterface $access, ConditionsIdempotencyRepository $keys): ManageConditionsUseCase
    {
        $transaction = new class implements CatalogTransactionInterface {
            public function execute(callable $operation): mixed
            {
                return $operation();
            }
        };
        $save = $this->createMock(SaveGlobalConditionsUseCase::class);
        $save->expects(self::never())->method('executeWithinTransaction');

        return new ManageConditionsUseCase(
            $transaction,
            $access,
            $this->createStub(GroupReferenceInterface::class),
            $keys,
            new ConditionsPayloadHash(),
            $this->createStub(GetGlobalConditionsUseCase::class),
            $save,
            $this->createStub(GetGroupConditionsUseCase::class),
            $this->createStub(SaveGroupConditionsUseCase::class),
        );
    }

    /** @param callable(): mixed $operation */
    private function assertHttp(string $code, int $status, callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected ' . $code);
        } catch (HttpException $exception) {
            self::assertSame([$code, $status], [$exception->getMessage(), $exception->getCode()]);
        }
    }
}
