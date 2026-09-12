<?php

declare(strict_types=1);

use Rebit\Share\Infrastructure\Bitrix\Module\ModuleHelper;

ModuleHelper::validateModuleInstalled('rebit.share');
ModuleHelper::validateModuleInstalled('rebit.auth');
ModuleHelper::validateModuleInstalled('highloadblock');
