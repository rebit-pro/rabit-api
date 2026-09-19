<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\Application;

final class Version20260919090001 extends Version
{
    protected $author = 'codex';
    protected $description = 'B2: idempotent staff and assignment mutations';

    public function up(): void
    {
        $connection = Application::getConnection();
        foreach (['b_hlbd_mf_staff_profile', 'mf_access_state'] as $table) {
            if (!$connection->isTableExists($table)) {
                throw new \RuntimeException('B2 staff management requires accepted Access migrations.');
            }
        }
        if (!$connection->isTableExists('mf_staff_operation')) {
            $connection->queryExecute(
                'CREATE TABLE mf_staff_operation ('
                . 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
                . 'actor_id INT NOT NULL,'
                . 'operation VARCHAR(64) NOT NULL,'
                . 'idempotency_key CHAR(32) NOT NULL,'
                . 'payload_hash CHAR(64) NOT NULL,'
                . 'result_json TEXT NOT NULL,'
                . 'created_at DATETIME NOT NULL,'
                . 'PRIMARY KEY (id),'
                . 'UNIQUE KEY ux_mf_staff_operation (actor_id,operation,idempotency_key),'
                . 'KEY ix_mf_staff_operation_created (created_at)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            );
        }
    }

    public function down(): void
    {
        $connection = Application::getConnection();
        if (!$connection->isTableExists('mf_staff_operation')) {
            return;
        }
        if (false !== $connection->query('SELECT id FROM mf_staff_operation LIMIT 1')->fetch()) {
            throw new \RuntimeException('Staff operation history exists; preserve it when rolling back application code.');
        }
        $connection->dropTable('mf_staff_operation');
    }
}
