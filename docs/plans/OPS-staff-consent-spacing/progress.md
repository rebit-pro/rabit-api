# OPS - прогресс исправления формы согласия

## Точка продолжения

- 02.11.2026, ветка codex/ops-staff-consent-spacing от origin/main 9b89f306905bf64e21013305844051145706e16a. Main подтверждён GitHub CLI API; SSH fetch не прошёл из-за соединения с GitHub, удалённый SHA совпадает с локальным.
- Компонент: frontend/src/modules/morefoto/legal/components/StaffConsentDialog.vue. Vuetify 3.10.11, Vue 3.5.24, Composition API.
- Выполнено: PR #230 создан, одобренный вид опубликован на app.morefoto36.ru. Продуктовый commit релиза 71918f547bbdf52aafd9f1c143ff15c765ae6288. Последующее изменение ветки - только этот отчёт, frontend-дерево остаётся идентичным опубликованному.
- E2E исключены явным указанием пользователя. Изолированное визуальное превью - только для согласования внешнего вида.
- Пользователь 02.11.2026 написал «Картинку одобряю». Условие перед деплоем выполнено, deploy этой версии разрешён. Merge не выполняется автоматически; создаётся PR и отдельно публикуется его frontend.
- В дереве остаются чужие для этого PR неотслеживаемые docs/plans/OPS-stage-gallery-cleanup/; не добавлять их в PR.

## Проверки

- CS-01 PASS
- CS-02 PASS
- CS-03 PASS
- CS-04 PASS
- CS-05 PASS
- CS-06 PASS - frontend-only deploy выполнен после OK пользователя; CSS/HTTP и 2/2 реплики проверены.


## Проверки и решение 02.11.2026

- ESLint финального SFC - PASS. Prettier применён через правила ESLint проекта, script не менялся.
- Stylelint 16.26.1 с неизменной конфигурацией проекта - PASS. Локальный node_modules не содержал Stylelint, npm WSL получил ECONNRESET; временный Windows runtime и побайтно совпадающая копия SFC позволили выполнить ту же проверку без изменения package.json/lockfile.
- npm run build - PASS (vue-tsc и Vite); после финального text-wrap:balance повторный build-only - PASS.
- node --experimental-strip-types --test tests/legal/rules.test.mjs - PASS, 5/5.
- Локальное изолированное превью реального компонента: desktop 1000x640, mobile 390x740, ошибок браузера нет; padding 24/20 px, scrollWidth=clientWidth. Скриншоты просмотрены; внешний вид подтверждён пользователем.
- E2E не запускались по явному указанию пользователя. Превью не проходило приложение сквозным сценарием, не сохраняло согласие и не обращалось к серверу.
- git diff --check - PASS. В PR включаются только StaffConsentDialog.vue и два обязательных документа этой задачи.


## PR и публикация

- PR: https://github.com/rebit-pro/rabit-api/pull/230, база main; PR остаётся открытым, merge не выполнялся.
- GitHub push выполнен HTTPS через штатный gh credential helper: SSH GitHub закрывал соединение. Секреты не выводились, конфигурация remote постоянно не менялась.
- Сборка для публикации: VITE_API_URL=/api VITE_API_MOCKS_ENABLED=false VITE_APP_VERSION=pr-230-71918f5 npm run build-only - PASS. Параметры соответствуют production Dockerfile.
- Runtime-образ собран из того же nginx-образа, который работал на сервере, с заменой проверенного dist. ID локального базового образа совпал с серверным. Существующие nginx-конфигурация, API upstream, healthcheck и прочие настройки сохранены.
- Опубликован `morefoto-frontend:pr-230-71918f5`, image ID `sha256:12ba06efafb383f0ff61ae2315ce8c836155ea93e07cd0be2e98fc1400ec7db7`. Передан docker save/load; размер сжатого архива 44 745 357 байт.
- Команда обновляла только `morefoto_frontend` через docker service update --no-resolve-image --image. После rollout состояние completed, реплики 2/2. Нормализованный Spec, кроме Image, совпал с прежним; спецификации всех остальных сервисов совпали.
- HTTP /health - 200, защищённый /api/v1/institutions без токена - 401. HTML с сервера ссылается на новый entry bundle. CSS `CabinetLayout-BgTgyCJ3.css` скачан по HTTPS и побайтно совпал по SHA-256 с локальным артефактом; правило text-wrap:balance присутствует.
- База, фотографии, настройки API и фоновые сервисы при этой публикации не менялись. E2E не запускались.
- Автоматический откат по образу был предусмотрен на ошибку rollout/проверок и не потребовался. Прежний образ сохранён: `morefoto-frontend:main-20261101121402-9b89f30`.
- Ручной откат при необходимости: ssh photo.rebit-pro 'docker service update --no-resolve-image --image morefoto-frontend:main-20261101121402-9b89f30 --detach=false morefoto_frontend'.
- Подробный обезличенный локальный отчёт: var/ops-staff-consent-spacing/deployment.json.
