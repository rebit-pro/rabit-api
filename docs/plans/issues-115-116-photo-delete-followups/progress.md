# Issues #115 и #116 — журнал

## Точка продолжения

- Ветка `codex/issues-115-116-photo-delete-followups`, worktree
  `/home/user/rabit-api-worktrees/issues-115-116-photo-delete-followups`, base `origin/main` `4fc9dce`.
  Issues: #115, #116 (follow-up ревью PR #107). PR [#135](https://github.com/rebit-pro/rabit-api/pull/135).
- Завершено: реализация #115 и #116, быстрые проверки frontend и backend (PASS).
- Сейчас: PR #135 ждёт ревью; не слит.
- Следующий шаг: ревью PR; после ревью без блокеров — полный `make test-e2e` (координатор). Он впервые выполнит
  новый сценарий `#115/#116` в `zz-media.spec.ts` и верификатор `verify-photo-deletion.php`.
- Блокеров нет. Открытых решений нет.
- Рабочее дерево чистое; код в `bf47cc0` (#115) и `fcfce47` (#116).
- Команды:
  - Frontend: `docker run --rm -v $PWD/frontend:/app -v rabit-issues115116-node:/app/node_modules -w /app mcr.microsoft.com/playwright:v1.52.0-jammy npm ci`,
    затем с `--network none`: `sh -c "npm run check && npm run test:commerce"`.
  - Backend (`R`): `docker run --rm --network none --memory 4g --cpus 2 -e XDEBUG_MODE=off -v $PWD/api:/app -v /home/user/rabit-api/api/vendor:/app/vendor:ro --tmpfs /app/var:rw,size=512m -w /app rabit-api-php-cli:d1-local php`
    - `$R vendor/bin/phpunit --colors=never`
    - `$R -d memory_limit=4G vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress`
    - `$R vendor/bin/php-cs-fixer fix --config=public/local/php-cs-fixer.php --dry-run --diff --allow-risky=yes --path-mode=intersection <изменённые .php>`
      (для `tools/e2e/*.php` — `--path-mode=override`: finder конфига их не включает).
  - Полный gate: `make test-e2e E2E_PHP_CLI_IMAGE=rabit-api-php-cli:d1-local E2E_PHP_FPM_IMAGE=rabit-api-php-fpm:d1-local E2E_KERNEL_ROOT=/home/user/rebit-p2p/api/public/bitrix E2E_VENDOR_ROOT=/home/user/rabit-api/api/vendor`

## Статус тест-кейсов

| ID | Статус | Дата | Команда | Доказательство |
|---|---|---|---|---|
| T01 | PASS | 2026-09-26 | `npm run test:commerce` | `photo-deletion.test.mjs`: «сервер удалил кадры, ответ потерян…» — 2 вызова с одинаковыми `{groupId, revision: 5, photoIds, key-1}`, хотя экран уже держал ревизию 6; сервер удалил один раз; 409 нет |
| T02 | PASS | 2026-09-26 | `npm run test:commerce` | «определённый 409 завершает попытку…»: `key-1`/5 → `REVISION_CONFLICT`, затем `key-2`/7 |
| T03 | PASS | 2026-09-26 | `npm run test:commerce` | «отмена диалога забывает попытку…»: после `forget()` новый ключ и ревизия |
| T04 | PASS | 2026-09-26 | `npm run test:commerce` | «ответ 5xx…»: `key-1` дважды, одно удаление |
| T05 | PASS | 2026-09-26 | `npm run test:commerce` | тот же тест: другой набор/группа → `key-3`, `key-4` |
| T06 | PASS | 2026-09-26 | `npm run check` | `tests/support/*` без изменений: все 56 node-тестов `test:ui` PASS |
| T07 | PENDING | 2026-09-26 | `make test-e2e` (группа a) | сценарий `#115/#116` в `zz-media.spec.ts`: `route.fetch()` + `route.abort('connectionreset')`; ещё не запускался |
| T08 | PENDING | 2026-09-26 | `make test-e2e` (группа a) | тот же сценарий (HTTP) + раздел 1 `verify-photo-deletion.php` (строки, FK, обложка, файлы); ещё не запускался |
| T09 | PENDING | 2026-09-26 | `make test-e2e` (группа a) | раздел 2 `verify-photo-deletion.php`: реальный `DeleteGroupPhotosUseCase` → `PHOTO_PROCESSING`; ещё не запускался |
| T10 | PASS | 2026-09-26 | PHPUnit | `MediaMutationDeletionSqlTest` (4 теста): порядок duplicate → назначения → обложка → строки; `processing` → 409 без записей; шпион SQL, не реальная БД |
| T11 | PASS | 2026-09-26 | см. «Команды» | `npm run check` + `test:commerce` (213); PHPUnit 980/980; PHPStan OK; php-cs-fixer 0 из 2 |
| T12 | PENDING | — | `make test-e2e` | gate после ревью (координатор) |

## Журнал

### 2026-09-26

- Прочитаны #115, #116, план/журнал #105/#106, `DeleteGroupPhotosUseCase` (идемпотентность до сверки ревизии —
  повтор с тем же payload вернёт сохранённый успех), `MediaMutationRepository::deletablePhotos/deletePhotos`,
  support `mayHaveBeenStored`/`firstQuestion.ts`, `VERIFIERS` в `tools/run-browser-e2e.py`.
