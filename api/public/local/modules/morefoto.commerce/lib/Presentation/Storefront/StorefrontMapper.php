<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Presentation\Storefront;

use Morefoto\Commerce\Application\Storefront\Dto\CatalogOutputDto;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteOutputDto;
use Morefoto\Commerce\Presentation\Storefront\Dto\CreateQuoteRequestDto;
use Morefoto\Commerce\Presentation\Storefront\Dto\QuoteResultDto;
use Morefoto\Commerce\Presentation\Storefront\Dto\StorefrontCatalogResultDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class StorefrontMapper
{
    /** @return list<QuoteLineInputDto> */
    public function lines(CreateQuoteRequestDto $request): array
    {
        if (!array_is_list($request->lines) || 100 < count($request->lines)) {
            throw new HttpException('INVALID_CART', 422);
        }
        $lines = [];
        foreach ($request->lines as $line) {
            if (1 !== preg_match('/^[a-f0-9-]{36}$/D', $line->assignmentId) || 1 !== preg_match('/^[a-f0-9-]{36}$/D', $line->productId)) {
                throw new HttpException('INVALID_CART', 422);
            }
            $lines[] = new QuoteLineInputDto($line->assignmentId, $line->productId, $line->quantity);
        }

        return $lines;
    }

    public function quote(QuoteOutputDto $output, string $token): QuoteResultDto
    {
        $quote = $output->quote;
        foreach ($quote['lines'] as &$line) {
            if (null !== $line['photo']) {
                $base = '/api/v1/public/galleries/' . $token . '/photos/' . $line['assignmentId'];
                $line['photo']['thumbSrc'] = $base . '/thumb';
                $line['photo']['previewSrc'] = $base . '/preview';
            }
        }
        unset($line);

        return new QuoteResultDto($quote, $output->quoteToken, $output->expiresAt);
    }

    public function catalog(CatalogOutputDto $output): StorefrontCatalogResultDto
    {
        $products = [];
        foreach ($output->products as $product) {
            $products[] = [
                'id' => $product->id, 'name' => $product->name, 'description' => $product->description, 'kind' => $product->kind->value,
                'price' => $product->price, 'printCount' => $product->printCount, 'format' => $product->format, 'unit' => $product->unit,
                'staffDiscount' => $product->staffDiscount, 'active' => $product->active,
            ];
        }

        return new StorefrontCatalogResultDto($products, $output->giftThreshold, $output->giftForStaff, $output->revision, $output->conditionsRevision, ['receiptChannels' => [], 'purchaseEnabled' => false], null);
    }
}
