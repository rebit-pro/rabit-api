# OPS — юридическая готовность MoreFoto — журнал

## Точка продолжения

- Ветка `codex/ops-legal-compliance` (реализация этапа A), base `main` `caa37b6`.
  Worktree `/home/user/rabit-api-worktrees/ops-legal-compliance`.
- Предыдущая ветка плана `codex/ops-legal-compliance-plan`, [PR #101](https://github.com/rebit-pro/rabit-api/pull/101):
  её два коммита перенесены сюда cherry-pick; PR #101 закрывается ссылкой на PR реализации.
- Документация: [план](plan.md).
- Завершено: контракт `Consent` в `rebit.share`, модуль `morefoto.legal` (реестр, черновики 4 документов, продавец из
  окружения, журнал `mf_legal_consent`, API), согласия в заказе и приглашении, страницы `/legal`, футер, плашка о cookie,
  диалог согласия в кабинете, TTL черновика контактов, E2E.
- Выполняется: PR на review (полный `make test-e2e` №3 PASS).
- Следующий шаг: PR на review; после merge — переменные `MOREFOTO_SELLER_*` на stage, миграция и symlink модуля при
  выкладке (решение пользователя).
- Открытые решения: LEG-DEC-03, 04, 06, 07, 08; проверка черновиков юристом; организационные шаги O1–O9.
- Рабочее дерево: всё закоммичено.
- Следующая проверка: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| LEG-T01 | PASS | 2026-09-25 | Ветка плана: `git diff --stat origin/main...HEAD` — только два файла плана |
| LEG-T02 | PASS | 2026-09-25 | `find`/`grep`: пути раздела «Фактическое состояние» существуют |
| LEG-T03 | PASS | 2026-09-25 | `phpunit --testsuite unit` в `rabit-api-php-cli:d1-local`: 734 теста OK; `morefoto.legal/tests/Unit` 17/17; `rebit.auth/tests` 86/86 |
| LEG-T04 | PASS | 2026-09-25 | `phpstan analyse --configuration=phpstan.neon`: No errors; php-cs-fixer dry-run по изменённым путям — 2 файла исправлены, повтор чистый |
| LEG-T05 | PASS | 2026-09-25 | `npm run check` в `mcr.microsoft.com/playwright:v1.52.0-jammy` (том `rabit-issues6263-node`): lint, stylelint, typecheck, typecheck:e2e, test:ui 43/43; `npm run test:commerce` 207/207 |
| LEG-T10, T11, T15, T20, T21 | PASS | 2026-09-25 | `make test-e2e` №3 (`rabit-e2e-1b96e250cb66`): `legal.spec.ts` — документы без входа, реквизиты подставлены, 390 px без прокрутки, футер, плашка без сторонних запросов, диалог `legal-pending` |
| LEG-T12, T13, T14 | PASS | 2026-09-25 | `zzzzz-orders.spec.ts`: `CONSENT_REQUIRED` без документов и с устаревшей версией, UI-оформление с чекбоксами desktop/mobile; `verify-orders.php`: две записи на каждый заказ, нет записей у отклонённых |
| LEG-T16 | PASS | 2026-09-25 | `useLiveCheckout` удаляет черновик после заказа (прежняя логика) + TTL 7 дней: `tests/legal/rules.test.mjs` |
| LEG-T17 | PASS | 2026-09-25 | `zz-access.spec.ts`: без согласия запрос не уходит, с согласием — вход; `AccessUseCasesTest`: без согласия ссылка и учётка не меняются |
| Полный gate | PASS | 2026-09-25 | `make test-e2e` №3: 403.6 с, группа a 74 passed, группа b 46 passed, 10 верификаторов MySQL passed; визуально: `e5-mobile-checkout.png`, `e5-desktop-checkout.png` |
| LEG-T18, T19 | PENDING | — | Этап C (LEG-5, LEG-6), не в этом PR |

## Журнал

### 2026-09-25

- Запрос пользователя: собрать, что нужно доделать с юридической стороны (cookie-баннер, правила, оферта и т. п.),
  чтобы пустить реальных пользователей в кабинет MoreFoto, и сделать PR с планом.
- Проверено: issue и PR на эту тему нет.
- Инвентаризация кода `main` 94502a1: ПДн сотрудников, покупателей, изображения детей; нет согласий, оферты,
  политики, реквизитов, футера, cookie-уведомления, возвратов, чеков (`receipt` не передаётся), сроков хранения
  и эндпоинтов удаления. Аналитики и внешних шрифтов в кабинете нет; контакты покупателя хранятся в `localStorage`.
- HTTP 25.09.2026: `morefoto36.ru` — wfolio, есть ИНН и заготовки политики, оферты и cookie-баннера, но
  `/privacy`, `/terms`, `/legal/*` отвечают 404; подключены Pinterest и `fonts.gstatic.com`. `app.morefoto36.ru` —
  шрифты локальные, `/privacy` отдаёт SPA (страницы нет).
- Нормы сверены по открытым источникам (ссылки в плане): отдельное согласие с 01.09.2025 (156-ФЗ), штрафы КоАП
  13.11 с 30.05.2025, штрафы за авторизацию (КоАП 13.55) с 07.07.2026, требования ЮKassa к сайту.
- План написан; тексты документов и спорные пункты оставлены юристу. Открыт PR #101.
- Пользователь взял план в реализацию. Ответы: LEG-DEC-01 — ИП; LEG-DEC-02 — черновики пишем мы; LEG-DEC-09 — один PR
  на этап A; LEG-DEC-05 — плашка «Понятно». Новая ветка `codex/ops-legal-compliance` от `main` `caa37b6`, план перенесён.
- Архитектура: общий контракт `Rebit\Share\Application\Contract\Consent` (его вызывают `morefoto.commerce` и общий
  `rebit.auth` внутри своих транзакций), реализация и тексты — новый модуль `morefoto.legal`. IP и user agent в журнал
  не пишутся (минимизация).
- Backend (`1e9b77b`): модуль, миграция `Version20260925230001` (таблица + регистрация модуля), согласия в
  `CreateOrderUseCase` (после размещения, откат вместе с заказом; хеш идемпотентности учитывает документы) и
  `AcceptAccessInvitationUseCase`, общий `AcceptedDocumentInputMapper` в `rebit.share`, переменные `MOREFOTO_SELLER_*`.
- Frontend (`b40b7dd`): `/legal`, `/legal/:code`, `/legal/:code/v/:version`, футер в `BlankLayout`, пункт «Документы» в
  меню, чекбоксы согласия и оферты в live-оформлении, продавец в условиях, ссылка на оферту у оплаты, чекбокс в
  приглашении, диалог согласия в кабинете, срок жизни черновика контактов 7 дней.
- Плашка о cookie сначала была плавающей внизу; переделана в полосу в потоке страницы: плавающая перекрывала бы кнопки на
  mobile и в десятках E2E-сценариев.
- E2E: засеяно согласие фикстурных сотрудников, добавлен `legal-pending`; тестовые реквизиты ИП передаются стенду;
  обновлены спеки заказов и приглашения; новый `legal.spec.ts` (группа `a`); `verify-orders.php` проверяет две записи
  согласия на каждый заказ и отсутствие записей у отклонённых.
- `make test-e2e` №1 (288.8 с): быстрые проверки и браузерная группа b PASS (214.6 с); `verify-orders.php` FAIL —
  скрипт сам создаёт заказ без `consents` (`ArgumentCountError` конструктора `CreateOrderInputDto`), группа a
  отменена. Исправлено: верификатор берёт действующие версии из `LegalDocumentCatalogInterface`. Прогон №2 запущен.
- `make test-e2e` №2 (430.3 с): группа b PASS (254.7 с), все верификаторы PASS (включая согласия в `verify-orders.php`);
  группа a — 73 passed, 1 failed: `LEG-T10/T21` нашёл «(будет указано до начала продаж)» в оферте — необязательный
  телефон продавца. Строка телефона убрана из оферты; `LegalDocumentCatalogTest` теперь проверяет, что при
  опубликованных обязательных реквизитах тексты не содержат незаполненных значений (17/17). Прогон №3 запущен.
- `make test-e2e` №3 (403.6 с): PASS — группа a 74/74, группа b 46/46, верификаторы storefront, handoff, orders,
  links, transfers, avatar, payment-costs, payments, access, support. Скриншот mobile-оформления просмотрен: полоса о
  cookie сверху, продавец в условиях, три отдельных чекбокса, футер с реквизитами.
