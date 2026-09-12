<?php

declare(strict_types=1);

use Bitrix\Main\DI\ServiceLocator;
use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Catalog\Service\AuthorizedCatalog;
use Morefoto\Commerce\Application\Catalog\Service\CatalogPayloadHash;
use Morefoto\Commerce\Application\Catalog\UseCase\CreateProductUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\UpdateProductUseCase;
use Morefoto\Commerce\Application\Catalog\UseCase\ListProductsUseCase;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogIdempotencyRepository;
use Rebit\Share\Contracts\Access\CatalogAccessGuardInterface;

try {
    $job = json_decode(file_get_contents($argv[1]), true, 32, JSON_THROW_ON_ERROR);
    require __DIR__ . '/connect.php';
    require __DIR__ . '/http.php';
    $services = ServiceLocator::getInstance();
    $catalog = null;
    if (isset($job['pause'])) {
        $guard = new class($services->get(CatalogAccessGuardInterface::class), $job['pause'], $job['marker']) implements CatalogAccessGuardInterface {
            public function __construct(private readonly CatalogAccessGuardInterface $delegate, private readonly string $phase, private readonly string $marker) {}

            public function lockOrganizer(int $actorId, string $token): void
            {
                if ('before' === $this->phase) {
                    $this->pause();
                }
                $this->delegate->lockOrganizer($actorId, $token);
                if ('after' === $this->phase) {
                    $this->pause();
                }
            }

            private function pause(): void
            {
                file_put_contents($this->marker, 'locked');
                $deadline = microtime(true) + 10;
                while (!file_exists($this->marker . '.release')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('Fixture synchronization timed out.');
                    }
                    usleep(10000);
                    clearstatcache();
                }
            }
        };
        $catalog = new AuthorizedCatalog($services->get(CatalogTransactionInterface::class), $guard, $services->get(CatalogIdempotencyRepository::class), $services->get(CatalogPayloadHash::class), $services->get(CreateProductUseCase::class), $services->get(UpdateProductUseCase::class), $services->get(ListProductsUseCase::class));
    }
    $response = E2Http::request($job['method'], $job['path'], $job['token'], $job['raw'], $job['key'], catalog: $catalog);
    file_put_contents($argv[1] . '.result', json_encode($response, JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    file_put_contents($argv[1] . '.result', json_encode(['failure' => $exception::class, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR));
    exit(1);
}
