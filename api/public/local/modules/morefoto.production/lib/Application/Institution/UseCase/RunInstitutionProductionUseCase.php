<?php

declare(strict_types=1);

namespace Morefoto\Production\Application\Institution\UseCase;

use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\Dto\ProductionRunOutputDto;
use Morefoto\Production\Application\Institution\Service\InstitutionArchiveBuilder;
use Morefoto\Production\Application\Institution\Service\InstitutionSnapshot;
use Morefoto\Production\Domain\Archive\Exception\ArchiveLayoutException;
use Morefoto\Production\Domain\Institution\Exception\InstitutionArchiveBusyException;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Organization\PrintInstitutionDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

/**
 * Автоматически обновляет полные архивы закрытых учреждений, собирает ожидающие выпуски и удаляет истёкшие ZIP.
 * Круговой обход и локальная обработка ошибки не позволяют одному учреждению остановить остальные.
 */
final readonly class RunInstitutionProductionUseCase
{
    public function __construct(
        private InstitutionArchiveRepositoryInterface $archives,
        private PrintInstitutionDirectoryInterface $institutions,
        private InstitutionSnapshot $snapshot,
        private InstitutionArchiveBuilder $builder,
        private ArchiveStorageInterface $storage,
        private ClockInterface $clock,
    ) {}

    public function execute(int $limit): ProductionRunOutputDto
    {
        $items = $this->institutions->after($this->archives->cursor(), $limit);
        if ([] === $items) {
            $this->archives->advance(0);
            $items = $this->institutions->after(0, $limit);
        }
        $scheduled = 0;
        $failed = 0;
        foreach ($items as $institution) {
            try {
                if (null !== $this->snapshot->capture($institution)) {
                    ++$scheduled;
                }
            } catch (InstitutionArchiveBusyException) {
                // Другой запрос обслуживает учреждение; его результат увидит следующий круг.
            } catch (ArchiveLayoutException|HttpException $error) {
                $code = $error->getMessage();
                if (!in_array($code, ['PRINT_SALES_OPEN', 'BUILD_DELAY'], true)) {
                    ++$failed;
                }
            } catch (\Throwable) {
                ++$failed;
            }
            $this->archives->advance($institution->nativeId);
        }
        $built = 0;
        foreach ($this->archives->due($this->clock->now(), $limit) as $archive) {
            $this->builder->build($archive) ? ++$built : ++$failed;
        }
        $expired = 0;
        foreach ($this->archives->expired($this->clock->now(), $limit) as $archive) {
            try {
                $this->archives->exclusive('institution:' . $archive->institutionPublicId, function() use ($archive, &$expired): void {
                    $fresh = $this->archives->find($archive->publicId);
                    if (null === $fresh || $fresh->generation !== $archive->generation || null === $fresh->expiresAt || $fresh->expiresAt > $this->clock->now()) {
                        return;
                    }
                    if (null !== $archive->archivePath) {
                        $this->storage->delete($archive->archivePath);
                    }
                    $this->archives->expire($archive->publicId, $archive->generation);
                    ++$expired;
                });
            } catch (InstitutionArchiveBusyException) {
                // Повтор очистки на следующем тике, чужая аренда не снимается.
            }
        }

        return new ProductionRunOutputDto($scheduled, $built, $failed, $expired);
    }
}
