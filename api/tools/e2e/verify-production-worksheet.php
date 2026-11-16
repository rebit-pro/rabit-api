<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Commerce\Application\Sales\Dto\SalesReportInputDto;
use Morefoto\Commerce\Application\Sales\UseCase\GetSalesReportUseCase;
use Morefoto\Production\Application\Archive\Contract\ArchiveStorageInterface;
use Morefoto\Production\Application\Archive\UseCase\ExportProductionVersionUseCase;
use Morefoto\Production\Application\Institution\UseCase\ExportInstitutionArchiveUseCase;
use Morefoto\Production\Domain\Archive\Repository\ArchiveRepositoryInterface;
use Morefoto\Production\Domain\Institution\Repository\InstitutionArchiveRepositoryInterface;
use Rebit\Share\Contracts\Production\PrintCompositionInterface;

// Runs before the existing Production verifiers expire/rebuild their fixtures. Never use against a working DB.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix') || !is_file('/runtime/production-worksheet.json')) {
    throw new RuntimeException('Worksheet verification requires the disposable runtime and browser records.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
if (!Loader::includeModule('morefoto.commerce') || !Loader::includeModule('morefoto.production')) {
    throw new RuntimeException('Cannot load worksheet providers.');
}
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Worksheet verification: ' . $message);
    }
};
$db = Application::getConnection();
$sql = $db->getSqlHelper();
$services = ServiceLocator::getInstance();
$actor = (int)$db->query("SELECT ID FROM b_user WHERE EMAIL='organizer@example.invalid'")->fetchRaw()['ID'];
$records = json_decode((string)file_get_contents('/runtime/production-worksheet.json'), true, flags: JSON_THROW_ON_ERROR)['records'];
$check(4 === count($records), 'preview, group and both institution versions were checked over HTTP');
foreach ($records as $record) {
    $params = $record['params'];
    $worksheet = $services->get(GetSalesReportUseCase::class)->execute($actor, new SalesReportInputDto(
        institutionId: $params['institutionId'] ?? null,
        groupId: $params['groupId'] ?? null,
        view: 'worksheet',
        worksheetMode: $params['worksheetMode'] ?? 'preview',
        archiveType: $params['archiveType'] ?? null,
        archiveId: $params['archiveId'] ?? null,
    ))->worksheet;
    $check(null !== $worksheet, 'real Bitrix DI resolves the worksheet');
    $check($worksheet->total === $record['worksheet']['total'], 'HTTP and native money agree');
    $check($worksheet->prints === $record['worksheet']['prints'], 'HTTP and native prints agree');
    $selected = [];
    $sum = 0;
    foreach ($worksheet->groups as $group) {
        foreach ([$group->orders, $group->outsideOrders, $group->deferredOrders] as $orders) {
            foreach ($orders as $order) {
                $id = $sql->forSql($order->id);
                $snapshot = $db->query("SELECT CHILD_FIRST_NAME,CHILD_LAST_NAME,TOTAL FROM mf_order WHERE PUBLIC_ID='{$id}'")->fetchRaw();
                $check(is_array($snapshot), 'the Commerce order snapshot exists');
                $check($order->firstName === $snapshot['CHILD_FIRST_NAME'] && $order->lastName === $snapshot['CHILD_LAST_NAME'], 'name belongs to the order snapshot');
                $check($order->total === (int)$snapshot['TOTAL'], 'full order total is unchanged');
                foreach ($order->lines as $line) {
                    if (!$line->selected) {
                        continue;
                    }
                    $id = $sql->forSql($line->id);
                    $saved = $db->query("SELECT TOTAL,DISCOUNT,UNIT_PRICE,PRODUCT_NAME,PRODUCT_FORMAT,QUANTITY,PRINT_COUNT FROM mf_order_line WHERE PUBLIC_ID='{$id}'")->fetchRaw();
                    $check($line->total === (int)$saved['TOTAL'] && $line->discount === (int)$saved['DISCOUNT'] && $line->unitPrice === (int)$saved['UNIT_PRICE'], 'net line money/discount use snapshots');
                    $check($line->productName === $saved['PRODUCT_NAME'] && $line->format === $saved['PRODUCT_FORMAT'], 'catalog does not rename the purchase');
                    $check(!isset($selected[$line->id]), 'a selected purchase occurs once');
                    $selected[$line->id] = $line;
                    $sum += $line->total;
                }
            }
        }
    }
    $check($sum === $worksheet->total, 'selected line net totals equal worksheet total');
    if (null === $worksheet->archive) {
        continue;
    }
    $version = $worksheet->archive;
    $check($version->id === $params['archiveId'], 'the explicit version survives native resolve');
    $composition = $services->get(PrintCompositionInterface::class)->read($actor, $version->type, $version->id);
    $check(count($selected) === count($composition->lines), 'no missing or extra final print line');
    if ('group' === $version->type) {
        $archive = $services->get(ArchiveRepositoryInterface::class)->find($version->id);
        $csv = $services->get(ExportProductionVersionUseCase::class)->execute($actor, $version->id)->content;
    } else {
        $archive = $services->get(InstitutionArchiveRepositoryInterface::class)->find($version->id);
        $csv = $services->get(ExportInstitutionArchiveUseCase::class)->execute($actor, $version->id)->content;
    }
    $check(null !== $archive && null !== $archive->archivePath, 'the selected archive is persisted');
    $zip = new ZipArchive();
    $check(true === $zip->open($services->get(ArchiveStorageInterface::class)->absolutePath($archive->archivePath)), 'the saved ZIP opens');
    $check($csv === $zip->getFromName('состав.csv'), 'saved ZIP and CSV manifest agree byte for byte');
    $rows = [];
    foreach (array_slice(explode("\r\n", rtrim(substr($csv, 3), "\r\n")), 1) as $row) {
        $cells = str_getcsv($row, ';', '"', '');
        $rows[$cells[11]] = $cells;
    }
    foreach ($composition->lines as $source) {
        $line = $selected[$source->lineId];
        $file = $line->files[0];
        $check($file->path === $rows[$line->id][2] && $file->filename === basename($source->path), 'worksheet preserves literal ZIP path and basename');
        $check($line->prints === (int)$rows[$line->id][9] && $file->printsInFile === (int)$rows[$line->id][15], 'contribution and shared-file prints agree');
        $check(false !== $zip->locateName($file->path), 'every worksheet file exists in this ZIP');
    }
    $zip->close();
}
echo "Production worksheet integration passed\n";
