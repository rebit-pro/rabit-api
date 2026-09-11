<?php

declare(strict_types=1);

namespace Sprint\Migration;

use Bitrix\Main\EventManager;
use Bitrix\Main\Application;
use Rebit\Auth\Infrastructure\Bitrix\Event\RegistrationStateHandler;

final class Version20260911120001 extends Version
{
    protected $author = 'codex';
    protected $description = 'Auth: explicit pending registration and revoke on administrative disable';

    public function up(): void
    {
        $result = Application::getConnection()->query(
            "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('b_user', 'b_uts_user', 'rebit_auth_registration_confirmation')",
        );
        $tables = 0;
        /** @var array{TABLE_NAME: string, ENGINE: string}|false $row */
        while (false !== ($row = $result->fetch())) {
            if ('INNODB' !== strtoupper((string)$row['ENGINE'])) {
                throw new \RuntimeException('W02 requires InnoDB for user, UF and confirmation tables.');
            }
            ++$tables;
        }
        if (3 !== $tables) {
            throw new \RuntimeException('W02 requires the preceding Auth foundation migrations.');
        }

        $this->getHelperManager()->UserTypeEntity()->saveUserTypeEntity([
            'ENTITY_ID' => 'USER',
            'FIELD_NAME' => 'UF_AUTH_REGISTRATION_PENDING',
            'USER_TYPE_ID' => 'boolean',
            'XML_ID' => 'UF_AUTH_REGISTRATION_PENDING',
            'SORT' => 1020,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'N',
            'SHOW_IN_LIST' => 'N',
            'EDIT_IN_LIST' => 'N',
            'IS_SEARCHABLE' => 'N',
            'EDIT_FORM_LABEL' => ['ru' => 'Ожидает подтверждения регистрации', 'en' => 'Pending registration'],
            'SETTINGS' => ['DEFAULT_VALUE' => 0, 'DISPLAY' => 'CHECKBOX'],
        ]);
        // No legacy ACTIVE=N user is automatically classified as pending.
        EventManager::getInstance()->registerEventHandlerCompatible(
            'main',
            'OnBeforeUserUpdate',
            'rebit.auth',
            RegistrationStateHandler::class,
            'onBeforeUserUpdate',
        );
    }

    public function down(): void
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnBeforeUserUpdate',
            'rebit.auth',
            RegistrationStateHandler::class,
            'onBeforeUserUpdate',
        );
        $this->getHelperManager()->UserTypeEntity()->deleteUserTypeEntityIfExists('USER', 'UF_AUTH_REGISTRATION_PENDING');
    }
}