- Найдено: правило неизвестного исхода продублировано в support, `orders/live` (`checkoutOutcome`,
  `payment-rules.isUncertain`) и `handoff`. Здесь оно вынесено в `src/api/outcome.ts` только для support и photos;
  остальное — возможный follow-up.
- Найдено: `processing` с `UF_JOB_STATE='published'` не подбирается воркером (`pendingJobs` берёт `pending`) и
  проходит `ck_mf_photo_state` — так верификатор удерживает кадр «в обработке».
- #115 реализация:
  - `frontend/src/api/outcome.ts`: `RequestProblem`, `requestProblem`, `mayHaveBeenStored` (перенесены из support;
    support реэкспортирует их под прежними именами, поведение не изменилось).
  - `photos/deletion.ts`: `createPhotoDeletion` — попытка `{ groupId, revision, photoIds, key }` живёт до
    определённого ответа; повтор того же набора отправляет сохранённые ревизию, порядок ID и ключ.
  - `photosApi.remove(attempt)`; `usePhotoWorkspace`: `removePhotos` через попытку, `removalUnknown`,
    `cancelRemoval()` (забыть попытку и обновить список); диалог: кнопка «Повторить удаление».
  - Сообщение при неизвестном исходе: «Не удалось подтвердить удаление: связь прервалась. Нажмите «Повторить
    удаление» — если кадры уже удалены, повтор это подтвердит.»
- #116 реализация:
  - `zz-media.spec.ts`, тест `#115/#116`: 3 кадра + повторная загрузка canonical (duplicate), метки A/B, обложка на
    canonical; удаление canonical из диалога с оборванным ответом, повтор, проверка тела/ключа, списка, меток,
    обложки, 404 canonical и duplicate; данные для верификатора — `var/i116-deletion.json`.
  - `api/tools/e2e/verify-photo-deletion.php` + `VERIFIERS` (`zz-media`, файл `i116-deletion.json`).
  - `MediaMutationDeletionSqlTest` — дешёвая проверка SQL-порядка без стенда.
- Проверки:
  - `npm ci` в свежий том `rabit-issues115116-node` → rc 0.
  - Первый прогон `npm run check` → FAIL: prettier в двух новых файлах; исправлено `npx eslint --fix` по этим файлам.
    Второй → FAIL `typecheck:e2e` (`string | undefined` из деструктуризации) — исправлено явными переменными.
  - `npm run check && npm run test:commerce` (`--network none`) → rc 0: `test:ui` 56/56, `test:commerce` 213/213.
  - PHPUnit весь → OK (980 tests, 46223 assertions); медиа — 107/107.
  - PHPStan (`phpstan.neon`) → `[OK] No errors`. Верификатор вне путей конфига; ad hoc — только ошибка окружения
    `require.fileNotFound` для prolog, как у `verify-avatar.php`.
  - php-cs-fixer `--dry-run`: тест (intersection) и верификатор (override) → 0 из 2.
  - `php -l tools/e2e/verify-photo-deletion.php` → без ошибок; `python3 -m py_compile tools/run-browser-e2e.py` → ok.
- `make test-e2e`/`make e2e-up` не запускались по поручению: T07–T09, T12 — PENDING до gate после ревью.
- Визуальная проверка на заглушках не проводилась: изменён только текст кнопки и сообщение диалога; скриншот
  `i115-desktop-unknown-outcome.png` снимет live-сценарий.
- Коммиты `bf47cc0` (fix #115), `fcfce47` (test #116), `3b96e0e` (docs); push. Дублей по
  `gh pr list --state all --search "115 in:title"` / `"116 in:title"` нет. Создан PR #135 в main, не слит.
