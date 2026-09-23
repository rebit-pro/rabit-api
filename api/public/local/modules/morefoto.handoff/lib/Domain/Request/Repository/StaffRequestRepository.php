<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Domain\Request\Repository;

use Bitrix\Main\Application;
use Rebit\Share\Contracts\Access\Dto\StaffRequestActorOutputDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class StaffRequestRepository
{
    /** @return null|array<string,mixed> */
    public function request(string $publicId, bool $lock = false): ?array
    {
        $row = Application::getConnection()->query(
            'SELECT r.ID,r.PUBLIC_ID,r.INSTITUTION_ID,r.SHOOT_ID,r.CREATED_BY,r.CREATED_BY_NAME,r.STATUS,r.REVISION,r.COMMENT,'
            . 'r.STAFF_ELIGIBLE,r.ELIGIBILITY_SOURCE,DATE_FORMAT(r.ELIGIBILITY_VERIFIED_AT,\'%Y-%m-%dT%H:%i:%sZ\') AS ELIGIBILITY_VERIFIED_AT,'
            . 'DATE_FORMAT(r.CREATED_AT,\'%Y-%m-%dT%H:%i:%sZ\') AS CREATED_AT,i.UF_PUBLIC_ID AS INSTITUTION_PUBLIC_ID,'
            . 's.UF_PUBLIC_ID AS SHOOT_PUBLIC_ID FROM mf_staff_request r '
            . 'INNER JOIN b_hlbd_mf_institution i ON i.ID=r.INSTITUTION_ID '
            . 'INNER JOIN b_hlbd_mf_shoot s ON s.ID=r.SHOOT_ID '
            . 'WHERE r.PUBLIC_ID=' . $this->quote($publicId) . ' LIMIT 1' . ($lock ? ' FOR UPDATE' : ''),
        )->fetch();

        return is_array($row) ? $row : null;
    }

    public const array STATUSES = ['submitted', 'clarification', 'transferred'];

    /**
     * Page of visible requests plus their split by status. The split uses the same visibility and filters except the
     * status filter, so a client can switch between statuses without losing the other counters.
     *
     * @return array{
     *     items: list<array<string, mixed>>,
     *     total: int,
     *     byStatus: array{submitted: int, clarification: int, transferred: int},
     * }
     */
    public function page(StaffRequestActorOutputDto $actor, ?string $institutionId, ?string $shootId, ?string $status, int $limit, int $offset): array
    {
        $conditions = [$this->visibility($actor, 'r')];
        if (null !== $institutionId) {
            $conditions[] = 'i.UF_PUBLIC_ID=' . $this->quote($institutionId);
        }
        if (null !== $shootId) {
            $conditions[] = 's.UF_PUBLIC_ID=' . $this->quote($shootId);
        }
        $summaryWhere = implode(' AND ', $conditions);
        if (null !== $status) {
            $conditions[] = 'r.STATUS=' . $this->quote($status);
        }
        $where = implode(' AND ', $conditions);
        $connection = Application::getConnection();
        $byStatus = array_fill_keys(self::STATUSES, 0);
        $counts = $connection->query(
            'SELECT r.STATUS, COUNT(*) AS TOTAL FROM mf_staff_request r INNER JOIN b_hlbd_mf_institution i ON i.ID=r.INSTITUTION_ID '
            . 'INNER JOIN b_hlbd_mf_shoot s ON s.ID=r.SHOOT_ID WHERE ' . $summaryWhere . ' GROUP BY r.STATUS',
        );
        while (false !== ($count = $counts->fetch())) {
            if (array_key_exists((string)$count['STATUS'], $byStatus)) {
                $byStatus[(string)$count['STATUS']] = (int)$count['TOTAL'];
            }
        }
        $totalRow = $connection->query(
            'SELECT COUNT(*) AS TOTAL FROM mf_staff_request r INNER JOIN b_hlbd_mf_institution i ON i.ID=r.INSTITUTION_ID '
            . 'INNER JOIN b_hlbd_mf_shoot s ON s.ID=r.SHOOT_ID WHERE ' . $where,
        )->fetch();
        $result = $connection->query(
            'SELECT r.PUBLIC_ID FROM mf_staff_request r INNER JOIN b_hlbd_mf_institution i ON i.ID=r.INSTITUTION_ID '
            . 'INNER JOIN b_hlbd_mf_shoot s ON s.ID=r.SHOOT_ID WHERE ' . $where
            . " ORDER BY r.UPDATED_AT DESC,r.ID DESC LIMIT {$limit} OFFSET {$offset}",
        );
        $items = [];
        while (false !== ($id = $result->fetch())) {
            $row = $this->request((string)$id['PUBLIC_ID']);
            if (null !== $row) {
                $items[] = $this->view($row);
            }
        }

        return ['items' => $items, 'total' => is_array($totalRow) ? (int)$totalRow['TOTAL'] : 0, 'byStatus' => $byStatus];
    }

    /** @return array{institutions:list<array{id:string,name:string}>,shoots:list<array{id:string,institutionId:string,name:string}>,groups:list<array{id:string,institutionId:string,shootId:string,shootName:string,name:string,kind:string,state:string}>} */
    public function options(StaffRequestActorOutputDto $actor): array
    {
        $scope = match ($actor->role) {
            'organizer' => '1=1',
            'curator', 'head' => 'i.ID IN (' . $this->ids($actor->institutionIds) . ')',
            'teacher' => 'g.ID IN (' . $this->ids($actor->groupIds) . ')',
            default => '1=0',
        };
        $result = Application::getConnection()->query(
            'SELECT i.UF_PUBLIC_ID AS INSTITUTION_ID,i.UF_NAME AS INSTITUTION_NAME,s.UF_PUBLIC_ID AS SHOOT_ID,s.UF_NAME AS SHOOT_NAME,'
            . 'g.UF_PUBLIC_ID AS GROUP_ID,g.UF_NAME AS GROUP_NAME,g.UF_KIND,g.UF_SENT_AT,g.UF_CLOSES_AT '
            . 'FROM b_hlbd_mf_group g INNER JOIN b_hlbd_mf_shoot s ON s.ID=g.UF_SHOOT_ID '
            . 'INNER JOIN b_hlbd_mf_institution i ON i.ID=s.UF_INSTITUTION_ID '
            . "WHERE g.UF_KIND='regular' AND {$scope} ORDER BY i.UF_NAME,s.UF_NAME,g.UF_NAME,g.ID",
        );
        $institutions = [];
        $shoots = [];
        $groups = [];
        while (false !== ($row = $result->fetch())) {
            $institutionId = (string)$row['INSTITUTION_ID'];
            $shootId = (string)$row['SHOOT_ID'];
            $institutions[$institutionId] = ['id' => $institutionId, 'name' => (string)$row['INSTITUTION_NAME']];
            $shoots[$shootId] = ['id' => $shootId, 'institutionId' => $institutionId, 'name' => (string)$row['SHOOT_NAME']];
            $groups[] = [
                'id' => (string)$row['GROUP_ID'],
                'institutionId' => $institutionId,
                'shootId' => $shootId,
                'shootName' => (string)$row['SHOOT_NAME'],
                'name' => (string)$row['GROUP_NAME'],
                'kind' => (string)$row['UF_KIND'],
                'state' => null === $row['UF_SENT_AT'] ? 'preparing' : (null !== $row['UF_CLOSES_AT'] && strtotime((string)$row['UF_CLOSES_AT'] . ' UTC') <= time() ? 'closed' : 'open'),
            ];
        }

        return ['institutions' => array_values($institutions), 'shoots' => array_values($shoots), 'groups' => $groups];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public function view(array $row): array
    {
        return [
            'id' => (string)$row['PUBLIC_ID'],
            'institutionId' => (string)$row['INSTITUTION_PUBLIC_ID'],
            'shootId' => (string)$row['SHOOT_PUBLIC_ID'],
            'createdBy' => (int)$row['CREATED_BY'],
            'createdByName' => (string)$row['CREATED_BY_NAME'],
            'createdAt' => (string)$row['CREATED_AT'],
            'revision' => (int)$row['REVISION'],
            'status' => (string)$row['STATUS'],
            'rows' => $this->rows((int)$row['ID']),
            'comment' => (string)$row['COMMENT'],
            'history' => $this->history((int)$row['ID']),
            'results' => $this->results((int)$row['ID']),
            'staffEligibility' => [
                'eligible' => 1 === (int)$row['STAFF_ELIGIBLE'],
                'source' => (string)$row['ELIGIBILITY_SOURCE'],
                'verifiedAt' => (string)$row['ELIGIBILITY_VERIFIED_AT'],
            ],
        ];
    }

    /** @return list<array{id:string,groupId:string,code:string,childCode:string,photoIds:list<string>}> */
    public function rows(int $requestId): array
    {
        $result = Application::getConnection()->query(
            'SELECT rr.PUBLIC_ID,g.UF_PUBLIC_ID AS GROUP_PUBLIC_ID,rr.INPUT_CODE,COALESCE(rr.TRANSFER_FROM_CODE,c.CODE) AS CODE,rr.PHOTO_IDS_JSON '
            . 'FROM mf_staff_request_row rr INNER JOIN b_hlbd_mf_group g ON g.ID=rr.GROUP_ID '
            . 'INNER JOIN mf_media_child c ON c.ID=rr.CHILD_ID WHERE rr.REQUEST_ID=' . $requestId . ' ORDER BY rr.SORT_NO,rr.ID',
        );
        $rows = [];
        while (false !== ($row = $result->fetch())) {
            $decoded = json_decode((string)$row['PHOTO_IDS_JSON'], true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \UnexpectedValueException('Invalid staff request photo snapshot.');
            }
            $rows[] = [
                'id' => (string)$row['PUBLIC_ID'],
                'groupId' => (string)$row['GROUP_PUBLIC_ID'],
                'code' => (string)$row['INPUT_CODE'],
                'childCode' => (string)$row['CODE'],
                'photoIds' => array_values(array_map('strval', $decoded)),
            ];
        }

        return $rows;
    }

    /** @return list<array{kind:string,actorId:int,actorName:string,at:string,comment:string,confirmed:bool}> */
    public function history(int $requestId): array
    {
        $result = Application::getConnection()->query(
            'SELECT KIND,ACTOR_ID,ACTOR_NAME,DATE_FORMAT(CREATED_AT,\'%Y-%m-%dT%H:%i:%sZ\') AS CREATED_AT,COMMENT,CONFIRMED '
            . 'FROM mf_staff_request_history WHERE REQUEST_ID=' . $requestId . ' ORDER BY ID',
        );
        $history = [];
        while (false !== ($row = $result->fetch())) {
            $history[] = [
                'kind' => (string)$row['KIND'],
                'actorId' => (int)$row['ACTOR_ID'],
                'actorName' => (string)$row['ACTOR_NAME'],
                'at' => (string)$row['CREATED_AT'],
                'comment' => (string)$row['COMMENT'],
                'confirmed' => 1 === (int)$row['CONFIRMED'],
            ];
        }

        return $history;
    }

    /** @return list<int> */
    public function groupIds(int $requestId): array
    {
        $result = Application::getConnection()->query(
            'SELECT DISTINCT GROUP_ID FROM mf_staff_request_row WHERE REQUEST_ID=' . $requestId . ' ORDER BY GROUP_ID',
        );
        $ids = [];
        while (false !== ($row = $result->fetch())) {
            $ids[] = (int)$row['GROUP_ID'];
        }

        return $ids;
    }

    public function activeChildRequest(int $childId, ?int $exceptRequestId): bool
    {
        $condition = null === $exceptRequestId ? '' : ' AND r.ID<>' . $exceptRequestId;

        return false !== Application::getConnection()->query(
            "SELECT rr.ID FROM mf_staff_request_row rr INNER JOIN mf_staff_request r ON r.ID=rr.REQUEST_ID WHERE rr.CHILD_ID={$childId} AND r.STATUS<>'transferred'{$condition} LIMIT 1",
        )->fetch();
    }

    /** @param list<array{id:string,groupId:int,childId:int,code:string,photoIds:list<string>}> $rows */
    public function create(string $publicId, int $institutionId, int $shootId, StaffRequestActorOutputDto $actor, string $comment, array $rows): int
    {
        $connection = Application::getConnection();
        $connection->queryExecute(sprintf(
            "INSERT INTO mf_staff_request(PUBLIC_ID,INSTITUTION_ID,SHOOT_ID,CREATED_BY,CREATED_BY_NAME,STATUS,REVISION,COMMENT,STAFF_ELIGIBLE,ELIGIBILITY_SOURCE,ELIGIBILITY_VERIFIED_AT,CREATED_AT,UPDATED_AT) VALUES(%s,%d,%d,%d,%s,'submitted',1,%s,1,'verified_staff_assignment',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())",
            $this->quote($publicId),
            $institutionId,
            $shootId,
            $actor->id,
            $this->quote($actor->name),
            $this->quote($comment),
        ));
        $id = (int)$connection->getInsertedId();
        $this->replaceRows($id, $rows);

        return $id;
    }

    /** @param list<array{id:string,groupId:int,childId:int,code:string,photoIds:list<string>}> $rows */
    public function resubmit(int $id, int $revision, string $comment, array $rows): int
    {
        Application::getConnection()->queryExecute(
            "UPDATE mf_staff_request SET STATUS='submitted',REVISION=REVISION+1,COMMENT=" . $this->quote($comment)
            . ",ELIGIBILITY_VERIFIED_AT=UTC_TIMESTAMP(),UPDATED_AT=UTC_TIMESTAMP() WHERE ID={$id} AND REVISION={$revision} AND STATUS<>'transferred'",
        );
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new HttpException('REVISION_CONFLICT', 409);
        }
        $this->replaceRows($id, $rows);

        return $revision + 1;
    }

    public function clarify(int $id, int $revision): int
    {
        Application::getConnection()->queryExecute(
            "UPDATE mf_staff_request SET STATUS='clarification',REVISION=REVISION+1,UPDATED_AT=UTC_TIMESTAMP() WHERE ID={$id} AND REVISION={$revision} AND STATUS<>'transferred'",
        );
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new HttpException('REVISION_CONFLICT', 409);
        }

        return $revision + 1;
    }

    /** @param list<array{id:string,groupId:int,childId:int,code:string,photoIds:list<string>}> $rows */
    private function replaceRows(int $requestId, array $rows): void
    {
        $connection = Application::getConnection();
        $connection->queryExecute('DELETE FROM mf_staff_request_row WHERE REQUEST_ID=' . $requestId);
        foreach ($rows as $index => $row) {
            $connection->queryExecute(sprintf(
                'INSERT INTO mf_staff_request_row(PUBLIC_ID,REQUEST_ID,GROUP_ID,CHILD_ID,INPUT_CODE,PHOTO_IDS_JSON,SORT_NO,CREATED_AT) VALUES(%s,%d,%d,%d,%s,%s,%d,UTC_TIMESTAMP())',
                $this->quote($row['id']),
                $requestId,
                $row['groupId'],
                $row['childId'],
                $this->quote($row['code']),
                $this->quote(json_encode($row['photoIds'], JSON_THROW_ON_ERROR)),
                $index + 1,
            ));
        }
    }

    public function appendHistory(int $requestId, string $kind, int $actorId, string $actorName, string $comment, bool $confirmed): void
    {
        Application::getConnection()->queryExecute(sprintf(
            'INSERT INTO mf_staff_request_history(REQUEST_ID,KIND,ACTOR_ID,ACTOR_NAME,COMMENT,CONFIRMED,CREATED_AT) VALUES(%d,%s,%d,%s,%s,%d,UTC_TIMESTAMP())',
            $requestId,
            $this->quote($kind),
            $actorId,
            $this->quote($actorName),
            $this->quote($comment),
            $confirmed ? 1 : 0,
        ));
    }

    /**
     * Строки заявки для переноса в порядке подачи.
     *
     * @return list<array{
     *     id: int,
     *     publicId: string,
     *     groupId: int,
     *     groupPublicId: string,
     *     childId: int,
     *     photoIds: list<string>,
     * }>
     */
    public function transferRows(int $requestId): array
    {
        $result = Application::getConnection()->query(
            'SELECT rr.ID,rr.PUBLIC_ID,rr.GROUP_ID,g.UF_PUBLIC_ID AS GROUP_PUBLIC_ID,rr.CHILD_ID,rr.PHOTO_IDS_JSON '
            . 'FROM mf_staff_request_row rr INNER JOIN b_hlbd_mf_group g ON g.ID=rr.GROUP_ID WHERE rr.REQUEST_ID=' . $requestId . ' ORDER BY rr.SORT_NO,rr.ID',
        );
        $rows = [];
        while (false !== ($row = $result->fetch())) {
            $rows[] = [
                'id' => (int)$row['ID'],
                'publicId' => (string)$row['PUBLIC_ID'],
                'groupId' => (int)$row['GROUP_ID'],
                'groupPublicId' => (string)$row['GROUP_PUBLIC_ID'],
                'childId' => (int)$row['CHILD_ID'],
                'photoIds' => $this->photoIds((string)$row['PHOTO_IDS_JSON']),
            ];
        }

        return $rows;
    }

    /**
     * Итог переноса по строкам; до переноса — пустой список.
     *
     * @return list<array{
     *     rowId: string,
     *     fromGroupId: string,
     *     fromChildCode: string,
     *     targetGroupId: string,
     *     targetChildCode: string,
     *     photoIds: list<string>,
     * }>
     */
    public function results(int $requestId): array
    {
        $result = Application::getConnection()->query(
            'SELECT rr.PUBLIC_ID,g.UF_PUBLIC_ID AS FROM_GROUP_ID,rr.TRANSFER_FROM_CODE,t.UF_PUBLIC_ID AS TARGET_GROUP_ID,rr.TRANSFER_CODE,rr.TRANSFER_PHOTO_IDS_JSON '
            . 'FROM mf_staff_request_row rr INNER JOIN b_hlbd_mf_group g ON g.ID=rr.GROUP_ID INNER JOIN b_hlbd_mf_group t ON t.ID=rr.TRANSFER_GROUP_ID '
            . 'WHERE rr.REQUEST_ID=' . $requestId . ' ORDER BY rr.SORT_NO,rr.ID',
        );
        $results = [];
        while (false !== ($row = $result->fetch())) {
            $results[] = [
                'rowId' => (string)$row['PUBLIC_ID'],
                'fromGroupId' => (string)$row['FROM_GROUP_ID'],
                'fromChildCode' => (string)$row['TRANSFER_FROM_CODE'],
                'targetGroupId' => (string)$row['TARGET_GROUP_ID'],
                'targetChildCode' => (string)$row['TRANSFER_CODE'],
                'photoIds' => $this->photoIds((string)$row['TRANSFER_PHOTO_IDS_JSON']),
            ];
        }

        return $results;
    }

    /** @param list<string> $photoIds */
    public function recordTransfer(int $rowId, string $fromCode, int $targetGroupId, string $targetCode, array $photoIds): void
    {
        Application::getConnection()->queryExecute(sprintf(
            'UPDATE mf_staff_request_row SET TRANSFER_FROM_CODE=%s,TRANSFER_GROUP_ID=%d,TRANSFER_CODE=%s,TRANSFER_PHOTO_IDS_JSON=%s WHERE ID=%d AND TRANSFER_GROUP_ID IS NULL',
            $this->quote($fromCode),
            $targetGroupId,
            $this->quote($targetCode),
            $this->quote(json_encode($photoIds, JSON_THROW_ON_ERROR)),
            $rowId,
        ));
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new HttpException('REVISION_CONFLICT', 409);
        }
    }

    public function markTransferred(int $id, int $revision): int
    {
        Application::getConnection()->queryExecute(
            "UPDATE mf_staff_request SET STATUS='transferred',REVISION=REVISION+1,UPDATED_AT=UTC_TIMESTAMP() WHERE ID={$id} AND REVISION={$revision} AND STATUS='submitted'",
        );
        if (1 !== Application::getConnection()->getAffectedRowsCount()) {
            throw new HttpException('REVISION_CONFLICT', 409);
        }

        return $revision + 1;
    }

    /** @return null|array{PAYLOAD_HASH:string,RESULT_JSON:string} */
    public function idempotency(int $actorId, string $resource, string $key): ?array
    {
        $row = Application::getConnection()->query(
            'SELECT PAYLOAD_HASH,RESULT_JSON FROM mf_staff_request_idempotency WHERE ACTOR_ID=' . $actorId
            . ' AND RESOURCE_KEY=' . $this->quote($resource) . ' AND IDEMPOTENCY_KEY=' . $this->quote($key) . ' LIMIT 1 FOR UPDATE',
        )->fetch();

        return is_array($row) ? ['PAYLOAD_HASH' => (string)$row['PAYLOAD_HASH'], 'RESULT_JSON' => (string)$row['RESULT_JSON']] : null;
    }

    public function saveIdempotency(int $actorId, string $resource, string $key, string $hash, string $result): void
    {
        Application::getConnection()->queryExecute(sprintf(
            'INSERT INTO mf_staff_request_idempotency(ACTOR_ID,RESOURCE_KEY,IDEMPOTENCY_KEY,PAYLOAD_HASH,RESULT_JSON,CREATED_AT) VALUES(%d,%s,%s,%s,%s,UTC_TIMESTAMP())',
            $actorId,
            $this->quote($resource),
            $this->quote($key),
            $this->quote($hash),
            $this->quote($result),
        ));
    }

    /** @return list<string> */
    private function photoIds(string $json): array
    {
        $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('Invalid staff request photo snapshot.');
        }

        return array_values(array_map('strval', $decoded));
    }

    private function visibility(StaffRequestActorOutputDto $actor, string $alias): string
    {
        return match ($actor->role) {
            'organizer' => '1=1',
            'curator', 'head' => $alias . '.INSTITUTION_ID IN (' . $this->ids($actor->institutionIds) . ')',
            'teacher' => $alias . '.CREATED_BY=' . $actor->id . ' AND NOT EXISTS (SELECT 1 FROM mf_staff_request_row visible_row WHERE visible_row.REQUEST_ID=' . $alias . '.ID AND visible_row.GROUP_ID NOT IN (' . $this->ids($actor->groupIds) . '))',
            default => '1=0',
        };
    }

    /** @param list<int> $ids */
    private function ids(array $ids): string
    {
        return [] === $ids ? '0' : implode(',', array_map('intval', $ids));
    }

    private function quote(string $value): string
    {
        return "'" . Application::getConnection()->getSqlHelper()->forSql($value) . "'";
    }
}
