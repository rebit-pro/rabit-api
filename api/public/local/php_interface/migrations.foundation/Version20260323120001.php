<?php

namespace Sprint\Migration;

use Sprint\Migration\Exceptions\HelperException;

class Version20260323120001 extends Version
{
    protected $author = 'auto';

    protected $description = 'Добавление полей UF_TOKEN и UF_TOKEN_EXPIRES_AT к пользователю';

    /**
     * @throws HelperException
     */
    public function up(): void
    {
        $helper = $this->getHelperManager();

        $helper->UserTypeEntity()->saveUserTypeEntity([
            'ENTITY_ID' => 'USER',
            'FIELD_NAME' => 'UF_TOKEN',
            'USER_TYPE_ID' => 'string',
            'XML_ID' => 'UF_TOKEN',
            'SORT' => 1000,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'N',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'N',
            'IS_SEARCHABLE' => 'N',
            'EDIT_FORM_LABEL' => ['ru' => 'Токен авторизации', 'en' => 'Auth Token'],
            'LIST_COLUMN_LABEL' => ['ru' => 'Токен авторизации', 'en' => 'Auth Token'],
            'SETTINGS' => [
                'SIZE' => 60,
                'ROWS' => 1,
                'REGEXP' => '',
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 255,
                'DEFAULT_VALUE' => '',
            ],
        ]);

        $helper->UserTypeEntity()->saveUserTypeEntity([
            'ENTITY_ID' => 'USER',
            'FIELD_NAME' => 'UF_TOKEN_EXPIRES_AT',
            'USER_TYPE_ID' => 'string',
            'XML_ID' => 'UF_TOKEN_EXPIRES_AT',
            'SORT' => 1010,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'N',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'N',
            'IS_SEARCHABLE' => 'N',
            'EDIT_FORM_LABEL' => ['ru' => 'Срок действия токена', 'en' => 'Token Expires At'],
            'LIST_COLUMN_LABEL' => ['ru' => 'Срок действия токена', 'en' => 'Token Expires At'],
            'SETTINGS' => [
                'SIZE' => 30,
                'ROWS' => 1,
                'REGEXP' => '',
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 30,
                'DEFAULT_VALUE' => '',
            ],
        ]);
    }

    /**
     * @throws HelperException
     */
    public function down(): void
    {
        $helper = $this->getHelperManager();

        $helper->UserTypeEntity()->deleteUserTypeEntityIfExists('USER', 'UF_TOKEN');
        $helper->UserTypeEntity()->deleteUserTypeEntityIfExists('USER', 'UF_TOKEN_EXPIRES_AT');
    }
}
