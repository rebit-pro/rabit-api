# OPS — путеводитель прогона stage — журнал

## Точка продолжения

- Ветка `codex/ops-stage-run-guide`, base `main` `1dd4a5f`. Worktree `/home/user/rabit-api-worktrees/ops-stage-run-guide`.
- Документация: [план](plan.md). Черновик на claude.ai: пульт прогона (общее хранилище, для владельца).
- Завершено: страницы `frontend/public/guide/`, проверки GUIDE-T01…T05.
- [PR #82](https://github.com/rebit-pro/rabit-api/pull/82) слит в `main` как `d92b4c4`; выложен frontend-only релиз
  `guide-20260925125036-7ec736a` (с #76, без #78).
- Следующий шаг: включение `MOREFOTO_CHECKOUT_ENABLED` на stage перед шагом 8 прогона — отдельным решением пользователя.
- Блокеров нет. Открыто: флаг оформления на stage (запись на сервер отклонялась автоматическим режимом).
- Рабочее дерево: новые файлы `frontend/public/guide/**`, `docs/plans/OPS-stage-run-guide/**`.

## Тест-кейсы

| ID | Статус | Дата | Команда / доказательство |
| --- | --- | --- | --- |
| GUIDE-T01 | PASS | 2026-09-25 | `npm run check` в `mcr.microsoft.com/playwright:v1.52.0-jammy`, том `rabit-issues42-node`: exit 0, test:ui 27/27 |
| GUIDE-T02 | PASS | 2026-09-25 | `docker build -f frontend/docker/production/nginx/Dockerfile --build-arg VITE_API_MOCKS_ENABLED=false -t morefoto-frontend:guide-check frontend` |
| GUIDE-T03 | PASS | 2026-09-25 | `curl` к контейнеру образа: `/guide/` и `/guide/stage-run/` — 200 со своим `<title>`; `/login`, `/cabinet/overview` — SPA; `/health` 200; `X-Robots-Tag: noindex, nofollow`, `Referrer-Policy: no-referrer` |
| GUIDE-T04 | PASS | 2026-09-25 | Playwright 1280×900 light, 390×844 light и dark: горизонтальный overflow 0, ошибок страницы и неудачных запросов нет; скриншоты в scratchpad сессии |
| GUIDE-T05 | PASS | 2026-09-25 | Отметки s1a/s1b, ссылка галереи и группа восстановлены после reload; «2 из 32 шагов», этап 1 «2 / 5», «Открыть» активна |
| GUIDE-T06 | PASS | 2026-09-25 | `curl https://app.morefoto36.ru`: `/guide/`, `/guide/stage-run/` 200 со своим `<title>`, `X-Robots-Tag: noindex, nofollow`; `/login`, `/cabinet/orders` 200 (SPA), `/health` 200; `index-B70UKJai.js` совпадает с образом |

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
- По поручению пользователя PR #82 слит (`d92b4c4`). В `main` к этому моменту слит PR #78 (#26–#28) с изменениями
  backend и frontend; по его журналу деплой отложен. Frontend собран из `7ec736a` (база `1dd4a5f`: выложенный
  `cfd5718` + #76 + путеводитель, #78 не входит — проверено `git merge-base --is-ancestor`).
- Релиз `/srv/morefoto/releases/guide-20260925125036-7ec736a` по рецепту PR #45: `frontend-image.tar.gz`
  (sha256 `a2c5d080…063b7`), `SHA256SUMS`, `switch-frontend.sh`. `morefoto_frontend` → `morefoto-frontend:guide-20260925125036-7ec736a`,
  converged, 2/2. Прежний образ (откат): `morefoto-frontend:issues68-69-20260925104722-cfd5718` в `frontend-before.txt`;
  откат — `docker service rollback morefoto_frontend`. Backend не менялся (`e6-20260925101815-54bd4ab`).
- GUIDE-T06 PASS (см. таблицу).
