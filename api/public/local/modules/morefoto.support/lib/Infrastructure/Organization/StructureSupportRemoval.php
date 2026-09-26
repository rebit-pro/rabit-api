<?php

declare(strict_types=1);

namespace Morefoto\Support\Infrastructure\Organization;

use Bitrix\Main\Application;
use Rebit\Share\Contracts\Organization\Dto\StructureRemovalDto;
use Rebit\Share\Contracts\Support\StructureSupportRemovalInterface;

/**
 * A parent's question cannot exist without its group (CHECK of mf_support_question), so it goes with the conversation.
 * A queued MAX delivery of a removed message finds no row and stops on its own.
 */
final readonly class StructureSupportRemoval implements StructureSupportRemovalInterface
{
    public function remove(StructureRemovalDto $removal): void
    {
        if ([] === $removal->groupIds) {
            return;
        }
        // Native IDs read by Organization under its locks: integers only, safe to inline.
        $questions = "SELECT ID FROM mf_support_question WHERE AUTHOR='parent' AND GROUP_ID IN (" . implode(',', $removal->groupIds) . ')';
        $connection = Application::getConnection();
        $connection->queryExecute('DELETE FROM mf_support_message WHERE QUESTION_ID IN (' . $questions . ')');
        $connection->queryExecute('DELETE FROM mf_support_idempotency WHERE QUESTION_ID IN (' . $questions . ')');
        $connection->queryExecute('DELETE FROM mf_support_question WHERE GROUP_ID IN (' . implode(',', $removal->groupIds) . ')');
    }
}
