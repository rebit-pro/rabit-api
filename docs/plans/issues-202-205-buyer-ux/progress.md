# Issues #202–#205 — прогресс

## Точка продолжения

- Дата: 15.10.2026. Ветка `codex/issues-202-205-buyer-ux` от main d4b3829. PR нет.
- Сейчас: S5 — ветка запушена, гейт PASS. PR не создан: `gh pr create` отклонён классификатором auto mode.
- Следующий шаг: создать PR (пользователь разрешает `gh pr create` или запускает его сам), затем merge и frontend-выкладка.
- Блокеры: нет разрешения на создание PR из сессии.

## Журнал

### 15.10.2026 — старт

- Владелец прислал скриншоты `var/img_18…22.png` со stage (телефон Android, домен p.morefoto36.ru). Заведены #202–#205 с причинами и способом исправления.
- #204: причина — меню «Поделиться» на Android не умеет «Сохранить в галерею», а 7,9 МБ скачиваются дольше, чем действует разрешение жеста (`NotAllowedError` → «нажмите ещё раз»).

### 15.10.2026 — правки и быстрые проверки

- #202: `BuyerGuideLink` получил вариант `prominent` (залитая кнопка ≥ 44 px, токены цвета/тени); в шапке галереи — «Как купить фото — 6 шагов».
- #203: `2-photo.webp` заменён снимком из img_20 (обрезан до 299×396); unit сверяет width/height всех шести слайдов с файлами.
- #204: `phoneSaveMode()` — Android → загрузка одной кнопкой «Сохранить в телефон» + подсказка про альбом «Загрузки»; iPhone — меню «Поделиться», компьютер — «Скачать».
- #205: «Оформить заказ · N» в окне кадра — липкая полоса `frame-checkout` внизу прокручиваемого окна.
- Быстрые проверки (docker playwright v1.52.0, том `rabit-e7-node`): `npm run check` — 0; `npm run test:commerce` — 248/248; `npm run test:ui` — 98/98. Сначала stylelint поймал hex-fallback (`color-no-hex`), заменён токенами.

### 15.10.2026 — полный гейт

- `make test-e2e …` (run `rabit-e2e-66c2e8433b2d`) — PASS, 377,6 с: группы a 312,7 с и b 288,6 с, все verify-*.php.
- Визуально (390 px): в шапке галереи залитая кнопка «Как купить фото — 6 шагов» (`e4-mobile-gallery.png`); на Android-контексте у каждого кадра «Сохранить в телефон», «Скачать» нет, подсказка про «Загрузки» под списком (`issues204-android-save-files.png`). Окно кадра скриншотом не снималось — видимость «Оформить заказ» проверена `toBeInViewport` на 390 и 1440 px.

## Команды

`make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`

## Тест-кейсы

| ID | Статус | Дата | Доказательство |
| --- | --- | --- | --- |
| BX-T01 | PASS | 15.10.2026 | `test:ui` 98/98; гейт `rabit-e2e-66c2e8433b2d`, `e4-mobile-gallery.png` |
| BX-T02 | PASS | 15.10.2026 | `test:ui`: KK-T01 сверяет размеры всех слайдов |
| BX-T03 | PASS | 15.10.2026 | `test:commerce` 248/248 |
| BX-T04 | PASS | 15.10.2026 | гейт `rabit-e2e-66c2e8433b2d`: байты загрузки = оригинал, `issues204-android-save-files.png` |
| BX-T05 | PASS | 15.10.2026 | гейт `rabit-e2e-66c2e8433b2d`: `frame-checkout` в пределах экрана, 390 и 1440 px |
| BX-T06 | PASS | 15.10.2026 | `npm run check` 0, `test:commerce` 248/248, `make test-e2e` PASS 377,6 с |
