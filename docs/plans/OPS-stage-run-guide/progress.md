# OPS — путеводитель прогона stage — журнал

## Точка продолжения

- Ветка `codex/ops-stage-run-guide`, base `main` `1dd4a5f`. Worktree `/home/user/rabit-api-worktrees/ops-stage-run-guide`.
- Документация: [план](plan.md). Черновик на claude.ai: пульт прогона (общее хранилище, для владельца).
- Завершено: страницы `frontend/public/guide/`, проверки GUIDE-T01…T05.
- Сейчас: PR в `main` на review.
- Следующий шаг: после merge — frontend-only релиз по рецепту PR #45 (решение пользователя), затем GUIDE-T06.
- Блокеры: выкладка и включение оформления на stage требуют действий на сервере, которые агенту запрещены
  автоматическим режимом; выполняет пользователь или даёт разрешение.
- Рабочее дерево: новые файлы `frontend/public/guide/**`, `docs/plans/OPS-stage-run-guide/**`.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| GUIDE-T01 | PASS | 2026-09-25 | `npm run check` в `mcr.microsoft.com/playwright:v1.52.0-jammy`, том `rabit-issues42-node`: exit 0, test:ui 27/27 |
| GUIDE-T02 | PASS | 2026-09-25 | `docker build -f frontend/docker/production/nginx/Dockerfile --build-arg VITE_API_MOCKS_ENABLED=false -t morefoto-frontend:guide-check frontend` |
| GUIDE-T03 | PASS | 2026-09-25 | `curl` к контейнеру образа: `/guide/` и `/guide/stage-run/` — 200 со своим `<title>`; `/login`, `/cabinet/overview` — SPA; `/health` 200; `X-Robots-Tag: noindex, nofollow`, `Referrer-Policy: no-referrer` |
| GUIDE-T04 | PASS | 2026-09-25 | Playwright 1280×900 light, 390×844 light и dark: горизонтальный overflow 0, ошибок страницы и неудачных запросов нет; скриншоты в scratchpad сессии |
| GUIDE-T05 | PASS | 2026-09-25 | Отметки s1a/s1b, ссылка галереи и группа восстановлены после reload; «2 из 32 шагов», этап 1 «2 / 5», «Открыть» активна |
| GUIDE-T06 | PENDING | — | после выкладки |

## Журнал

### 2026-09-25

- Сверены маршруты кабинета, роли D08, готовность ссылки F2 (`LinkReadinessPolicy`), открытие галереи по `UF_SENT_AT`,
  флаг `MOREFOTO_CHECKOUT_ENABLED` (читается `getenv` в `morefoto.commerce/di/orders.php`), маршрут оплаты `demoOnly`.
- Сервер (чтение): backend — релиз `e6-20260925101815-54bd4ab`, frontend — `issues68-69-20260925104722-cfd5718`;
  у `morefoto_stage_fpm` нет `MOREFOTO_CHECKOUT_ENABLED`, `clear_env=no`. Попытка добавить переменную отклонена
  автоматическим режимом (запись на сервер); пользователю даны команды включения и отката.
- Пользователь выбрал отдельную статическую страницу без входа; `/guide/` — будущая база знаний.
- `main` после выложенного frontend `cfd5718` содержит слитый PR #76 (#72/#73, только frontend) — войдёт в следующую
  сборку frontend.
- Страницы перенесены в `frontend/public/guide/`: системные шрифты вместо Google Fonts, хранение полей в `localStorage`,
  тексты без имён переменных окружения и сведений о ключах.
- GUIDE-T01…T05 PASS (см. таблицу). По скриншоту desktop подпись «Ответственный группы» обрезалась — колонка 180 px
  и перенос подписи, повторный прогон чистый.
- Замечание вне scope: `/guide/stage-run` без слеша nginx перенаправляет абсолютным адресом со схемой `http`
  (`absolute_redirect` по умолчанию); ссылки используют адрес со слешем.
