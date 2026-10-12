<?php

declare(strict_types=1);

// Each version is a separate file <code>/<version>.md. A published version is never edited: a correction is a new version.
// reconsent = true: people who accepted an earlier version must accept this one again (changed purposes or data).
return [
    'privacy' => [
        'title' => 'Политика обработки персональных данных',
        'versions' => [
            ['version' => '2026-09-25', 'effectiveFrom' => '2026-09-25', 'reconsent' => false],
        ],
    ],
    'offer' => [
        'title' => 'Публичная оферта о продаже фотографий и фотопродукции',
        'versions' => [
            ['version' => '2026-09-25', 'effectiveFrom' => '2026-09-25', 'reconsent' => false],
            // E7: separate print (7 days) and digital (14 days) intake from the link delivery.
            ['version' => '2026-10-11', 'effectiveFrom' => '2026-10-11', 'reconsent' => false],
            // E8 and J4: the buyer gives the email only; a paid print brings the digital copy of its photo.
            ['version' => '2026-10-12', 'effectiveFrom' => '2026-10-12', 'reconsent' => false],
        ],
    ],
    'buyer-consent' => [
        'title' => 'Согласие покупателя на обработку персональных данных',
        'versions' => [
            ['version' => '2026-09-25', 'effectiveFrom' => '2026-09-25', 'reconsent' => true],
        ],
    ],
    'staff-consent' => [
        'title' => 'Согласие сотрудника на обработку персональных данных',
        'versions' => [
            ['version' => '2026-09-25', 'effectiveFrom' => '2026-09-25', 'reconsent' => true],
        ],
    ],
];
