<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Infrastructure\Gallery;

use Bitrix\Main\Application;
use Morefoto\Handoff\Domain\Request\Exception\StaffEligibilityUnavailableException;
use Rebit\Share\Contracts\Handoff\StaffEligibilityInterface;

final readonly class StaffEligibility implements StaffEligibilityInterface
{
    public function confirmed(int $shootId, array $childIds): array
    {
        if ([] === $childIds) {
            return [];
        }
        foreach ($childIds as $id) {
            if (1 > $id) {
                throw new \InvalidArgumentException('Positive child ID required.');
            }
        }
        try {
            $result = Application::getConnection()->query(
                'SELECT DISTINCT line.CHILD_ID FROM mf_staff_request_row line INNER JOIN mf_staff_request request ON request.ID=line.REQUEST_ID '
                . 'WHERE request.STAFF_ELIGIBLE=1 AND request.SHOOT_ID=' . $shootId
                . ' AND line.CHILD_ID IN (' . implode(',', $childIds) . ')',
            );
            $eligible = [];
            while (false !== ($row = $result->fetch())) {
                $eligible[(int)$row['CHILD_ID']] = true;
            }
        } catch (\Throwable $error) {
            throw new StaffEligibilityUnavailableException('Cannot verify staff eligibility.', 0, $error);
        }

        return $eligible;
    }
}
